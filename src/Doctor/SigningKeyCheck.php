<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Doctor;

use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * An active signing key must exist to issue tokens.
 */
final class SigningKeyCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-kit:signing-key';
    }

    public function description(): string
    {
        return 'An active signing key is present';
    }

    public function run(): DoctorResult
    {
        return LicensingKey::findActiveSigning() instanceof LicensingKey
            ? DoctorResult::pass('Active signing key present.')
            : DoctorResult::fail('Run `php artisan licensing:keys:issue-signing`.');
    }
}
