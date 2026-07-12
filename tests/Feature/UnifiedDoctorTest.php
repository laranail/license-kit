<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

test('the kit registers its checks into the unified package-tools doctor', function (): void {
    $service = app(DoctorService::class);
    $names = array_map(static fn (DoctorCheck $check): string => $check->name(), $service->getChecks());

    expect($names)
        ->toContain('license-kit:root-key')
        ->toContain('license-kit:tables')
        ->toContain('license-kit:crypto');
});
