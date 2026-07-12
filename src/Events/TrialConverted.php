<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTrial;

class TrialConverted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LicenseTrial $trial,
        public License $license
    ) {}
}
