<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Observers;

use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Enums\KeyType;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;

class LicensingKeyObserver
{
    public function created(LicensingKey $key): void
    {
        if (! config('licensing.audit.enabled', true)) {
            return;
        }

        $eventType = $key->type === KeyType::Root
            ? AuditEventType::KeyRootGenerated
            : AuditEventType::KeySigningIssued;

        LicensingAuditLog::create([
            'event_type' => $eventType,
            'auditable_type' => $key::class,
            'auditable_id' => $key->id,
            'meta' => [
                'kid' => $key->kid,
                'type' => $key->type->value,
                'algorithm' => $key->algorithm,
            ],
        ]);
    }

    public function updated(LicensingKey $key): void
    {
        if (! config('licensing.audit.enabled', true)) {
            return;
        }

        // Check for revocation
        if ($key->wasChanged('status') && $key->status->value === 'revoked') {
            LicensingAuditLog::create([
                'event_type' => AuditEventType::KeyRevoked,
                'auditable_type' => $key::class,
                'auditable_id' => $key->id,
                'meta' => [
                    'kid' => $key->kid,
                    'reason' => $key->revocation_reason,
                ],
            ]);
        }
    }
}
