<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use DateTimeInterface;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;

interface AuditLogger
{
    public function log(
        AuditEventType $eventType,
        array $data,
        ?string $actor = null,
        array $context = []
    ): void;

    public function query(array $filters = []): iterable;

    public function purge(DateTimeInterface $before): int;
}
