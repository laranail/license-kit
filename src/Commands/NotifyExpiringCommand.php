<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseExpiringSoon;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Notifications\LicenseExpiringNotification;

class NotifyExpiringCommand extends Command
{
    protected $signature = 'laranail::license-kit.notify-expiring
                            {--dry-run : Report who would be notified without sending}';

    protected $description = 'Notify license owners and configured admins about approaching expiry';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:notify-expiring'];

    public function handle(): int
    {
        if (! (bool) config('licensing.notifications.expiring.enabled', false)) {
            $this->info('Expiring-license notifications are disabled.');

            return 0;
        }

        /** @var list<int|string> $daysBefore */
        $daysBefore = (array) config('licensing.scheduler.notify_expiring.days_before', [30, 14, 7, 3, 1]);
        $maxDays = $daysBefore === [] ? 7 : max(array_map(intval(...), $daysBefore));

        $licenseClass = config('licensing.models.license', License::class);

        $expiring = $licenseClass::query()
            ->whereIn('status', [LicenseStatus::Active, LicenseStatus::Grace])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($maxDays)])
            ->get();

        if ((bool) $this->option('dry-run')) {
            $this->line("[dry-run] would notify for {$expiring->count()} expiring license(s).");

            return 0;
        }

        /** @var list<string> $recipients */
        $recipients = array_values(array_filter((array) config('licensing.notifications.expiring.to', [])));

        $expiring->each(function (License $license) use ($recipients): void {
            $days = max(0, $license->daysUntilExpiration() ?? 0);
            $notification = new LicenseExpiringNotification($license, $days);

            // The owner morph may reference a model class absent in this runtime;
            // resolving it must not abort the whole notification run.
            $owner = rescue(fn (): ?Model => $license->licensable, null, false);

            if ($owner instanceof Model && method_exists($owner, 'notify')) {
                $owner->notify($notification);
            }

            foreach ($recipients as $email) {
                Notification::route('mail', $email)->notify($notification);
            }

            event(new LicenseExpiringSoon($license, $days));
        });

        $this->info("Expiring-license notifications sent for: {$expiring->count()}");

        return 0;
    }
}
