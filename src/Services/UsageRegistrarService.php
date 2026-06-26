<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Simtabi\Laranail\Licence\Kit\Contracts\UsageRegistrar;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Enums\OverLimitPolicy;
use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Events\UsageLimitReached;
use Simtabi\Laranail\Licence\Kit\Events\UsageRegistered;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;

class UsageRegistrarService implements UsageRegistrar
{
    public function register(
        License $license,
        string $fingerprint,
        array $metadata = []
    ): LicenseUsage {
        $shouldLogLimitReached = false;
        /** @var array{license_id?: string, license_class?: string, fingerprint?: string} $auditData */
        $auditData = [];

        try {
            return DB::transaction(function () use ($license, $fingerprint, $metadata, &$shouldLogLimitReached, &$auditData) {
                /** @var License|null $lockedLicense */
                $lockedLicense = $license->newQuery()
                    ->lockForUpdate()
                    ->find($license->getKey());

                if (! $lockedLicense) {
                    throw new RuntimeException('License not found for registration');
                }

                $existingUsage = $this->findByFingerprint($lockedLicense, $fingerprint);

                if ($existingUsage && $existingUsage->isActive()) {
                    if ((string) $existingUsage->license_id === (string) $lockedLicense->id) {
                        $existingUsage->heartbeat();

                        return $existingUsage;
                    }

                    if ($lockedLicense->getUniqueUsageScope() === 'global') {
                        throw new RuntimeException('Fingerprint already in use globally');
                    }
                }

                if (! $this->canRegister($lockedLicense, $fingerprint)) {
                    if ($lockedLicense->getUniqueUsageScope() === 'global') {
                        $existingGlobal = LicenseUsage::forFingerprint($fingerprint)
                            ->active()
                            ->where('license_id', '!=', $lockedLicense->id)
                            ->first();

                        if ($existingGlobal) {
                            throw new RuntimeException('Fingerprint already in use globally');
                        }
                    }

                    $policy = $lockedLicense->getOverLimitPolicy();

                    if ($policy === OverLimitPolicy::Reject) {
                        event(new UsageLimitReached($lockedLicense, $fingerprint, $metadata));

                        $shouldLogLimitReached = true;
                        $auditData = [
                            'license_id' => (string) $lockedLicense->id,
                            'license_class' => $lockedLicense::class,
                            'fingerprint' => $fingerprint,
                        ];

                        throw new RuntimeException('License usage limit reached');
                    }

                    $this->revokeOldestUsage($lockedLicense);
                }

                // Re-activate a previously-revoked seat for the same fingerprint instead of
                // inserting a duplicate: the unique index is (license_id, usage_fingerprint)
                // with no status column, so a fresh insert would collide and a deactivate ->
                // re-activate of the same device would be permanently rejected.
                /** @var LicenseUsage|null $reusable */
                $reusable = $lockedLicense->usages()
                    ->where('usage_fingerprint', $fingerprint)
                    ->first();

                $attributes = [
                    'usage_fingerprint' => $fingerprint,
                    'status' => UsageStatus::Active->value,
                    'registered_at' => now(),
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                    'client_type' => $metadata['client_type'] ?? null,
                    'name' => $metadata['name'] ?? null,
                    'ip' => $this->contextValue('ip', $metadata),
                    'user_agent' => $this->contextValue('user_agent', $metadata),
                    'meta' => $metadata['meta'] ?? null,
                ];

                if ($reusable) {
                    $reusable->forceFill($attributes)->save();
                    $usage = $reusable;
                } else {
                    /** @var LicenseUsage $usage */
                    $usage = $lockedLicense->usages()->create($attributes);
                }

                event(new UsageRegistered($usage));

                return $usage;
            });
        } catch (Exception $e) {
            if ($shouldLogLimitReached && isset($auditData['license_class']) && config('licensing.audit.enabled', true)) {
                LicensingAuditLog::create([
                    'event_type' => AuditEventType::UsageLimitReached,
                    'auditable_type' => $auditData['license_class'],
                    'auditable_id' => $auditData['license_id'],
                    'meta' => [
                        'fingerprint' => $auditData['fingerprint'],
                    ],
                ]);
            }
            throw $e;
        }
    }

    public function heartbeat(LicenseUsage $usage): void
    {
        if (! $usage->isActive()) {
            throw new RuntimeException('Cannot heartbeat revoked usage');
        }

        $usage->heartbeat();
    }

    public function revoke(LicenseUsage $usage, ?string $reason = null): void
    {
        $usage->revoke($reason);
    }

    public function findByFingerprint(License $license, string $fingerprint): ?LicenseUsage
    {
        $scope = $license->getUniqueUsageScope();

        if ($scope === 'global') {
            /** @var LicenseUsage|null */
            return LicenseUsage::forFingerprint($fingerprint)
                ->active()
                ->first();
        }

        /** @var LicenseUsage|null */
        return $license->usages()
            ->where('usage_fingerprint', $fingerprint)
            ->first();
    }

    public function canRegister(License $license, string $fingerprint): bool
    {
        if (! $license->isUsable()) {
            return false;
        }

        $existingUsage = $this->findByFingerprint($license, $fingerprint);

        if ($existingUsage && $existingUsage->isActive()) {
            if ($license->getUniqueUsageScope() === 'global' &&
                (string) $existingUsage->license_id !== (string) $license->id) {
                return false;
            }

            return true;
        }

        return $license->hasAvailableSeats();
    }

    protected function revokeOldestUsage(License $license): void
    {
        /** @var LicenseUsage|null $oldestUsage */
        $oldestUsage = $license->activeUsages()
            ->orderBy('last_seen_at')
            ->first();

        if ($oldestUsage) {
            $oldestUsage->revoke('auto_replaced');
        }
    }

    protected function contextValue(string $key, array $metadata): mixed
    {
        if (array_key_exists($key, $metadata)) {
            return $metadata[$key];
        }

        $request = $this->currentRequest();

        if (! $request instanceof Request) {
            return null;
        }

        return match ($key) {
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            default => null,
        };
    }

    protected function currentRequest(): ?Request
    {
        if (! App::bound('request')) {
            return null;
        }

        return App::make('request');
    }
}
