<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Kit\Contracts\UsageRegistrar;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTrial;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Services\TrialService;

/**
 * These tests pin the public API surface that the Boost guideline file
 * (resources/boost/guidelines/laravel-licensing/core.blade.php) references in
 * its copy-paste snippets. If a method gets renamed or its signature changes,
 * these tests fail loudly so the guideline is updated in the same commit.
 */
function assertSignature(ReflectionMethod $m, array $expected): void
{
    $actual = collect($m->getParameters())
        ->map(fn (ReflectionParameter $p): array => [
            'name' => $p->getName(),
            'type' => $p->getType() instanceof ReflectionType ? (string) $p->getType() : null,
            'optional' => $p->isOptional(),
        ])
        ->all();

    expect($actual)->toEqual($expected);
}

it('License::createWithKey matches the guideline snippet', function (): void {
    $m = new ReflectionMethod(License::class, 'createWithKey');

    expect($m->isStatic())->toBeTrue();
    assertSignature($m, [
        ['name' => 'attributes', 'type' => 'array', 'optional' => true],
        ['name' => 'providedKey', 'type' => '?string', 'optional' => true],
    ]);
});

it('License::createFromTemplate matches the guideline snippet', function (): void {
    $m = new ReflectionMethod(License::class, 'createFromTemplate');

    expect($m->isStatic())->toBeTrue();
    assertSignature($m, [
        ['name' => 'template', 'type' => 'Simtabi\\Laranail\\Licence\\Kit\\Models\\LicenseTemplate|string', 'optional' => false],
        ['name' => 'attributes', 'type' => 'array', 'optional' => true],
    ]);
});

it('UsageRegistrar::register matches the guideline snippet', function (): void {
    $m = new ReflectionMethod(UsageRegistrar::class, 'register');

    assertSignature($m, [
        ['name' => 'license', 'type' => License::class, 'optional' => false],
        ['name' => 'fingerprint', 'type' => 'string', 'optional' => false],
        ['name' => 'metadata', 'type' => 'array', 'optional' => true],
    ]);
});

it('LicenseUsage::heartbeat takes no arguments', function (): void {
    $m = new ReflectionMethod(LicenseUsage::class, 'heartbeat');

    expect($m->getNumberOfParameters())->toBe(0);
});

it('TrialService::startTrial matches the guideline snippet', function (): void {
    $m = new ReflectionMethod(TrialService::class, 'startTrial');

    assertSignature($m, [
        ['name' => 'license', 'type' => License::class, 'optional' => false],
        ['name' => 'fingerprint', 'type' => 'string', 'optional' => false],
        ['name' => 'durationDays', 'type' => 'int', 'optional' => true],
        ['name' => 'limitations', 'type' => 'array', 'optional' => true],
        ['name' => 'featureRestrictions', 'type' => 'array', 'optional' => true],
    ]);
});

it('LicenseTrial::convert matches the guideline snippet', function (): void {
    $m = new ReflectionMethod(LicenseTrial::class, 'convert');

    assertSignature($m, [
        ['name' => 'trigger', 'type' => '?string', 'optional' => true],
        ['name' => 'value', 'type' => '?float', 'optional' => true],
    ]);
});

it('LicenseTrial::hasActiveTrialForFingerprint exists and is static', function (): void {
    $m = new ReflectionMethod(LicenseTrial::class, 'hasActiveTrialForFingerprint');

    expect($m->isStatic())->toBeTrue();
    assertSignature($m, [
        ['name' => 'fingerprint', 'type' => 'string', 'optional' => false],
    ]);
});
