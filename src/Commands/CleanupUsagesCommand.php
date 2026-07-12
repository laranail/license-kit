<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;

class CleanupUsagesCommand extends Command
{
    protected $signature = 'laranail::license-kit.cleanup-usages
                            {--dry-run : Report revocations without applying them}';

    protected $description = 'Revoke license usages inactive beyond the configured threshold';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:cleanup-usages'];

    public function handle(): int
    {
        $days = config('licensing.policies.usage_inactivity_auto_revoke_days');

        if ($days === null) {
            $this->comment(__('license-kit::license-kit.cleanup.disabled'));

            return 0;
        }

        $usageClass = config('licensing.models.license_usage', LicenseUsage::class);

        $usages = $usageClass::query()
            ->where('status', UsageStatus::Active)
            ->where('last_seen_at', '<', now()->subDays((int) $days))
            ->get();

        if ($this->option('dry-run')) {
            $this->line(__('license-kit::license-kit.cleanup.dry', ['count' => $usages->count(), 'days' => $days]));

            return 0;
        }

        $usages->each(fn (LicenseUsage $usage): LicenseUsage => $usage->revoke('inactivity'));

        $this->info(__('license-kit::license-kit.cleanup.revoked', ['count' => $usages->count()]));

        return 0;
    }
}
