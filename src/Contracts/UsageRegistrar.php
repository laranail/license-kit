<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;

interface UsageRegistrar
{
    public function register(
        License $license,
        string $fingerprint,
        array $metadata = []
    ): LicenseUsage;

    public function heartbeat(LicenseUsage $usage): void;

    public function revoke(LicenseUsage $usage, ?string $reason = null): void;

    public function findByFingerprint(License $license, string $fingerprint): ?LicenseUsage;

    public function canRegister(License $license, string $fingerprint): bool;
}
