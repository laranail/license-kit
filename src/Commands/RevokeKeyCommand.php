<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use DateTimeImmutable;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;

class RevokeKeyCommand extends Command
{
    protected $signature = 'laranail::license-kit.keys.revoke {kid : The key ID to revoke} {--reason=manual : Revocation reason} {--at= : When to revoke (ISO datetime)}';

    protected $description = 'Revoke a signing key';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:keys:revoke'];

    public function handle(): int
    {
        $kid = $this->argument('kid');
        $reason = $this->option('reason');
        $at = $this->option('at');

        $key = LicensingKey::where('kid', $kid)->first();

        if (! $key) {
            $this->line(__('license-kit::license-kit.revoke.not_found', ['kid' => $kid]));

            return 2; // Not found
        }

        if ($key->isRevoked()) {
            $this->line(__('license-kit::license-kit.revoke.already_revoked', ['kid' => $kid]));

            return 0;
        }

        if (! $this->confirm("Are you sure you want to revoke key {$kid}?")) {
            $this->line(__('license-kit::license-kit.revoke.cancelled'));

            return 0;
        }

        $revokedAt = $at ? new DateTimeImmutable($at) : now();
        $key->revoke($reason, $revokedAt);

        $this->line(__('license-kit::license-kit.revoke.revoked'));
        $this->line(__('license-kit::license-kit.revoke.key_id', ['kid' => $kid]));
        $this->line(__('license-kit::license-kit.revoke.reason', ['reason' => $reason]));

        return 0;
    }
}
