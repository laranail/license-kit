<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Doctor;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * The keystore path (resolved at run time) must be writable.
 */
final class KeyStorageCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-kit:key-storage';
    }

    public function description(): string
    {
        return 'The keystore path is writable';
    }

    public function run(): DoctorResult
    {
        $path = (string) config('licensing.crypto.keystore.path', storage_path('app/licensing/keys'));

        try {
            File::ensureDirectoryExists($path);
        } catch (Throwable) {
            // fall through to writability check
        }

        return File::isWritable($path)
            ? DoctorResult::pass($path)
            : DoctorResult::fail("Not writable: {$path}");
    }
}
