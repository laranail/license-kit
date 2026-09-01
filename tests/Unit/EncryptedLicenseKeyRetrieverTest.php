<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Services\EncryptedLicenseKeyRetriever;

it('warns (redacted) and returns null when a stored key cannot be decrypted', function (): void {
    Log::spy();

    $license = new License;
    $license->forceFill([
        'id' => 4242,
        'meta' => ['encrypted_key' => 'not-a-valid-ciphertext'],
    ]);

    $result = (new EncryptedLicenseKeyRetriever)->retrieve($license);

    expect($result)->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => str_contains($message, 'could not be decrypted')
            && ($context['license'] ?? null) === 4242
            && ! array_key_exists('encrypted_key', $context)
            && ! in_array('not-a-valid-ciphertext', $context, true),
    )->once();
});
