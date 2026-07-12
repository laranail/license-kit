<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Observers;

use RuntimeException;
use Simtabi\Laranail\Licence\Kit\Models\LicensingAuditLog;

class LicensingAuditLogObserver
{
    /**
     * Prevent updates - audit logs are append-only
     */
    public function updating(LicensingAuditLog $log): void
    {
        throw new RuntimeException('Audit logs are append-only and cannot be updated');
    }

    /**
     * Set previous hash on creation if hash chaining is enabled
     */
    public function creating(LicensingAuditLog $log): void
    {
        if (! config('licensing.audit.hash_chain', true)) {
            return;
        }

        $previous = LicensingAuditLog::latest('id')->first();
        if ($previous) {
            $log->previous_hash = $previous->calculateHash();
        }
    }
}
