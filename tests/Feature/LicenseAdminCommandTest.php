<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseSuspended;
use Simtabi\Laranail\Licence\Kit\Tests\Helpers\LicenseTestHelper;

uses(LicenseTestHelper::class);

it('shows a license by uid', function (): void {
    $license = $this->createLicense(['status' => LicenseStatus::Active]);

    $this->artisan('licensing:license', ['action' => 'show', 'key' => $license->uid])
        ->assertExitCode(0);
});

it('fails for an unknown license', function (): void {
    $this->artisan('licensing:license', ['action' => 'show', 'key' => 'nope'])
        ->assertExitCode(1);
});

it('suspends a license and fires the event', function (): void {
    Event::fake([LicenseSuspended::class]);
    $license = $this->createLicense(['status' => LicenseStatus::Active]);

    $this->artisan('licensing:license', ['action' => 'suspend', 'key' => $license->uid, '--force' => true])
        ->assertExitCode(0);

    expect($license->fresh()->status)->toBe(LicenseStatus::Suspended);
    Event::assertDispatched(LicenseSuspended::class);
});

it('reinstates a suspended license to active', function (): void {
    $license = $this->createLicense(['status' => LicenseStatus::Suspended]);

    $this->artisan('licensing:license', ['action' => 'reinstate', 'key' => $license->uid, '--force' => true])
        ->assertExitCode(0);

    expect($license->fresh()->status)->toBe(LicenseStatus::Active);
});
