<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Services\CertificateAuthorityService;

class RotateKeysCommand extends Command
{
    protected $signature = 'laranail::license-kit.keys.rotate
        {--reason=routine : Rotation reason (routine|compromised)}
        {--immediate : Immediately revoke old key (for compromised keys)}';

    protected $description = 'Rotate signing keys';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:keys:rotate'];

    public function handle(CertificateAuthorityService $ca): int
    {
        $reason = $this->option('reason');
        $immediate = $this->option('immediate');

        if (! in_array($reason, ['routine', 'compromised'])) {
            $this->line('Invalid reason. Must be "routine" or "compromised".');

            return 1;
        }

        if ($reason === 'compromised' && $immediate) {
            $this->line('SECURITY: Rotating compromised key immediately...');
        }

        $rootKey = LicensingKey::findActiveRoot();
        if (! $rootKey instanceof LicensingKey) {
            $this->line('No active root key found.');

            return 2;
        }

        $this->line('Rotating signing key...');

        $newKid = 'signing-'.bin2hex(random_bytes(16));

        // Revoke-then-issue atomically: if certificate issuance or save fails,
        // the old signing key is NOT left revoked (which would break signing).
        $revokedExisting = DB::transaction(function () use ($ca, $reason, $newKid): bool {
            $currentSigningKey = LicensingKey::findActiveSigning();
            $hadExisting = $currentSigningKey instanceof LicensingKey;

            if ($currentSigningKey instanceof LicensingKey) {
                $currentSigningKey->revoke($reason);
            }

            $newSigningKey = LicensingKey::generateSigningKey($newKid);
            $newSigningKey->valid_from = now();
            $newSigningKey->valid_until = now()->addDays(30);
            $newSigningKey->certificate = $ca->issueSigningCertificate(
                $newSigningKey->getPublicKey(),
                $newSigningKey->kid,
                $newSigningKey->valid_from,
                $newSigningKey->valid_until
            );
            $newSigningKey->save();

            return $hadExisting;
        });

        if ($revokedExisting) {
            $this->line('Current signing key revoked');
        }

        $this->line('New signing key issued');
        $this->line('Key ID: '.$newKid);

        if ($reason === 'compromised') {
            $this->line('All tokens signed with the compromised key are now invalid');
            $this->line('Clients must refresh their tokens immediately');
            $this->line('IMPORTANT: Update all clients immediately with the new public key bundle.');
        }

        return 0;
    }
}
