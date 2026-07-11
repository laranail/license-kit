<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Services;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Simtabi\Laranail\Licence\Kit\Contracts\LicenseKeyRetrieverContract;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

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
        } catch (Exception $e) {
            // A tolerated anomaly (failure-handling standard, rule 14): a key is
            // stored but won't decrypt (tampering, or a rotated APP_KEY). We keep
            // the documented "not retrievable" null contract, but surface it —
            // otherwise it is indistinguishable from "no key stored". Redacted
            // (rule 15): the license id and exception class only, never the
            // ciphertext or the decrypted key.
            FailurePolicy::warn('stored license key could not be decrypted', [
                'license' => $license->getKey(),
                'reason' => 'threw '.$e::class,
                'decision' => 'treated as not retrievable (returned null)',
            ]);

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
