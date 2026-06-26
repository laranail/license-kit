<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseExpiringSoon;
use Simtabi\Laranail\Licence\Kit\Models\License;

class CheckExpirationsCommand extends Command
{
    protected $signature = 'laranail::license-kit.check-expirations
                            {--dry-run : Report transitions without applying them}
                            {--notify : Dispatch LicenseExpiringSoon for licenses near expiration}
                            {--expiring-within=7 : Days threshold for expiring-soon notifications}';

    protected $description = 'Transition licenses across grace and expired states based on time';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:check-expirations'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $notify = (bool) $this->option('notify');
        $expiringWithin = (int) $this->option('expiring-within');

        $licenseClass = config('licensing.models.license', License::class);

        $toGrace = $licenseClass::query()
            ->where('status', LicenseStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $toExpired = $licenseClass::query()
            ->where('status', LicenseStatus::Grace)
            ->whereNotNull('expires_at')
            ->get()
            ->filter(fn (License $license): bool => $license->gracePeriodExpired());

        $expiringSoon = $notify
            ? $licenseClass::query()
                ->where('status', LicenseStatus::Active)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays($expiringWithin)])
                ->get()
            : collect();

        if ($dryRun) {
            $this->line("[dry-run] would transition {$toGrace->count()} active licenses to grace.");
            $this->line("[dry-run] would transition {$toExpired->count()} grace licenses to expired.");

            if ($notify) {
                $this->line("[dry-run] would notify {$expiringSoon->count()} licenses expiring within {$expiringWithin} days.");
            }

            return 0;
        }

        $toGrace->each->transitionToGrace();
        $toExpired->each->transitionToExpired();

        if ($notify) {
            $expiringSoon->each(function (License $license): void {
                event(new LicenseExpiringSoon($license, max(0, $license->daysUntilExpiration() ?? 0)));
            });
        }

        $this->info("Transitioned to grace: {$toGrace->count()}");
        $this->info("Transitioned to expired: {$toExpired->count()}");

        if ($notify) {
            $this->info("Expiring-soon notifications dispatched: {$expiringSoon->count()}");
        }

        return 0;
    }
}
