<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Enums;

enum UsageStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
