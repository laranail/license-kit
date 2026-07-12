<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Doctor;

use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * An active root key must exist to anchor the signing chain.
 */
final class RootKeyCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-kit:root-key';
    }

    public function description(): string
    {
        return 'An active root key is present';
    }

    public function run(): DoctorResult
    {
        return LicensingKey::findActiveRoot() instanceof LicensingKey
            ? DoctorResult::pass('Active root key present.')
            : DoctorResult::fail('Run `php artisan licensing:keys:make-root`.');
    }
}
