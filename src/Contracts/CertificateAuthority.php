<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use DateTimeInterface;

interface CertificateAuthority
{
    public function issueSigningCertificate(
        string $signingPublicKey,
        string $kid,
        DateTimeInterface $validFrom,
        DateTimeInterface $validUntil
    ): string;

    public function verifyCertificate(string $certificate): bool;

    public function getCertificateChain(string $kid): array;

    public function getRootPublicKey(): string;
}
