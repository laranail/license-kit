<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Enums;

enum OverLimitPolicy: string
{
    case Reject = 'reject';
    case AutoReplaceOldest = 'auto_replace_oldest';
}
