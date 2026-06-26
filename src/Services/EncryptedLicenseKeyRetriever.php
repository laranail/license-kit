<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Services;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRetrieverContract;
use Simtabi\Laranail\Licence\Kit\Models\License;

class EncryptedLicenseKeyRetriever implements LicenseKeyRetrieverContract
{
    /**
     * Retrieve the license key for a given license.
     *
     * @return string|null The license key or null if not retrievable
     */
    public function retrieve(License $license): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        // Check if we have an encrypted key stored in meta
        $encryptedKey = $license->meta['encrypted_key'] ?? null;

        if (! $encryptedKey) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedKey);
        } catch (Exception) {
            // Log the error if needed
            return null;
        }
    }

    /**
     * Check if the service supports key retrieval.
     */
    public function isAvailable(): bool
    {
        return config('licensing.key_management.retrieval_enabled', true);
    }
}
