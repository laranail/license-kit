<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;

interface AuditLog
{
    public function auditable(): MorphTo;

    public static function record(
        AuditEventType $eventType,
        array $data,
        ?string $actor = null,
        array $context = []
    ): self;
}
