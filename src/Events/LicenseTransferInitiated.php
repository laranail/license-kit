<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTransfer;

class LicenseTransferInitiated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LicenseTransfer $transfer
    ) {}
}
