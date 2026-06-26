<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Exception;
use RuntimeException;
use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Services\PasetoTokenService;

class IssueOfflineTokenCommand extends Command
{
    protected $signature = 'laranail::license-kit.offline.issue
        {--license= : License ID or key}
        {--fingerprint= : Usage fingerprint}
        {--ttl=7d : Token TTL}';

    protected $description = 'Issue an offline token for a license';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:offline:issue'];

    public function handle(PasetoTokenService $tokenService): int
    {
        $licenseRef = $this->option('license');
        $fingerprint = $this->option('fingerprint');
        $ttl = $this->option('ttl');

        if (! $licenseRef || ! $fingerprint) {
            $this->line('Both --license and --fingerprint are required.');

            return 1;
        }

        // Find license by ID or key
        $license = is_numeric($licenseRef)
            ? License::find($licenseRef)
            : License::findByKey($licenseRef);

        if (! $license) {
            $this->line("License not found: {$licenseRef}");

            return 2;
        }

        /** @var LicenseUsage|null $usage */
        $usage = $license->usages()
            ->where('usage_fingerprint', $fingerprint)
            ->where('status', UsageStatus::Active->value)
            ->first();

        if (! $usage) {
            $this->line("No active usage found for fingerprint: {$fingerprint}");

            return 2;
        }

        // Check if signing key is available and not revoked
        $signingKey = LicensingKey::findActiveSigning();
        if (! $signingKey instanceof LicensingKey) {
            $this->line('No active signing key available');

            return 3;
        }

        if ($signingKey->isRevoked()) {
            $this->line('Signing key is revoked');

            return 3;
        }

        // Parse TTL
        $ttlDays = $this->parseTtl($ttl);

        try {
            $token = $tokenService->issue($license, $usage, [
                'ttl_days' => $ttlDays,
            ]);

            $this->line('Offline token issued successfully');
            $this->line('Token:');
            $this->line($token);

            return 0;
        } catch (RuntimeException $e) {
            // Crypto/key errors return code 3
            $this->line('Failed to issue token: '.$e->getMessage());

            return 3;
        } catch (Exception $e) {
            // Other errors
            $this->line('Failed to issue token: '.$e->getMessage());

            return 4;
        }
    }

    private function parseTtl(string $ttl): int
    {
        if (preg_match('/^(\d+)d$/', $ttl, $matches)) {
            return (int) $matches[1];
        }

        return 7; // Default to 7 days
    }
}
