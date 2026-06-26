<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;

class UsageRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LicenseUsage $usage,
        public ?string $reason = null
    ) {}
}
