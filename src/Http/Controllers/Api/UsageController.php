<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Licence\Kit\Contracts\UsageRegistrar;
use Simtabi\Laranail\Licence\Kit\LicenceKit;
use Simtabi\Laranail\Licence\Kit\Models\License;

class UsageController extends ApiController
{
    public function __construct(
        protected LicenceKit $licensing,
        protected UsageRegistrar $usageRegistrar
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $payload = $this->validate($request, [
            'license_key' => ['required', 'string'],
            'fingerprint' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'array'],
        ]);

        $license = $this->findLicense($payload['license_key']);
        if (! $license instanceof License) {
            return $this->error('INVALID_KEY', 'License key is invalid or not found', 404);
        }

        $usage = $this->usageRegistrar->findByFingerprint($license, $payload['fingerprint']);
        if (! $usage || ! $usage->isActive()) {
            return $this->error('FINGERPRINT_MISMATCH', 'Fingerprint does not match an active usage for this license', 403);
        }

        $this->licensing->heartbeat($usage);

        if (! empty($payload['data'])) {
            $currentMeta = (array) ($usage->meta ?? []);
            $currentMeta['client_data'] = $payload['data'];
            $usage->fill(['meta' => $currentMeta]);
            $usage->save();
        }

        $usage->refresh();

        return $this->success([
            'usage' => [
                'id' => $usage->getKey(),
                'fingerprint' => $usage->usage_fingerprint,
                'last_seen_at' => $usage->last_seen_at?->toIso8601String(),
                'meta' => $usage->meta,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $payload = $this->validate($request, [
            'license_key' => ['required', 'string'],
            'fingerprint' => ['required', 'string', 'max:255'],
        ]);

        $license = $this->findLicense($payload['license_key']);
        if (! $license instanceof License) {
            return $this->error('INVALID_KEY', 'License key is invalid or not found', 404);
        }

        $caller = $this->usageRegistrar->findByFingerprint($license, $payload['fingerprint']);
        if (! $caller || ! $caller->isActive()) {
            return $this->error('FINGERPRINT_MISMATCH', 'Fingerprint does not match an active usage for this license', 403);
        }

        $usages = $license->usages()->get()->map(fn ($usage): array => [
            'id' => $usage->getKey(),
            'fingerprint' => $usage->usage_fingerprint,
            'last_seen_at' => $usage->last_seen_at?->toIso8601String(),
            'status' => $usage->isActive() ? 'active' : 'revoked',
        ])->all();

        return $this->success(['usages' => $usages, 'total' => count($usages)]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $payload = $this->validate($request, [
            'license_key' => ['required', 'string'],
            'fingerprint' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string'],
        ]);

        $license = $this->findLicense($payload['license_key']);
        if (! $license instanceof License) {
            return $this->error('INVALID_KEY', 'License key is invalid or not found', 404);
        }

        $caller = $this->usageRegistrar->findByFingerprint($license, $payload['fingerprint']);
        if (! $caller || ! $caller->isActive()) {
            return $this->error('FINGERPRINT_MISMATCH', 'Fingerprint does not match an active usage for this license', 403);
        }

        // The revoke target may be referenced by device fingerprint or usage id.
        $target = $this->usageRegistrar->findByFingerprint($license, $payload['target'])
            ?? $license->usages()->whereKey($payload['target'])->first();

        if (! $target) {
            return $this->error('USAGE_NOT_FOUND', 'No matching seat to revoke', 404);
        }

        $this->usageRegistrar->revoke($target, 'Revoked via API');

        return $this->success(['revoked' => true, 'id' => $target->getKey()]);
    }

    protected function validate(Request $request, array $rules): array
    {
        try {
            return $request->validate($rules);
        } catch (ValidationException $exception) {
            $response = $this->error('VALIDATION_FAILED', 'Request payload is invalid', 422, [
                'details' => $exception->errors(),
            ]);

            throw new ValidationException($exception->validator, $response);
        }
    }

    protected function findLicense(string $licenseKey): ?License
    {
        return $this->licensing->findByKey($licenseKey);
    }
}
