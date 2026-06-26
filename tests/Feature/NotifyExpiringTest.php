<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Notifications\LicenseExpiringNotification;
use Simtabi\Laranail\Licence\Kit\Tests\Helpers\LicenseTestHelper;

uses(LicenseTestHelper::class);

it('sends nothing when expiring notifications are disabled', function (): void {
    config()->set('licensing.notifications.expiring.enabled', false);
    Notification::fake();

    $this->createLicense(['status' => LicenseStatus::Active, 'expires_at' => now()->addDays(3)]);

    $this->artisan('licensing:notify-expiring')->assertExitCode(0);

    Notification::assertNothingSent();
});

it('notifies configured admin recipients about expiring licenses', function (): void {
    config()->set('licensing.notifications.expiring.enabled', true);
    config()->set('licensing.notifications.expiring.to', ['admin@test.com']);
    Notification::fake();

    $this->createLicense(['status' => LicenseStatus::Active, 'expires_at' => now()->addDays(3)]);

    $this->artisan('licensing:notify-expiring')->assertExitCode(0);

    Notification::assertSentOnDemand(LicenseExpiringNotification::class);
});

it('skips licenses that are not near expiry', function (): void {
    config()->set('licensing.notifications.expiring.enabled', true);
    config()->set('licensing.notifications.expiring.to', ['admin@test.com']);
    Notification::fake();

    $this->createLicense(['status' => LicenseStatus::Active, 'expires_at' => now()->addDays(120)]);

    $this->artisan('licensing:notify-expiring')->assertExitCode(0);

    Notification::assertNothingSent();
});
