<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use DateTimeImmutable;
use Exception;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Enums\KeyType;
use Simtabi\Laranail\Licence\Kit\Models\LicenseScope;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Simtabi\Laranail\Licence\Kit\Services\AuditLoggerService;
use Simtabi\Laranail\Licence\Kit\Services\CertificateAuthorityService;

class IssueSigningKeyCommand extends Command
{
    protected $signature = 'laranail::license-kit.keys.issue-signing '
        .'{--kid= : Key ID for the new signing key} '
        .'{--scope= : Scope slug or identifier for the signing key} '
        .'{--days= : Validity window in days} '
        .'{--nbf= : Not before date (ISO format)} '
        .'{--exp= : Expiration date (ISO format)}';

    protected $description = 'Issue a new signing key signed by the active root key';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:keys:issue-signing'];

    public function handle(
        AuditLoggerService $auditLogger,
        CertificateAuthorityService $ca
    ): int {
        $rootKey = LicensingKey::findActiveRoot();

        if (! $rootKey instanceof LicensingKey) {
            $this->line(__('license-kit::license-kit.issue_signing.no_root'));

            return self::FAILURE;
        }

        $kid = $this->option('kid') ?? 'signing-'.bin2hex(random_bytes(16));

        $licenseScope = null;
        if ($scopeOption = $this->option('scope')) {
            $licenseScope = LicenseScope::findBySlugOrIdentifier($scopeOption);

            if (! $licenseScope instanceof LicenseScope) {
                $this->line(__('license-kit::license-kit.issue_signing.scope_not_found', ['scope' => $scopeOption]));
                $this->line(__('license-kit::license-kit.issue_signing.available_scopes'));
                LicenseScope::active()->each(function ($scope): void {
                    $this->line(__('license-kit::license-kit.issue_signing.scope_line', ['slug' => $scope->slug, 'name' => $scope->name]));
                });

                return 2;
            }
        }

        $validFrom = $this->option('nbf')
            ? new DateTimeImmutable($this->option('nbf'))
            : new DateTimeImmutable;

        $validUntil = null;
        $validForDays = 30;

        if ($this->option('days') !== null) {
            $daysOption = $this->option('days');

            if (! is_numeric($daysOption) || (int) $daysOption <= 0) {
                $this->line(__('license-kit::license-kit.issue_signing.days_positive'));

                return self::FAILURE;
            }

            $validForDays = (int) $daysOption;
            $validUntil = $validFrom->modify("+{$validForDays} days");
        } elseif ($this->option('exp')) {
            $validUntil = new DateTimeImmutable($this->option('exp'));
            $validForDays = max(1, $validUntil->diff($validFrom)->days);
        } else {
            $validUntil = $validFrom->modify('+30 days');
        }

        $this->line(__('license-kit::license-kit.issue_signing.generating'));
        if ($this->output->isVerbose()) {
            $this->line(__('license-kit::license-kit.issue_signing.generating_rsa'));
        }

        try {
            $signingKey = new LicensingKey;
            $signingKey->generate([
                'type' => KeyType::Signing,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ]);

            $signingKey->kid = $kid;

            if ($licenseScope instanceof LicenseScope) {
                $signingKey->license_scope_id = $licenseScope->id;
            }

            $signingKey->save();

            if ($this->output->isVerbose()) {
                $this->line(__('license-kit::license-kit.issue_signing.creating_cert'));
                $this->line(__('license-kit::license-kit.issue_signing.signing_cert'));
            }

            $certificate = $ca->issueSigningCertificate(
                $signingKey->getPublicKey(),
                $kid,
                $validFrom,
                $validUntil,
                $licenseScope
            );

            if ($this->output->isVerbose()) {
                $this->line(__('license-kit::license-kit.issue_signing.storing'));
            }

            $signingKey->update(['certificate' => $certificate]);

            $auditLogger->log(
                AuditEventType::KeySigningIssued,
                [
                    'kid' => $kid,
                    'scope_id' => $licenseScope?->id,
                    'scope_name' => $licenseScope?->name,
                    'valid_from' => $validFrom->format('c'),
                    'valid_until' => $validUntil->format('c'),
                ],
                'console'
            );

            $this->line(__('license-kit::license-kit.issue_signing.issued'));
            $this->line(__('license-kit::license-kit.issue_signing.key_id', ['kid' => $kid]));
            if ($licenseScope instanceof LicenseScope) {
                $this->line(__('license-kit::license-kit.issue_signing.scope', ['name' => $licenseScope->name, 'slug' => $licenseScope->slug]));
            } else {
                $this->line(__('license-kit::license-kit.issue_signing.scope_global'));
            }
            $this->line(__('license-kit::license-kit.issue_signing.valid_for', ['days' => $validForDays]));

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->line(__('license-kit::license-kit.issue_signing.failed', ['error' => $e->getMessage()]));

            return 3;
        }
    }
}
