<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Doctor;

use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * The core licensing tables must be migrated.
 */
final class TablesExistCheck implements DoctorCheck
{
    private const array TABLES = [
        'licenses',
        'license_usages',
        'license_renewals',
        'licensing_keys',
        'licensing_audit_logs',
    ];

    public function name(): string
    {
        return 'license-kit:tables';
    }

    public function description(): string
    {
        return 'The licensing database tables exist';
    }

    public function run(): DoctorResult
    {
        $missing = array_values(array_filter(self::TABLES, static fn (string $t): bool => ! Schema::hasTable($t)));

        return $missing === []
            ? DoctorResult::pass('All licensing tables present.')
            : DoctorResult::fail('Run `php artisan migrate`.', ['missing' => $missing]);
    }
}
