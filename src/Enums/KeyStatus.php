<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Enums;

enum KeyStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
