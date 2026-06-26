<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;

interface TokenIssuer
{
    public function issue(License $license, LicenseUsage $usage, array $options = []): string;

    public function refresh(string $token, array $options = []): string;
}
