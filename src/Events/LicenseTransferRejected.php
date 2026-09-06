<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTransfer;

class LicenseTransferRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LicenseTransfer $transfer,
    ) {}
}
