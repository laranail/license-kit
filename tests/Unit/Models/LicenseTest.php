<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseActivated;
use Simtabi\Laranail\Licence\Kit\Events\LicenseExpired;
use Simtabi\Laranail\Licence\Kit\Events\LicenseRenewed;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseScope;
use Simtabi\Laranail\Licence\Kit\Tests\Helpers\LicenseTestHelper;

uses(LicenseTestHelper::class);

beforeEach(function (): void {
    Event::fake();
});

test('can create a license with hashed key', function (): void {
    $license = License::create([
        'key_hash' => License::hashKey('TEST-KEY-123'),
        'status' => LicenseStatus::Pending,
        'licensable_type' => 'App\Models\User',
        'licensable_id' => 1,
        'max_usages' => 5,
    ]);

    expect($license)->toBeInstanceOf(License::class)
        ->and($license->status)->toBe(LicenseStatus::Pending)
        ->and($license->max_usages)->toBe(5);
});

test('can find license by key', function (): void {
    $key = 'UNIQUE-LICENSE-KEY';
    $license = $this->createLicense([
        'key_hash' => License::hashKey($key),
    ]);

    $found = License::findByKey($key);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($license->id);
});

test('automatically generates uid on creation', function (): void {
    $license = $this->createLicense();

    expect($license->uid)->not->toBeNull()
        ->and(strlen((string) $license->uid))->toBe(26)
        ->and($license->uid)->toMatch('/^[0-9a-z]{26}$/');
});

test('can find license by uid', function (): void {
    $license = $this->createLicense();

    $found = License::findByUid($license->uid);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($license->id);
});

test('uid is unique across licenses', function (): void {
    $license1 = $this->createLicense();
    $license2 = $this->createLicense();

    expect($license1->uid)->not->toBe($license2->uid);
});

test('can verify license key', function (): void {
    $key = 'SECRET-LICENSE-KEY';
    $license = $this->createLicense([
        'key_hash' => License::hashKey($key),
    ]);

    expect($license->verifyKey($key))->toBeTrue()
        ->and($license->verifyKey('WRONG-KEY'))->toBeFalse();
});

test('can activate a pending license', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Pending,
        'activated_at' => null,
    ]);

    $license->activate();

    expect($license->status)->toBe(LicenseStatus::Active)
        ->and($license->activated_at)->not->toBeNull();

    Event::assertDispatched(LicenseActivated::class, fn ($event): bool => $event->license->id === $license->id);
});

test('cannot activate already active license', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Active,
    ]);

    $license->activate();
})->throws(RuntimeException::class, 'License cannot be activated in current status: active');

test('can renew license', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Active,
        'expires_at' => now()->addDays(7),
    ]);

    $newExpiration = now()->addYear();
    $license->renew($newExpiration);

    expect($license->expires_at->format('Y-m-d'))->toBe($newExpiration->format('Y-m-d'))
        ->and($license->renewals)->toHaveCount(1);

    Event::assertDispatched(LicenseRenewed::class);
});

test('can suspend and cancel license', function (): void {
    $license = $this->createLicense(['status' => LicenseStatus::Active]);

    $license->suspend();
    expect($license->status)->toBe(LicenseStatus::Suspended);

    $license->cancel();
    expect($license->status)->toBe(LicenseStatus::Cancelled);
});

test('can check if license is usable', function (): void {
    $activeLicense = $this->createLicense(['status' => LicenseStatus::Active]);
    $graceLicense = $this->createLicense(['status' => LicenseStatus::Grace]);
    $expiredLicense = $this->createLicense(['status' => LicenseStatus::Expired]);

    expect($activeLicense->isUsable())->toBeTrue()
        ->and($graceLicense->isUsable())->toBeTrue()
        ->and($expiredLicense->isUsable())->toBeFalse();
});

test('can check expiration status', function (): void {
    $expired = $this->createLicense(['expires_at' => now()->subDay()]);
    $valid = $this->createLicense(['expires_at' => now()->addDay()]);
    $perpetual = $this->createLicense(['expires_at' => null]);

    expect($expired->isExpired())->toBeTrue()
        ->and($valid->isExpired())->toBeFalse()
        ->and($perpetual->isExpired())->toBeFalse();
});

test('can calculate days until expiration', function (): void {
    $license = $this->createLicense(['expires_at' => now()->addDays(30)]);

    expect($license->daysUntilExpiration())->toBe(30);

    $perpetual = $this->createLicense(['expires_at' => null]);
    expect($perpetual->daysUntilExpiration())->toBeNull();
});

test('can check available seats', function (): void {
    $license = $this->createLicense(['max_usages' => 3]);

    expect($license->hasAvailableSeats())->toBeTrue()
        ->and($license->getAvailableSeats())->toBe(3);

    $this->createUsage($license);
    $this->createUsage($license);

    expect($license->getAvailableSeats())->toBe(1);

    $this->createUsage($license);

    expect($license->hasAvailableSeats())->toBeFalse()
        ->and($license->getAvailableSeats())->toBe(0);
});

test('can transition to grace period', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Active,
        'expires_at' => now()->subDay(),
    ]);

    $license->transitionToGrace();

    expect($license->status)->toBe(LicenseStatus::Grace);
});

test('can transition to expired after grace period', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Grace,
        'expires_at' => now()->subDays(15), // Default grace is 14 days
    ]);

    $license->transitionToExpired();

    expect($license->status)->toBe(LicenseStatus::Expired);
    Event::assertDispatched(LicenseExpired::class);
});

test('grace period respects configuration', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Grace,
        'expires_at' => now()->subDays(10),
        'meta' => ['policies' => ['grace_days' => 7]],
    ]);

    expect($license->gracePeriodExpired())->toBeTrue();

    $license2 = $this->createLicense([
        'status' => LicenseStatus::Grace,
        'expires_at' => now()->subDays(5),
        'meta' => ['policies' => ['grace_days' => 7]],
    ]);

    expect($license2->gracePeriodExpired())->toBeFalse();
});

test('license scope relation is accessible', function (): void {
    $scope = LicenseScope::create([
        'name' => 'Pro Suite',
        'slug' => 'pro-suite',
        'identifier' => 'com.example.pro-suite',
    ]);

    $license = $this->createLicense([
        'license_scope_id' => $scope->id,
    ]);

    expect($license->scope)->not->toBeNull()
        ->and($license->scope->id)->toBe($scope->id);

    $license->load('scope');
    expect($license->getRelation('scope')->name)->toBe('Pro Suite');
});
