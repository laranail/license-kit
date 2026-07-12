<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Models\License;

/**
 * Administer a single license from the CLI: inspect it or change its lifecycle
 * state. Uses plain Illuminate prompts/tables (it needs none of laranail/console's
 * richer services, though the kit now extends that command base).
 *
 * Note: the kit has no distinct "revoked" status — `revoke` is a synonym for
 * `cancel` (terminal Cancelled state).
 */
class LicenseCommand extends Command
{
    protected $signature = 'laranail::license-kit.license
                            {action : show|suspend|cancel|revoke|reinstate}
                            {key : The license key or uid}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Administer a license: show status or change its lifecycle state';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:license'];

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $value = (string) $this->argument('key');

        $license = $this->resolveLicense($value);

        if (! $license instanceof License) {
            $this->error(__('license-kit::license-kit.license.not_found', ['value' => $value]));

            return self::FAILURE;
        }

        return match ($action) {
            'show' => $this->show($license),
            'suspend' => $this->transition($license, 'suspend', 'Suspend'),
            'cancel', 'revoke' => $this->transition($license, 'cancel', 'Cancel/revoke'),
            'reinstate' => $this->reinstate($license),
            default => $this->invalid($action),
        };
    }

    private function resolveLicense(string $value): ?License
    {
        /** @var class-string<License> $class */
        $class = config('licensing.models.license', License::class);

        return $class::findByKey($value) ?? $class::findByUid($value);
    }

    private function show(License $license): int
    {
        $this->table(['Property', 'Value'], [
            ['UID', (string) $license->uid],
            ['Status', $license->status->value],
            ['Activated', $license->activated_at?->toDateTimeString() ?? '—'],
            ['Expires', $license->expires_at?->toDateTimeString() ?? 'never'],
            ['Max usages', (string) ($license->max_usages ?? '—')],
            ['Available seats', (string) $license->getAvailableSeats()],
        ]);

        return self::SUCCESS;
    }

    private function transition(License $license, string $method, string $label): int
    {
        if (! $this->confirmed("{$label} license {$license->uid}?")) {
            return self::SUCCESS;
        }

        $license->{$method}();
        $this->info(__('license-kit::license-kit.license.transitioned', ['uid' => $license->uid, 'status' => $license->status->value]));

        return self::SUCCESS;
    }

    private function reinstate(License $license): int
    {
        if (! $this->confirmed("Reinstate license {$license->uid} to active?")) {
            return self::SUCCESS;
        }

        $license->update(['status' => LicenseStatus::Active]);
        $this->info(__('license-kit::license-kit.license.reinstated', ['uid' => $license->uid]));

        return self::SUCCESS;
    }

    private function confirmed(string $question): bool
    {
        if ((bool) $this->option('force') || $this->confirm($question, false)) {
            return true;
        }

        $this->info(__('license-kit::license-kit.license.aborted'));

        return false;
    }

    private function invalid(string $action): int
    {
        $this->error(__('license-kit::license-kit.license.unknown_action', ['action' => $action]));

        return self::FAILURE;
    }
}
