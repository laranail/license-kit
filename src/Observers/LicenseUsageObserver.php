<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Observers;

use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;

class LicenseUsageObserver
{
    public function created(LicenseUsage $usage): void
    {
        if (! config('licensing.audit.enabled', true)) {
            return;
        }

        LicensingAuditLog::create([
            'event_type' => AuditEventType::UsageRegistered,
            'auditable_type' => $usage::class,
            'auditable_id' => $usage->id,
            'meta' => [
                'license_id' => $usage->license_id,
                'fingerprint' => $usage->usage_fingerprint,
                'client_type' => $usage->client_type,
            ],
        ]);
    }

    public function updated(LicenseUsage $usage): void
    {
        if (! config('licensing.audit.enabled', true)) {
            return;
        }

        // Check for revocation
        if ($usage->wasChanged('status') && $usage->status === UsageStatus::Revoked) {
            // Get the reason from meta
            $reason = $usage->meta['revocation_reason'] ?? null;

            LicensingAuditLog::create([
                'event_type' => AuditEventType::UsageRevoked,
                'auditable_type' => $usage::class,
                'auditable_id' => $usage->id,
                'meta' => [
                    'reason' => $reason,
                ],
            ]);
        }
    }
}
