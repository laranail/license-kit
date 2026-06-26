<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Licence\Kit\LicenceKit;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;

/**
 * @method static License findByKey(string $key)
 * @method static LicenseUsage register(License $license, string $fingerprint, array $metadata = [])
 * @method static string issueToken(License $license, LicenseUsage $usage, array $options = [])
 * @method static array verifyToken(string $token, array $options = [])
 *
 * @see LicenceKit
 */
class LicenceKitFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LicenceKit::class;
    }
}
