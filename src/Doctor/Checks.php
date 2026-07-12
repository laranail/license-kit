<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Doctor;

use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ConfigPresentCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpExtensionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;

/**
 * The canonical license-kit health checks — one list reused by the service
 * provider (unified doctor registration) and the `licensing:check` command.
 */
final class Checks
{
    /**
     * @return list<DoctorCheck|class-string<DoctorCheck>>
     */
    public static function all(): array
    {
        return [
            new ConfigPresentCheck(['licensing'], name: 'license-kit:config', description: 'The licensing config is loaded'),
            TablesExistCheck::class,
            RootKeyCheck::class,
            SigningKeyCheck::class,
            new ConfigPresentCheck(['LICENSING_KEY_SALT' => 'licensing.key_salt'], name: 'license-kit:key-salt', description: 'The key salt is configured'),
            new ConfigPresentCheck(['LICENSING_KEY_PASSPHRASE' => 'licensing.crypto.keystore.passphrase'], required: false, name: 'license-kit:key-passphrase', description: 'The key passphrase is configured'),
            KeyStorageCheck::class,
            new PhpExtensionCheck('sodium', 'license-kit:crypto', 'The PHP sodium extension is loaded'),
        ];
    }
}
