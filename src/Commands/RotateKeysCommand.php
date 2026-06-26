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
            $this->line(__('license-kit::license-kit.rotate.invalid_reason'));

            return 1;
        }

        if ($reason === 'compromised' && $immediate) {
            $this->line(__('license-kit::license-kit.rotate.security_immediate'));
        }

        $rootKey = LicensingKey::findActiveRoot();
        if (! $rootKey instanceof LicensingKey) {
            $this->line(__('license-kit::license-kit.rotate.no_root'));

            return 2;
        }

        $this->line(__('license-kit::license-kit.rotate.rotating'));

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
            $this->line(__('license-kit::license-kit.rotate.revoked'));
        }

        $this->line(__('license-kit::license-kit.rotate.issued'));
        $this->line(__('license-kit::license-kit.rotate.key_id', ['kid' => $newKid]));

        if ($reason === 'compromised') {
            $this->line(__('license-kit::license-kit.rotate.compromised_invalid'));
            $this->line(__('license-kit::license-kit.rotate.refresh_clients'));
            $this->line(__('license-kit::license-kit.rotate.update_clients'));
        }

        return 0;
    }
}
