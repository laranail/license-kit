<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Simtabi\Laranail\Licence\Kit\Models\License;

interface LicenseKeyRetrieverContract
{
    /**
     * Retrieve the license key for a given license.
     *
     * @return string|null The license key or null if not retrievable
     */
    public function retrieve(License $license): ?string;

    /**
     * Check if the service supports key retrieval.
     */
    public function isAvailable(): bool;
}
