<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Providers;

use function class_exists;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\RateLimiter;
use Simtabi\Laranail\Licence\Kit\Commands\CheckExpirationsCommand;
use Simtabi\Laranail\Licence\Kit\Commands\CheckInstallationCommand;
use Simtabi\Laranail\Licence\Kit\Commands\CleanupUsagesCommand;
use Simtabi\Laranail\Licence\Kit\Commands\ExportKeysCommand;
use Simtabi\Laranail\Licence\Kit\Commands\IssueOfflineTokenCommand;
use Simtabi\Laranail\Licence\Kit\Commands\IssueSigningKeyCommand;
use Simtabi\Laranail\Licence\Kit\Commands\ListKeysCommand;
use Simtabi\Laranail\Licence\Kit\Commands\MakeRootKeyCommand;
use Simtabi\Laranail\Licence\Kit\Commands\RevokeKeyCommand;
use Simtabi\Laranail\Licence\Kit\Commands\RotateKeysCommand;
use Simtabi\Laranail\Licence\Kit\Contracts\AuditLogger;
use Simtabi\Laranail\Licence\Kit\Contracts\CertificateAuthority;
use Simtabi\Laranail\Licence\Kit\Contracts\FingerprintResolver;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyGeneratorContract;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRegeneratorContract;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRetrieverContract;
use Simtabi\Laranail\Licence\Kit\Contracts\TokenIssuer;
use Simtabi\Laranail\Licence\Kit\Contracts\TokenVerifier;
use Simtabi\Laranail\Licence\Kit\Contracts\UsageRegistrar;
use Simtabi\Laranail\Licence\Kit\LicenceKit;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseUsageObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingAuditLogObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingKeyObserver;
use Simtabi\Laranail\Licence\Kit\Services\AuditLoggerService;
use Simtabi\Laranail\Licence\Kit\Services\CertificateAuthorityService;
use Simtabi\Laranail\Licence\Kit\Services\FingerprintResolverService;
use Simtabi\Laranail\Licence\Kit\Services\TemplateService;
use Simtabi\Laranail\Licence\Kit\Services\UsageRegistrarService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LicensingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('license-kit')
            ->hasConfigFile('licensing')
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
            ]);

        if (config('licensing.api.enabled')) {
            $package->hasRoute('api');
        }
    }

    public function packageRegistered(): void
    {
        $this->registerServices();
        $this->registerLicenseKeyServices();
        $this->registerTokenService();
        $this->registerLicensing();
        $this->registerObservers();
        $this->registerPassphraseCleanup();
    }

    public function packageBooted(): void
    {
        $this->registerRateLimiters();
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
        $this->app->singleton(TokenIssuer::class, fn ($app) => $app->make(config('licensing.offline_token.service'))
        );

        $this->app->singleton(TokenVerifier::class, fn ($app) => $app->make(config('licensing.offline_token.service'))
        );

        $this->app->singleton('licensing.token', fn ($app) => $app->make(config('licensing.offline_token.service'))
        );
    }

    protected function registerLicensing(): void
    {
        $this->app->singleton(LicenceKit::class, fn ($app): LicenceKit => new LicenceKit(
            $app->make(UsageRegistrar::class),
            $app->make(TokenIssuer::class),
            $app->make(TokenVerifier::class)
        )
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
        RateLimiter::for('licensing-validate', fn ($request) => Limit::perMinute(config('licensing.rate_limit.validate_per_minute', 60))
            ->by($request->ip()));

        RateLimiter::for('licensing-register', fn ($request) => Limit::perMinute(config('licensing.rate_limit.register_per_minute', 30))
            ->by($request->ip()));

        RateLimiter::for('licensing-token', fn ($request) => Limit::perMinute(config('licensing.rate_limit.token_per_minute', 20))
            ->by($request->ip()));
    }

    protected function registerObservers(): void
    {
        License::observe(LicenseObserver::class);
        LicenseUsage::observe(LicenseUsageObserver::class);
        LicensingKey::observe(LicensingKeyObserver::class);
        LicensingAuditLog::observe(LicensingAuditLogObserver::class);
    }
}
