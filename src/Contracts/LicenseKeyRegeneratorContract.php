<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Simtabi\Laranail\Licence\Kit\Models\License;

interface LicenseKeyRegeneratorContract
{
    /**
     * Regenerate the license key for a given license.
     *
     * @return string The new license key
     */
    public function regenerate(License $license): string;

    /**
     * Check if the service supports key regeneration.
     */
    public function isAvailable(): bool;
}
