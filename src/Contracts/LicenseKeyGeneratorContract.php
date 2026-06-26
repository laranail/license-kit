<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Simtabi\Laranail\Licence\Kit\Models\License;

interface LicenseKeyGeneratorContract
{
    /**
     * Generate a new license key.
     *
     * @param  License|null  $license  Optional license instance for context
     * @return string The generated license key
     */
    public function generate(?License $license = null): string;
}
