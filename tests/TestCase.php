<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Tests;

use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase as Orchestra;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicenseUsageObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingAuditLogObserver;
use Simtabi\Laranail\Licence\Kit\Observers\LicensingKeyObserver;
use Simtabi\Laranail\Licence\Kit\Providers\LicensingServiceProvider;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;
use Spatie\Sluggable\SluggableServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Simtabi\\Laranail\\Licence\\Kit\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        RateLimiter::for('api', fn () => Limit::perMinute(600));

        // Register observers for testing
        License::observe(LicenseObserver::class);
        LicenseUsage::observe(LicenseUsageObserver::class);
        LicensingKey::observe(LicensingKeyObserver::class);
        LicensingAuditLog::observe(LicensingAuditLogObserver::class);

        // Clear any cached data from previous tests
        LicensingKey::forgetCachedPassphrase();
    }

    protected function tearDown(): void
    {
        // Clear any cached data
        LicensingKey::forgetCachedPassphrase();

        // Clean up key storage
        $keyPath = config('licensing.crypto.keystore.path');
        if ($keyPath && File::exists($keyPath)) {
            // Try to delete directory, but don't fail if it doesn't work (Windows issue)
            try {
                File::deleteDirectory($keyPath);
            } catch (Exception) {
                // On Windows, files might still be locked
                // We'll try to clean individual files at least
                if (PHP_OS_FAMILY === 'Windows') {
                    $files = File::allFiles($keyPath);
                    foreach ($files as $file) {
                        try {
                            File::delete($file);
                        } catch (Exception) {
                            // Ignore individual file deletion errors
                        }
                    }
                }
            }
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            PackageToolsServiceProvider::class,
            LicensingServiceProvider::class,
            SluggableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'mysql' || $driver === 'mariadb') {
            config()->set('database.connections.testing', [
                'driver' => $driver,
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => (int) env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'licensing_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ]);
        } else {
            config()->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        config()->set('cache.default', 'array');

        // Set app key for encryption
        config()->set('app.key', 'base64:'.base64_encode('32characterslong1234567890123456'));

        config()->set('licensing.crypto.keystore.passphrase', 'test-passphrase-for-testing');
    }

    /**
     * Runs after RefreshDatabase's migrate:fresh has dropped every table, and
     * only once per process (the DatabaseRefreshed event fires exactly once,
     * gated by RefreshDatabaseState::$migrated). Including the published
     * stubs and calling up() here is safe on both SQLite in-memory and
     * persistent drivers like MySQL/MariaDB — no "table already exists".
     *
     * The execution order is pulled directly from the service provider's
     * Package configuration (the same list hasMigrations() consumes at
     * vendor:publish time), so the test suite cannot silently drift away
     * from the real install order.
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed()
    {
        $sourceDir = __DIR__.'/../database/migrations';

        foreach ($this->packageMigrationFileNames() as $name) {
            $stub = $sourceDir.'/'.$name.'.php.stub';
            if (! file_exists($stub)) {
                continue;
            }
            (include $stub)->up();
        }

        $usersTable = __DIR__.'/database/migrations/create_users_table.php';
        if (file_exists($usersTable)) {
            (include $usersTable)->up();
        }
    }

    /**
     * Ask the service provider for the authoritative migration order by
     * letting it configure a throwaway Package instance.
     *
     * @return array<int, string>
     */
    protected function packageMigrationFileNames(): array
    {
        $package = new Package;
        $package->setPathFrom(__DIR__.'/..');
        $package->name('laranail/license-kit');

        (new LicensingServiceProvider($this->app))->configurePackage($package);

        return $package->migrationFileNames;
    }
}
