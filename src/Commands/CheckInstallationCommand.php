<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;

class CheckInstallationCommand extends Command
{
    protected $signature = 'laranail::license-kit.check {--json}';

    protected $description = 'Verify the licensing package installation status';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:check', 'licensing:doctor'];

    public function handle(): int
    {
        $checks = $this->runChecks();
        $hasFailure = collect($checks)->contains(static fn (array $row): bool => $row[1] === 'FAIL');

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => $hasFailure ? 'failed' : 'ok',
                'checks' => array_map(static fn (array $row): array => [
                    'name' => $row[0],
                    'status' => $row[1],
                    'detail' => $row[2],
                ], $checks),
            ], JSON_PRETTY_PRINT));

            return $hasFailure ? 1 : 0;
        }

        $this->table(['Check', 'Status', 'Details'], $checks);

        if ($hasFailure) {
            $this->error('Installation check failed. Resolve the items marked FAIL above.');

            return 1;
        }

        $this->info('Installation OK.');

        return 0;
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}>
     */
    protected function runChecks(): array
    {
        return [
            $this->checkConfig(),
            ...$this->checkTables(),
            $this->checkRootKey(),
            $this->checkSigningKey(),
            $this->checkKeySalt(),
            $this->checkKeyPassphrase(),
            $this->checkKeyStorageWritable(),
            $this->checkCryptoExtension(),
        ];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkConfig(): array
    {
        return config('licensing') === null
            ? ['Configuration', 'FAIL', 'Run `php artisan vendor:publish --tag=licensing-config`']
            : ['Configuration', 'OK', 'config/licensing.php loaded'];
    }

    /** @return array<int, array{0:string,1:string,2:string}> */
    protected function checkTables(): array
    {
        $tables = [
            'licenses',
            'license_usages',
            'license_renewals',
            'licensing_keys',
            'licensing_audit_logs',
        ];

        return array_map(static fn (string $table): array => Schema::hasTable($table)
            ? ["Table {$table}", 'OK', 'present']
            : ["Table {$table}", 'FAIL', 'Run `php artisan migrate`'], $tables);
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkRootKey(): array
    {
        return LicensingKey::findActiveRoot() instanceof LicensingKey
            ? ['Root key', 'OK', 'active root key present']
            : ['Root key', 'FAIL', 'Run `php artisan licensing:keys:make-root`'];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkSigningKey(): array
    {
        return LicensingKey::findActiveSigning() instanceof LicensingKey
            ? ['Signing key', 'OK', 'active signing key present']
            : ['Signing key', 'FAIL', 'Run `php artisan licensing:keys:issue-signing`'];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkKeySalt(): array
    {
        return config('licensing.key_salt')
            ? ['Key salt', 'OK', 'configured']
            : ['Key salt', 'FAIL', 'Set LICENSING_KEY_SALT (or APP_KEY)'];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkKeyPassphrase(): array
    {
        return config('licensing.crypto.keystore.passphrase')
            ? ['Key passphrase', 'OK', 'configured']
            : ['Key passphrase', 'WARN', 'LICENSING_KEY_PASSPHRASE not set (required for key operations)'];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkKeyStorageWritable(): array
    {
        $path = (string) config('licensing.crypto.keystore.path', storage_path('app/licensing/keys'));

        File::ensureDirectoryExists($path);

        return File::isWritable($path)
            ? ['Key storage', 'OK', $path]
            : ['Key storage', 'FAIL', "Not writable: {$path}"];
    }

    /** @return array{0:string,1:string,2:string} */
    protected function checkCryptoExtension(): array
    {
        return extension_loaded('sodium')
            ? ['Crypto extension', 'OK', 'sodium loaded']
            : ['Crypto extension', 'FAIL', 'ext-sodium is required'];
    }
}
