<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Providers;

use Override;

use function class_exists;

use Composer\InstalledVersions;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\RateLimiter;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Licence\Kit\LicenceKit;
use Simtabi\Laranail\Licence\Kit\Doctor\Checks;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Contracts\AuditLogger;
use Simtabi\Laranail\Licence\Kit\Contracts\TokenIssuer;
use Simtabi\Laranail\Licence\Kit\Commands\LicenseCommand;
use Simtabi\Laranail\Licence\Kit\Contracts\TokenVerifier;
use Simtabi\Laranail\Licence\Kit\Support\DeprecatedNames;
use Simtabi\Laranail\Licence\Kit\Commands\ListKeysCommand;
use Simtabi\Laranail\Licence\Kit\Contracts\UsageRegistrar;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;
use Simtabi\Laranail\Licence\Kit\Services\TemplateService;
use Simtabi\Laranail\Licence\Kit\Commands\RevokeKeyCommand;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseObserver;
use Simtabi\Laranail\Licence\Kit\Commands\ExportKeysCommand;
use Simtabi\Laranail\Licence\Kit\Commands\RotateKeysCommand;
use Simtabi\Laranail\Licence\Kit\Commands\MakeRootKeyCommand;
use Simtabi\Laranail\Licence\Kit\Services\AuditLoggerService;
use Simtabi\Laranail\Licence\Kit\Commands\CleanupUsagesCommand;
use Simtabi\Laranail\Licence\Kit\Contracts\FingerprintResolver;
use Simtabi\Laranail\Licence\Kit\Commands\NotifyExpiringCommand;
use Simtabi\Laranail\Licence\Kit\Contracts\CertificateAuthority;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseUsageObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingKeyObserver;
use Simtabi\Laranail\Licence\Kit\Services\UsageRegistrarService;
use Simtabi\Laranail\Licence\Kit\Commands\IssueSigningKeyCommand;
use Simtabi\Laranail\Licence\Kit\Commands\CheckExpirationsCommand;
use Simtabi\Laranail\Licence\Kit\Commands\CheckInstallationCommand;
use Simtabi\Laranail\Licence\Kit\Commands\IssueOfflineTokenCommand;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingAuditLogObserver;
use Simtabi\Laranail\Licence\Kit\Services\FingerprintResolverService;
use Simtabi\Laranail\Licence\Kit\Services\CertificateAuthorityService;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyGeneratorContract;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRetrieverContract;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRegeneratorContract;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;

class LicensingServiceProvider extends PackageServiceProvider
{
    /**
     * The prefix every API route name carries.
     */
    public const string ROUTE_PREFIX = 'laranail-license-kit.';

    /**
     * The bare route-name prefix used until 0.1. Still resolves, through package-tools'
     * BareRouteNameAliases, to the route under {@see ROUTE_PREFIX}. The names under it are
     * deprecated and are removed no earlier than the next minor after 0.1.
     */
    public const string DEPRECATED_ROUTE_PREFIX = 'licensing.';

    /**
     * The token service's container binding.
     */
    public const string TOKEN_BINDING = 'laranail.license-kit.token';

    /**
     * The bare container key used until 0.1, kept as an alias of {@see TOKEN_BINDING}. It is
     * deprecated and is removed no earlier than the next minor after 0.1.
     */
    public const string DEPRECATED_TOKEN_BINDING = 'licensing.token';

    /**
     * Rate limiter name => the config key holding its per-minute limit, and that key's default.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    public const array RATE_LIMITERS = [
        'laranail-license-kit.validate' => ['licensing.rate_limit.validate_per_minute', 60],
        'laranail-license-kit.register' => ['licensing.rate_limit.register_per_minute', 30],
        'laranail-license-kit.token'    => ['licensing.rate_limit.token_per_minute', 20],
    ];

    /**
     * The bare rate limiter names used until 0.1 => their scoped replacements. Each stays
     * registered, announces itself once and delegates to the scoped limiter. The bare names
     * are deprecated and are removed no earlier than the next minor after 0.1.
     *
     * @var array<string, string>
     */
    public const array DEPRECATED_RATE_LIMITERS = [
        'licensing-validate' => 'laranail-license-kit.validate',
        'licensing-register' => 'laranail-license-kit.register',
        'licensing-token'    => 'laranail-license-kit.token',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/license-kit')
            ->hasConfigFile('licensing')
            ->withoutConfigNamespacing()
            ->hasTranslations('laranail-license-kit')
            // The API route names were bare `licensing.*` until 0.1; they still resolve to the
            // scoped `laranail-license-kit.*` routes, and announce themselves once per process.
            ->hasDeprecatedRouteNames(prefixes: [self::DEPRECATED_ROUTE_PREFIX => self::ROUTE_PREFIX])
            ->hasMigrations([
                // Order matters: parents before children, FK targets before FK holders.
                'create_license_scopes_table',
                'create_license_templates_table',
                'create_licenses_table',
                'create_license_usages_table',
                'create_license_renewals_table',
                'create_license_trials_table',
                'add_trial_and_duration_columns_to_license_templates_table',
                'create_license_transfers_table',
                'create_license_transfer_histories_table',
                'create_license_transfer_approvals_table',
                'create_licensing_keys_table',
                'create_licensing_audit_logs_table',
            ])
            ->hasCommands([
                MakeRootKeyCommand::class,
                IssueSigningKeyCommand::class,
                RotateKeysCommand::class,
                ListKeysCommand::class,
                RevokeKeyCommand::class,
                ExportKeysCommand::class,
                IssueOfflineTokenCommand::class,
                CheckInstallationCommand::class,
                CheckExpirationsCommand::class,
                CleanupUsagesCommand::class,
                NotifyExpiringCommand::class,
                LicenseCommand::class,
            ])
            ->hasDoctorChecks(Checks::all())
            ->hasAboutSection(
                AboutSectionDefinition::make('License Kit')
                    ->field('Version', fn (): string => (string) InstalledVersions::getPrettyVersion('laranail/license-kit'))
                    ->field('Key prefix', fn (): string => (string) config('licensing.key_management.key_prefix', 'LIC')),
            );
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->registerServices();
        $this->registerLicenseKeyServices();
        $this->registerTokenService();
        $this->registerLicensing();
        $this->registerObservers();
        $this->registerPassphraseCleanup();
    }

    #[Override]
    public function packageBooted(): void
    {
        $this->registerRateLimiters();
        $this->registerSchedule();
        $this->registerApiRoutes();
    }

    /**
     * Register the scheduled maintenance tasks declared in the `scheduler` config.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $config = (array) config('licensing.scheduler', []);

            if (($config['check_expirations']['enabled'] ?? false)) {
                $schedule->command(CheckExpirationsCommand::class)
                    ->dailyAt((string) ($config['check_expirations']['time'] ?? '02:00'))
                    ->name('license-kit:check-expirations')
                    ->withoutOverlapping();
            }

            if (($config['cleanup_inactive_usages']['enabled'] ?? false)) {
                $schedule->command(CleanupUsagesCommand::class)
                    ->dailyAt((string) ($config['cleanup_inactive_usages']['time'] ?? '03:00'))
                    ->name('license-kit:cleanup-usages')
                    ->withoutOverlapping();
            }

            if (($config['notify_expiring']['enabled'] ?? false)) {
                $schedule->command(NotifyExpiringCommand::class)
                    ->dailyAt((string) ($config['notify_expiring']['time'] ?? '09:00'))
                    ->name('license-kit:notify-expiring')
                    ->withoutOverlapping();
            }
        });
    }

    protected function registerServices(): void
    {
        $this->app->singleton(CertificateAuthority::class, CertificateAuthorityService::class);
        $this->app->singleton(UsageRegistrar::class, UsageRegistrarService::class);
        $this->app->singleton(FingerprintResolver::class, FingerprintResolverService::class);
        $this->app->singleton(AuditLogger::class, AuditLoggerService::class);
        $this->app->singleton(TemplateService::class);
    }

    protected function registerLicenseKeyServices(): void
    {
        // Register key generator
        $this->app->singleton(LicenseKeyGeneratorContract::class, function ($app): object {
            $class = config('licensing.services.key_generator');

            return new $class;
        });

        // Register key retriever
        $this->app->singleton(LicenseKeyRetrieverContract::class, function ($app): object {
            $class = config('licensing.services.key_retriever');

            return new $class;
        });

        // Register key regenerator
        $this->app->singleton(LicenseKeyRegeneratorContract::class, function ($app): object {
            $class = config('licensing.services.key_regenerator');
            $generator = $app->make(LicenseKeyGeneratorContract::class);

            return new $class($generator);
        });
    }

    protected function registerTokenService(): void
    {
        $this->app->singleton(
            TokenIssuer::class,
            fn ($app) => $app->make(config('licensing.offline_token.service')),
        );

        $this->app->singleton(
            TokenVerifier::class,
            fn ($app) => $app->make(config('licensing.offline_token.service')),
        );

        $this->app->singleton(
            self::TOKEN_BINDING,
            fn ($app) => $app->make(config('licensing.offline_token.service')),
        );

        // Kept for hosts that resolve the pre-0.1 key. A container alias cannot announce itself,
        // so the deprecation is documented rather than raised.
        $this->app->alias(self::TOKEN_BINDING, self::DEPRECATED_TOKEN_BINDING);
    }

    protected function registerLicensing(): void
    {
        $this->app->singleton(
            LicenceKit::class,
            fn ($app): LicenceKit => new LicenceKit(
                $app->make(UsageRegistrar::class),
                $app->make(TokenIssuer::class),
                $app->make(TokenVerifier::class),
            ),
        );
    }

    protected function registerPassphraseCleanup(): void
    {
        $cleanup = static function (): void {
            LicensingKey::forgetCachedPassphrase();
        };

        // Octane: clear passphrase after each request
        if (class_exists('Laravel\Octane\Events\RequestTerminated')) {
            $this->app['events']->listen('Laravel\Octane\Events\RequestTerminated', $cleanup);
            $this->app['events']->listen('Laravel\Octane\Events\TaskTerminated', $cleanup);
        }

        // Queue: clear passphrase when worker stops
        $this->app['events']->listen(WorkerStopping::class, $cleanup);
        $this->app['events']->listen(JobProcessed::class, $cleanup);
        $this->app['events']->listen(JobFailed::class, $cleanup);
    }

    protected function registerRateLimiters(): void
    {
        foreach (self::RATE_LIMITERS as $name => [$key, $default]) {
            RateLimiter::for($name, fn ($request) => Limit::perMinute(config($key, $default))
                ->by($request->ip()));
        }

        foreach (self::DEPRECATED_RATE_LIMITERS as $old => $scoped) {
            RateLimiter::for($old, static function ($request) use ($old, $scoped) {
                DeprecatedNames::announce('rate limiter', $old, $scoped);

                return RateLimiter::limiter($scoped)($request);
            });
        }
    }

    protected function registerObservers(): void
    {
        License::observe(LicenseObserver::class);
        LicenseUsage::observe(LicenseUsageObserver::class);
        LicensingKey::observe(LicensingKeyObserver::class);
        LicensingAuditLog::observe(LicensingAuditLogObserver::class);
    }

    /**
     * Load the API routes when enabled — at boot, where the merged config (including
     * the package default) is authoritative. Gating at configurePackage() time reads
     * config before the package config is merged, so an app that has not published the
     * config would silently never register the API routes (the default is `true`).
     */
    private function registerApiRoutes(): void
    {
        if (config('licensing.api.enabled')) {
            $this->loadRoutesFrom($this->package->basePath('/routes/api.php'));
        }
    }
}
