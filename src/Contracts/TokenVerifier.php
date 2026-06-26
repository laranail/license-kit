<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Contracts;

interface TokenVerifier
{
    public function verify(string $token, array $options = []): array;

    public function verifyOffline(string $token, string $publicKeyBundle): array;

    public function extractClaims(string $token): array;
}
