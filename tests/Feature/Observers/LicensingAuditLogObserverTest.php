<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;

beforeEach(function (): void {
    LicensingAuditLog::truncate();
    Config::set('licensing.audit.enabled', true);
    Config::set('licensing.audit.hash_chain', true);
});

test('audit log observer chains hashes when enabled', function (): void {
    $first = LicensingAuditLog::create([
        'event_type' => AuditEventType::LicenseCreated,
        'auditable_type' => 'App\\Models\\License',
        'auditable_id' => 1,
        'meta' => ['example' => 'first'],
    ]);

    $second = LicensingAuditLog::create([
        'event_type' => AuditEventType::LicenseActivated,
        'auditable_type' => 'App\\Models\\License',
        'auditable_id' => 1,
        'meta' => ['example' => 'second'],
    ]);

    expect($second->previous_hash)->toBe($first->calculateHash())
        ->and($second->verifyChain($first))->toBeTrue();
});

test('audit log observer skips hash chaining when disabled', function (): void {
    Config::set('licensing.audit.hash_chain', false);

    $first = LicensingAuditLog::create([
        'event_type' => AuditEventType::LicenseCreated,
        'auditable_type' => 'App\\Models\\License',
        'auditable_id' => 1,
        'meta' => ['example' => 'first'],
    ]);

    $second = LicensingAuditLog::create([
        'event_type' => AuditEventType::LicenseActivated,
        'auditable_type' => 'App\\Models\\License',
        'auditable_id' => 1,
        'meta' => ['example' => 'second'],
    ]);

    expect($first->previous_hash)->toBeNull()
        ->and($second->previous_hash)->toBeNull();
});

test('audit logs remain immutable after creation', function (): void {
    $log = LicensingAuditLog::create([
        'event_type' => AuditEventType::LicenseCreated,
        'auditable_type' => 'App\\Models\\License',
        'auditable_id' => 1,
        'meta' => ['example' => 'immutable'],
    ]);

    expect(fn () => $log->update(['meta' => ['example' => 'mutated']]))
        ->toThrow(RuntimeException::class, 'Audit logs are append-only and cannot be updated');
});
