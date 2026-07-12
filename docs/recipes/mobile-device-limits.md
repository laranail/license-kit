# Enforce device limits in a mobile app

Implementation for mobile apps with device registration limits.

## 1. Device management service

```php
// app/Services/MobileDeviceService.php
namespace App\Services;

use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseUsage;
use Simtabi\Laranail\Licence\Kit\Services\UsageRegistrarService;
use Illuminate\Support\Collection;

class MobileDeviceService
{
    public function __construct(
        private UsageRegistrarService $registrar
    ) {}
    
    public function registerDevice(
        License $license,
        string $deviceId,
        array $deviceInfo
    ): LicenseUsage {
        // Check if device already registered
        $existing = $license->usages()
            ->where('usage_fingerprint', $deviceId)
            ->first();
        
        if ($existing) {
            if ($existing->isActive()) {
                // Update heartbeat
                $existing->heartbeat();
                return $existing;
            } else {
                // Reactivate if was revoked
                $existing->update(['status' => UsageStatus::Active]);
                return $existing;
            }
        }
        
        // Check device limit
        if (!$license->hasAvailableSeats()) {
            // Get least recently used device
            $lru = $license->activeUsages()
                ->orderBy('last_seen_at', 'asc')
                ->first();
            
            if ($license->getOverLimitPolicy() === OverLimitPolicy::AutoReplaceOldest) {
                // Auto-revoke oldest device
                $this->registrar->revoke($lru, 'Auto-replaced by new device');
            } else {
                throw new DeviceLimitException(
                    "Device limit reached. Please remove a device first.",
                    $license->max_usages,
                    $this->getDeviceList($license)
                );
            }
        }
        
        // Register new device
        return $this->registrar->register(
            $license,
            $deviceId,
            [
                'name' => $deviceInfo['device_name'] ?? 'Mobile Device',
                'client_type' => 'mobile',
                'meta' => [
                    'platform' => $deviceInfo['platform'], // ios/android
                    'os_version' => $deviceInfo['os_version'],
                    'app_version' => $deviceInfo['app_version'],
                    'model' => $deviceInfo['model'],
                ],
            ]
        );
    }
    
    public function getDeviceList(License $license): Collection
    {
        return $license->activeUsages()
            ->get()
            ->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'name' => $usage->name,
                    'platform' => $usage->meta['platform'] ?? 'unknown',
                    'last_seen' => $usage->last_seen_at,
                    'registered' => $usage->registered_at,
                    'is_current' => request()->header('X-Device-ID') === $usage->usage_fingerprint,
                ];
            });
    }
    
    public function removeDevice(License $license, string $usageId): bool
    {
        $usage = $license->usages()->find($usageId);
        
        if (!$usage) {
            return false;
        }
        
        $this->registrar->revoke($usage, 'Removed by user');
        
        return true;
    }
}
```

## 2. Mobile API controller

```php
// app/Http/Controllers/Api/MobileController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\MobileDeviceService;
use Simtabi\Laranail\Licence\Kit\Models\License;

class MobileController extends Controller
{
    public function __construct(
        private MobileDeviceService $deviceService
    ) {}
    
    public function activate(Request $request)
    {
        $validated = $request->validate([
            'activation_key' => 'required|string',
            'device_id' => 'required|string',
            'device_info' => 'required|array',
        ]);
        
        $license = License::findByKey($validated['activation_key']);
        
        if (!$license || !$license->verifyKey($validated['activation_key'])) {
            return response()->json([
                'error' => 'Invalid activation key',
            ], 401);
        }
        
        try {
            $usage = $this->deviceService->registerDevice(
                $license,
                $validated['device_id'],
                $validated['device_info']
            );
            
            return response()->json([
                'success' => true,
                'license' => [
                    'id' => $license->uid,
                    'expires_at' => $license->expires_at,
                    'features' => $license->getFeatures(),
                ],
                'device' => [
                    'id' => $usage->id,
                    'name' => $usage->name,
                ],
                'devices_remaining' => $license->getAvailableSeats(),
            ]);
            
        } catch (DeviceLimitException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'device_limit' => $e->limit,
                'registered_devices' => $e->devices,
            ], 403);
        }
    }
    
    public function listDevices(Request $request)
    {
        $license = $request->user()->license;
        
        return response()->json([
            'devices' => $this->deviceService->getDeviceList($license),
            'limit' => $license->max_usages,
            'remaining' => $license->getAvailableSeats(),
        ]);
    }
    
    public function removeDevice(Request $request, string $deviceId)
    {
        $license = $request->user()->license;
        
        if ($this->deviceService->removeDevice($license, $deviceId)) {
            return response()->json([
                'success' => true,
                'message' => 'Device removed successfully',
            ]);
        }
        
        return response()->json([
            'error' => 'Device not found',
        ], 404);
    }
}
```

## 3. iOS client (Swift)

```swift
// LicenseManager.swift
import Foundation
import CryptoKit
import UIKit

class LicenseManager {
    static let shared = LicenseManager()
    
    private let serverURL = "https://api.myapp.com"
    private let keychain = KeychainService()
    
    private var license: License?
    private var deviceId: String {
        return UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString
    }
    
    struct License: Codable {
        let id: String
        let expiresAt: Date
        let features: [String: Bool]
    }
    
    // MARK: - Activation
    
    func activate(with key: String, completion: @escaping (Result<License, Error>) -> Void) {
        let deviceInfo = [
            "device_name": UIDevice.current.name,
            "platform": "ios",
            "os_version": UIDevice.current.systemVersion,
            "app_version": Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "",
            "model": UIDevice.current.model
        ]
        
        let payload = [
            "activation_key": key,
            "device_id": deviceId,
            "device_info": deviceInfo
        ] as [String: Any]
        
        APIClient.shared.post("/api/licensing/v1/activate", body: payload) { result in
            switch result {
            case .success(let data):
                if let license = try? JSONDecoder().decode(License.self, from: data) {
                    self.license = license
                    self.saveLicense(license)
                    completion(.success(license))
                }
            case .failure(let error):
                completion(.failure(error))
            }
        }
    }
    
    // MARK: - Validation
    
    func validateLicense(completion: @escaping (Bool) -> Void) {
        // Check cached license first
        if let license = loadLicense() {
            if license.expiresAt > Date() {
                self.license = license
                completion(true)
                return
            }
        }
        
        // Validate with server
        APIClient.shared.post("/api/licensing/v1/validate", 
                              headers: ["X-Device-ID": deviceId]) { result in
            switch result {
            case .success(_):
                completion(true)
            case .failure(_):
                completion(false)
            }
        }
    }
    
    // MARK: - Feature Checking
    
    func hasFeature(_ feature: String) -> Bool {
        return license?.features[feature] ?? false
    }
    
    func requireFeature(_ feature: String, in viewController: UIViewController) -> Bool {
        if hasFeature(feature) {
            return true
        }
        
        // Show upgrade prompt
        let alert = UIAlertController(
            title: "Premium Feature",
            message: "This feature requires a premium license.",
            preferredStyle: .alert
        )
        
        alert.addAction(UIAlertAction(title: "Upgrade", style: .default) { _ in
            self.showUpgradeScreen(in: viewController)
        })
        
        alert.addAction(UIAlertAction(title: "Cancel", style: .cancel))
        
        viewController.present(alert, animated: true)
        return false
    }
    
    // MARK: - Device Management
    
    // Device listing and removal must be implemented as a custom application
    // route — the package exposes /deactivate but no built-in device listing
    // endpoint. Wire it up against your own controller backed by the
    // LicenseUsage model.
    
    // MARK: - Storage
    
    private func saveLicense(_ license: License) {
        if let data = try? JSONEncoder().encode(license) {
            keychain.save(data, for: "license")
        }
    }
    
    private func loadLicense() -> License? {
        guard let data = keychain.load(for: "license"),
              let license = try? JSONDecoder().decode(License.self, from: data) else {
            return nil
        }
        return license
    }
}
```

---

[← Docs index](../../README.md#documentation)
