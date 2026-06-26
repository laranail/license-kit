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
            $this->line(__('license-kit::license-kit.check_expirations.dry_grace', ['count' => $toGrace->count()]));
            $this->line(__('license-kit::license-kit.check_expirations.dry_expired', ['count' => $toExpired->count()]));

            if ($notify) {
                $this->line(__('license-kit::license-kit.check_expirations.dry_notify', ['count' => $expiringSoon->count(), 'days' => $expiringWithin]));
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

        $this->info(__('license-kit::license-kit.check_expirations.transitioned_grace', ['count' => $toGrace->count()]));
        $this->info(__('license-kit::license-kit.check_expirations.transitioned_expired', ['count' => $toExpired->count()]));

        if ($notify) {
            $this->info(__('license-kit::license-kit.check_expirations.notifications_dispatched', ['count' => $expiringSoon->count()]));
        }

        return 0;
    }
}
