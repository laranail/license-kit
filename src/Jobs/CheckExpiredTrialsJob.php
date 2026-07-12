<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Licence\Kit\Services\TrialService;

class CheckExpiredTrialsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TrialService $trialService): void
    {
        $expiredCount = $trialService->checkExpiredTrials();

        if ($expiredCount > 0) {
            info("Expired {$expiredCount} trials");
        }
    }
}
