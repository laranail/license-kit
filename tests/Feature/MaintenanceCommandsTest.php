<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseExpiringSoon;
use Simtabi\Laranail\Licence\Kit\Tests\Helpers\LicenseTestHelper;

uses(LicenseTestHelper::class)->group('cli');

test('licensing:check reports failure without keys', function (): void {
    $this->artisan('licensing:check')
        ->expectsOutputToContain('license-kit:root-key')
        ->assertExitCode(1);
});

test('licensing:check passes after root and signing keys exist', function (): void {
    $this->createSigningKey();

    $exit = Artisan::call('licensing:check');

    // surface the report on failure so ci tells us which check failed
    expect($exit)->toBe(0, 'licensing:check failed: ' . Artisan::output());
});

test('licensing:check --json reports the enhanced crypto checks', function (): void {
    $this->createSigningKey();

    Artisan::call('licensing:check', ['--json' => true]);
    $output = Artisan::output();

    expect($output)
        ->toContain('license-kit:key-salt')
        ->toContain('license-kit:crypto')
        ->toContain('license-kit:key-storage');

    $exit = Artisan::call('licensing:check', ['--json' => true]);

    expect($exit)->toBe(0, 'licensing:check --json failed: ' . Artisan::output());
});

test('licensing:check fails when the key salt is not configured', function (): void {
    $this->createSigningKey();
    config()->set('licensing.key_salt', '');

    $this->artisan('licensing:check')
        ->expectsOutputToContain('license-kit:key-salt')
        ->assertExitCode(1);
});

test('licensing:check-expirations transitions active expired licenses to grace', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Active,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('licensing:check-expirations')->assertExitCode(0);

    expect($license->fresh()->status)->toBe(LicenseStatus::Grace);
});

test('licensing:check-expirations transitions grace licenses past grace window to expired', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Grace,
        'expires_at' => now()->subDays(config('licensing.policies.grace_days') + 1),
    ]);

    $this->artisan('licensing:check-expirations')->assertExitCode(0);

    expect($license->fresh()->status)->toBe(LicenseStatus::Expired);
});

test('licensing:check-expirations dry-run leaves licenses untouched', function (): void {
    $license = $this->createLicense([
        'status' => LicenseStatus::Active,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('licensing:check-expirations', ['--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);

    expect($license->fresh()->status)->toBe(LicenseStatus::Active);
});

test('licensing:check-expirations notifies licenses expiring soon', function (): void {
    Event::fake([LicenseExpiringSoon::class]);

    $this->createLicense([
        'status' => LicenseStatus::Active,
        'expires_at' => now()->addDays(3),
    ]);

    $this->artisan('licensing:check-expirations', ['--notify' => true])->assertExitCode(0);

    Event::assertDispatched(LicenseExpiringSoon::class);
});

test('licensing:cleanup-usages skips when policy disabled', function (): void {
    config()->set('licensing.policies.usage_inactivity_auto_revoke_days');

    $this->artisan('licensing:cleanup-usages')
        ->expectsOutputToContain('Auto-revoke disabled')
        ->assertExitCode(0);
});

test('licensing:cleanup-usages revokes inactive usages', function (): void {
    config()->set('licensing.policies.usage_inactivity_auto_revoke_days', 30);

    $license = $this->createLicense();
    $stale = $this->createUsage($license, ['last_seen_at' => now()->subDays(60)]);
    $fresh = $this->createUsage($license, ['last_seen_at' => now()->subDay()]);

    $this->artisan('licensing:cleanup-usages')->assertExitCode(0);

    expect($stale->fresh()->status)->toBe(UsageStatus::Revoked)
        ->and($fresh->fresh()->status)->toBe(UsageStatus::Active);
});
