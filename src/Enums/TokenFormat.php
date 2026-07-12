<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Enums;

enum TokenFormat: string
{
    case Paseto = 'paseto';
    case Jws = 'jws';
}
