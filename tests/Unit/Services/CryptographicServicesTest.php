<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Kit\Enums\KeyStatus;
use Simtabi\Laranail\Licence\Kit\Enums\KeyType;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Services\CertificateAuthorityService;
use Simtabi\Laranail\Licence\Kit\Tests\Helpers\LicenseTestHelper;

uses(LicenseTestHelper::class);

beforeEach(function (): void {
    $this->ca = app(CertificateAuthorityService::class);
});

test('can generate root key pair', function (): void {
    $rootKey = $this->createRootKey();

    expect($rootKey)->toBeInstanceOf(LicensingKey::class)
        ->and($rootKey->type)->toBe(KeyType::Root)
        ->and($rootKey->status)->toBe(KeyStatus::Active)
        ->and($rootKey->public_key)->not->toBeEmpty()
        ->and($rootKey->private_key_encrypted)->not->toBeEmpty()
        ->and($rootKey->kid)->toStartWith('kid_');
});

test('can generate signing key pair', function (): void {
    $this->createRootKey();

    $signingKey = new LicensingKey;
    $signingKey->generate(['type' => KeyType::Signing]);

    expect($signingKey->type)->toBe(KeyType::Signing)
        ->and($signingKey->valid_until)->not->toBeNull()
        ->and($signingKey->valid_from->diffInDays($signingKey->valid_until))->toEqual(30);
});

test('can encrypt and decrypt private key', function (): void {
    $key = $this->createRootKey();
    $privateKey = $key->getPrivateKey();

    expect($privateKey)->not->toBeNull()
        ->and($privateKey)->toBeString()
        ->and(strlen(base64_decode((string) $privateKey)))->toBe(64); // Ed25519 secret key is 64 bytes
});

test('can issue signing certificate', function (): void {
    $rootKey = $this->createRootKey();
    $signingKey = new LicensingKey;
    $signingKey->generate(['type' => KeyType::Signing]);

    $certificate = $this->ca->issueSigningCertificate(
        $signingKey->getPublicKey(),
        $signingKey->kid,
        now(),
        now()->addDays(30)
    );

    expect($certificate)->not->toBeEmpty()
        ->and(json_decode((string) $certificate, true))->toHaveKeys(['certificate', 'signature']);
});

test('can verify valid certificate', function (): void {
    $rootKey = $this->createRootKey();
    $signingKey = new LicensingKey;
    $signingKey->generate(['type' => KeyType::Signing]);

    $certificate = $this->ca->issueSigningCertificate(
        $signingKey->getPublicKey(),
        $signingKey->kid,
        now(),
        now()->addDays(30)
    );

    expect($this->ca->verifyCertificate($certificate))->toBeTrue();
});

test('rejects tampered certificate', function (): void {
    $rootKey = $this->createRootKey();
    $signingKey = new LicensingKey;
    $signingKey->generate(['type' => KeyType::Signing]);

    $certificate = $this->ca->issueSigningCertificate(
        $signingKey->getPublicKey(),
        $signingKey->kid,
        now(),
        now()->addDays(30)
    );

    $data = json_decode((string) $certificate, true);
    $data['certificate']['kid'] = 'tampered_kid';
    $tamperedCertificate = json_encode($data);

    expect($this->ca->verifyCertificate($tamperedCertificate))->toBeFalse();
});

test('can get certificate chain', function (): void {
    $signingKey = $this->createSigningKey();

    $chain = $this->ca->getCertificateChain($signingKey->kid);

    expect($chain)->toHaveKeys(['signing', 'root'])
        ->and($chain['signing'])->toHaveKeys(['kid', 'public_key', 'certificate', 'valid_from', 'valid_until'])
        ->and($chain['root'])->toHaveKeys(['kid', 'public_key', 'valid_from']);
});

test('can find active root key', function (): void {
    $rootKey = $this->createRootKey();

    $found = LicensingKey::findActiveRoot();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($rootKey->id)
        ->and($found->type)->toBe(KeyType::Root);
});

test('can find active signing key', function (): void {
    $signingKey = $this->createSigningKey();

    $found = LicensingKey::findActiveSigning();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($signingKey->id)
        ->and($found->type)->toBe(KeyType::Signing);
});

test('can revoke key', function (): void {
    $key = $this->createSigningKey();

    $key->revoke('compromised');

    expect($key->status)->toBe(KeyStatus::Revoked)
        ->and($key->revoked_at)->not->toBeNull()
        ->and($key->revocation_reason)->toBe('compromised')
        ->and($key->isActive())->toBeFalse();
});

test('expired key is not active', function (): void {
    $key = new LicensingKey;
    $key->generate([
        'type' => KeyType::Signing,
        'valid_from' => now()->subDays(60),
        'valid_until' => now()->subDays(30),
    ]);

    expect($key->isActive())->toBeFalse();
});

test('future key is not active', function (): void {
    $key = new LicensingKey;
    $key->generate([
        'type' => KeyType::Signing,
        'valid_from' => now()->addDay(),
        'valid_until' => now()->addDays(30),
    ]);

    expect($key->isActive())->toBeFalse();
});

test('can sign and verify data with keys', function (): void {
    $key = $this->createRootKey();
    $data = 'test data to sign';

    // Sign with Ed25519
    $privateKey = base64_decode((string) $key->getPrivateKey());
    $signature = sodium_crypto_sign_detached($data, $privateKey);

    // Verify with Ed25519
    $publicKey = base64_decode((string) $key->getPublicKey());
    $verified = sodium_crypto_sign_verify_detached($signature, $data, $publicKey);

    expect($verified)->toBeTrue();

    $tamperedData = 'tampered data';
    expect(sodium_crypto_sign_verify_detached($signature, $tamperedData, $publicKey))->toBeFalse();
});
