<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;
use Throwable;

class HealthController extends ApiController
{
    public function show(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'root_key' => $this->checkRootKey(),
            'signing_key' => $this->checkSigningKey(),
        ];

        $isHealthy = collect($checks)->every(fn ($result): bool => $result['status'] === 'ok');

        return $this->success([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
        ]);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'error',
            ];
        }
    }

    protected function checkRootKey(): array
    {
        $rootKey = LicensingKey::findActiveRoot();

        if (! $rootKey instanceof LicensingKey) {
            return ['status' => 'error'];
        }

        return ['status' => 'ok'];
    }

    protected function checkSigningKey(): array
    {
        $signingKey = LicensingKey::findActiveSigning();

        if (! $signingKey instanceof LicensingKey) {
            return ['status' => 'error'];
        }

        return ['status' => 'ok'];
    }
}
