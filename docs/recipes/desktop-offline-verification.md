# Verify desktop licenses offline

Complete implementation for desktop software with offline license verification.

## 1. License activation controller

```php
// app/Http/Controllers/Api/DesktopActivationController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Services\UsageRegistrarService;
use Simtabi\Laranail\Licence\Kit\Services\PasetoTokenService;

class DesktopActivationController extends Controller
{
    public function activate(
        Request $request,
        UsageRegistrarService $registrar,
        PasetoTokenService $tokenService
    ) {
        $validated = $request->validate([
            'activation_key' => 'required|string',
            'device_fingerprint' => 'required|string',
            'device_info' => 'required|array',
        ]);
        
        // Find and verify license
        $license = License::findByKey($validated['activation_key']);
        
        if (!$license) {
            return response()->json([
                'error' => 'Invalid activation key',
            ], 404);
        }
        
        if (!$license->verifyKey($validated['activation_key'])) {
            return response()->json([
                'error' => 'Invalid activation key',
            ], 401);
        }
        
        // Activate if pending
        if ($license->status === LicenseStatus::Pending) {
            $license->activate();
        }
        
        // Check if license is usable
        if (!$license->isUsable()) {
            return response()->json([
                'error' => 'License is not active',
                'status' => $license->status->value,
            ], 403);
        }
        
        try {
            // Register device
            $usage = $registrar->register(
                $license,
                $validated['device_fingerprint'],
                [
                    'name' => $validated['device_info']['computer_name'] ?? 'Unknown',
                    'client_type' => 'desktop',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => $validated['device_info'],
                ]
            );
            
            // Generate offline token
            $token = $tokenService->issue($license, $usage, [
                'ttl_days' => 30,
                'force_online_after' => 90,
                'include_entitlements' => true,
            ]);
            
            // Get public key bundle for offline verification
            $publicBundle = $this->getPublicKeyBundle();
            
            return response()->json([
                'success' => true,
                'license' => [
                    'id' => $license->uid,
                    'status' => $license->status->value,
                    'expires_at' => $license->expires_at,
                    'features' => $license->getFeatures(),
                    'entitlements' => $license->getEntitlements(),
                ],
                'token' => $token,
                'public_key_bundle' => $publicBundle,
                'refresh_before' => now()->addDays(25),
            ]);
            
        } catch (UsageLimitReachedException $e) {
            return response()->json([
                'error' => 'Device limit reached',
                'max_devices' => $license->max_usages,
                'active_devices' => $license->activeUsages()->count(),
            ], 403);
        }
    }
    
    public function refresh(Request $request, PasetoTokenService $tokenService)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_fingerprint' => 'required|string',
        ]);
        
        try {
            // Verify current token
            $claims = $tokenService->verify($validated['token']);
            
            // Load license and usage
            $license = License::findByUid($claims['license_id']);
            $usage = $license->usages()
                ->where('usage_fingerprint', $validated['device_fingerprint'])
                ->first();
            
            if (!$usage || !$usage->isActive()) {
                return response()->json([
                    'error' => 'Device not registered',
                ], 403);
            }
            
            // Update heartbeat
            $usage->heartbeat();
            
            // Issue new token
            $newToken = $tokenService->refresh($validated['token'], [
                'ttl_days' => 30,
            ]);
            
            return response()->json([
                'success' => true,
                'token' => $newToken,
                'refresh_before' => now()->addDays(25),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Token refresh failed',
                'message' => $e->getMessage(),
            ], 401);
        }
    }
    
    private function getPublicKeyBundle(): array
    {
        return Cache::remember('public_key_bundle', 3600, function () {
            $ca = app(CertificateAuthorityService::class);
            $signingKey = LicensingKey::findActiveSigning();
            
            return [
                'root_public_key' => $ca->getRootPublicKey(),
                'signing_keys' => [
                    [
                        'kid' => $signingKey->kid,
                        'public_key' => $signingKey->public_key,
                        'certificate' => $signingKey->certificate,
                        'valid_from' => $signingKey->valid_from,
                        'valid_until' => $signingKey->valid_until,
                    ],
                ],
                'issued_at' => now(),
            ];
        });
    }
}
```

## 2. Desktop client (Python example)

```python
# desktop_client/license_manager.py
import json
import hashlib
import platform
import uuid
from datetime import datetime, timedelta
from pathlib import Path
import requests
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import ed25519
import paseto

class DesktopLicenseManager:
    def __init__(self, server_url: str):
        self.server_url = server_url
        self.storage_path = self._get_storage_path()
        self.license_data = None
        self.token = None
        self.public_keys = None
        
    def _get_storage_path(self) -> Path:
        """Get platform-specific secure storage path"""
        if platform.system() == "Windows":
            base = Path.home() / "AppData" / "Local"
        elif platform.system() == "Darwin":  # macOS
            base = Path.home() / "Library" / "Application Support"
        else:  # Linux
            base = Path.home() / ".config"
        
        path = base / "MyApp" / "licensing"
        path.mkdir(parents=True, exist_ok=True)
        return path
    
    def _generate_fingerprint(self) -> str:
        """Generate unique device fingerprint"""
        components = {
            'machine_id': uuid.getnode(),  # MAC address
            'hostname': platform.node(),
            'platform': platform.platform(),
            'processor': platform.processor(),
        }
        
        # Create stable hash
        fingerprint_data = json.dumps(components, sort_keys=True)
        return hashlib.sha256(fingerprint_data.encode()).hexdigest()
    
    def _get_device_info(self) -> dict:
        """Collect device information"""
        return {
            'computer_name': platform.node(),
            'os': platform.system(),
            'os_version': platform.version(),
            'processor': platform.processor(),
            'python_version': platform.python_version(),
        }
    
    def activate(self, activation_key: str) -> bool:
        """Activate license with server"""
        try:
            response = requests.post(
                f"{self.server_url}/api/licensing/v1/activate",
                json={
                    'activation_key': activation_key,
                    'device_fingerprint': self._generate_fingerprint(),
                    'device_info': self._get_device_info(),
                },
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                
                # Save license data
                self.license_data = data['license']
                self.token = data['token']
                self.public_keys = data['public_key_bundle']
                
                # Persist to secure storage
                self._save_license_data()
                
                print(f"License activated successfully!")
                print(f"Expires: {data['license']['expires_at']}")
                return True
            else:
                error = response.json().get('error', 'Unknown error')
                print(f"Activation failed: {error}")
                return False
                
        except requests.RequestException as e:
            print(f"Network error during activation: {e}")
            return False
    
    def validate(self) -> bool:
        """Validate license (offline first, then online)"""
        # Try offline validation first
        if self._validate_offline():
            return True
        
        # If offline validation fails, try online
        return self._validate_online()
    
    def _validate_offline(self) -> bool:
        """Validate license using stored token"""
        if not self.token or not self.public_keys:
            self._load_license_data()
        
        if not self.token:
            return False
        
        try:
            # Verify PASETO token
            verifier = paseto.PasetoV4()
            
            # Get signing public key
            signing_key = self.public_keys['signing_keys'][0]
            public_key = ed25519.Ed25519PublicKey.from_public_bytes(
                bytes.fromhex(signing_key['public_key'])
            )
            
            # Verify token
            payload = verifier.verify(
                self.token,
                public_key,
                implicit_assertion=b''
            )
            
            claims = json.loads(payload)
            
            # Check expiration
            exp = datetime.fromisoformat(claims['exp'])
            if datetime.now() > exp:
                print("License token expired")
                return False
            
            # Check fingerprint
            if claims['usage_fingerprint'] != self._generate_fingerprint():
                print("Device fingerprint mismatch")
                return False
            
            # Check force online date
            if 'force_online_after' in claims:
                force_online = datetime.fromisoformat(claims['force_online_after'])
                if datetime.now() > force_online:
                    print("Online validation required")
                    return self._validate_online()
            
            print("License validated offline successfully")
            return True
            
        except Exception as e:
            print(f"Offline validation failed: {e}")
            return False
    
    def _validate_online(self) -> bool:
        """Validate and refresh license online"""
        if not self.token:
            return False
        
        try:
            response = requests.post(
                f"{self.server_url}/api/licensing/v1/refresh",
                json={
                    'token': self.token,
                    'device_fingerprint': self._generate_fingerprint(),
                },
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                self.token = data['token']
                self._save_license_data()
                print("License validated online and refreshed")
                return True
            else:
                print("Online validation failed")
                return False
                
        except requests.RequestException:
            print("Cannot reach license server")
            return False
    
    def _save_license_data(self):
        """Save license data to secure storage"""
        data = {
            'license': self.license_data,
            'token': self.token,
            'public_keys': self.public_keys,
            'saved_at': datetime.now().isoformat(),
        }
        
        # In production, encrypt this data
        license_file = self.storage_path / "license.json"
        with open(license_file, 'w') as f:
            json.dump(data, f, indent=2)
        
        # Set file permissions (Unix-like systems)
        if platform.system() != "Windows":
            import os
            os.chmod(license_file, 0o600)
    
    def _load_license_data(self):
        """Load license data from storage"""
        license_file = self.storage_path / "license.json"
        
        if license_file.exists():
            try:
                with open(license_file, 'r') as f:
                    data = json.load(f)
                
                self.license_data = data.get('license')
                self.token = data.get('token')
                self.public_keys = data.get('public_keys')
                
            except Exception as e:
                print(f"Failed to load license data: {e}")
    
    def has_feature(self, feature: str) -> bool:
        """Check if license has a specific feature"""
        if not self.license_data:
            self._load_license_data()
        
        if self.license_data and 'features' in self.license_data:
            return self.license_data['features'].get(feature, False)
        
        return False
    
    def get_entitlement(self, key: str, default=None):
        """Get entitlement value"""
        if not self.license_data:
            self._load_license_data()
        
        if self.license_data and 'entitlements' in self.license_data:
            return self.license_data['entitlements'].get(key, default)
        
        return default

# Usage example
if __name__ == "__main__":
    manager = DesktopLicenseManager("https://api.myapp.com")
    
    # First time activation
    if not manager.validate():
        key = input("Enter activation key: ")
        if manager.activate(key):
            print("Activation successful!")
    
    # Regular validation
    if manager.validate():
        print("License is valid")
        
        # Check features
        if manager.has_feature('advanced_analytics'):
            print("Advanced analytics enabled")
        
        # Check entitlements
        api_limit = manager.get_entitlement('api_calls_per_day')
        print(f"API calls limit: {api_limit}")
    else:
        print("License validation failed")
```

---

[← Docs index](../../README.md#documentation)
