# Set up tiered SaaS licensing

Complete implementation for a SaaS with Basic, Pro, and Enterprise tiers.

## 1. Database setup

```php
// database/migrations/create_license_templates.php
Schema::create('license_templates', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignId('license_scope_id')->nullable()->constrained('license_scopes')->nullOnDelete();
    $table->string('name');
    $table->string('slug')->unique();
    $table->integer('tier_level');
    $table->json('base_configuration');
    $table->json('features');
    $table->json('entitlements');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

## 2. Create license tiers

```php
// database/seeders/LicenseTierSeeder.php
use Simtabi\Laranail\Licence\Kit\Models\LicenseTemplate;
use Simtabi\Laranail\Licence\Kit\Models\LicenseScope;

class LicenseTierSeeder extends Seeder
{
    public function run()
    {
        $scope = LicenseScope::firstOrCreate(
            ['slug' => 'saas-app'],
            ['name' => 'SaaS App']
        );

        // Basic Tier
        LicenseTemplate::create([
            'license_scope_id' => $scope->id,
            'name' => 'Basic Plan',
            'slug' => 'basic-monthly',
            'tier_level' => 1,
            'base_configuration' => [
                'max_usages' => 2,
                'validity_days' => 30,
                'grace_days' => 7,
            ],
            'features' => [
                'basic_reports' => true,
                'email_support' => true,
            ],
            'entitlements' => [
                'api_calls_per_day' => 1000,
                'storage_gb' => 10,
                'team_members' => 3,
            ],
        ]);

        // Pro Tier
        LicenseTemplate::create([
            'license_scope_id' => $scope->id,
            'name' => 'Professional Plan',
            'slug' => 'pro-monthly',
            'tier_level' => 2,
            'base_configuration' => [
                'max_usages' => 5,
                'validity_days' => 30,
                'grace_days' => 14,
            ],
            'features' => [
                'basic_reports' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'priority_support' => true,
                'custom_branding' => true,
            ],
            'entitlements' => [
                'api_calls_per_day' => 10000,
                'storage_gb' => 100,
                'team_members' => 10,
            ],
        ]);

        // Enterprise Tier
        LicenseTemplate::create([
            'license_scope_id' => $scope->id,
            'name' => 'Enterprise Plan',
            'slug' => 'enterprise-annual',
            'tier_level' => 3,
            'base_configuration' => [
                'max_usages' => -1, // Unlimited
                'validity_days' => 365,
                'grace_days' => 30,
            ],
            'features' => [
                'basic_reports' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'priority_support' => true,
                'custom_branding' => true,
                'white_label' => true,
                'sso_integration' => true,
                'audit_logs' => true,
                'dedicated_support' => true,
            ],
            'entitlements' => [
                'api_calls_per_day' => -1, // Unlimited
                'storage_gb' => 1000,
                'team_members' => -1, // Unlimited
            ],
        ]);
    }
}
```

## 3. License creation service

```php
// app/Services/SaaSLicensingService.php
namespace App\Services;

use Simtabi\Laranail\Licence\Kit\Models\{License, LicenseScope, LicenseTemplate};
use Simtabi\Laranail\Licence\Kit\Services\TemplateService;
use App\Models\Organization;
use Illuminate\Support\Str;

class SaaSLicensingService
{
    public function __construct(private TemplateService $templates) {}

    public function createLicenseForOrganization(
        Organization $org,
        LicenseScope $scope,
        string|LicenseTemplate $plan,
        array $options = []
    ): License {
        $activationKey = $this->generateActivationKey();

        $license = $this->templates->createLicenseForScope($scope, $plan, [
            'key_hash' => License::hashKey($activationKey),
            'licensable_type' => Organization::class,
            'licensable_id' => $org->id,
            'meta' => array_merge([
                'organization_name' => $org->name,
                'billing_email' => $org->billing_email,
                'created_by' => auth()->id(),
            ], $options),
        ]);

        $org->update([
            'activation_key' => encrypt($activationKey),
        ]);

        Mail::to($org->billing_email)->send(
            new LicenseCreatedMail($license, $activationKey)
        );

        return $license;
    }

    private function generateActivationKey(): string
    {
        return implode('-', str_split(
            strtoupper(Str::random(20)),
            4
        ));
    }

    public function upgradeLicense(License $license, LicenseTemplate $newTemplate): License
    {
        $currentTemplate = $license->template;

        if (!$newTemplate->isHigherTierThan($currentTemplate)) {
            throw new \RuntimeException('Can only upgrade to higher tier');
        }

        $upgraded = $this->templates->upgradeLicense($license, $newTemplate);

        if ($newTemplate->base_configuration['validity_days'] ?? null === 365) {
            $upgraded->renew(now()->addYear());
        }

        event(new LicenseUpgraded($upgraded, $currentTemplate, $newTemplate));

        return $upgraded;
    }
}
```

## 4. Middleware for feature checking

```php
// app/Http/Middleware/RequiresFeature.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequiresFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $license = $request->user()->organization->license;
        
        if (!$license || !$license->isUsable()) {
            return redirect()->route('billing.expired');
        }
        
        if (!$license->hasFeature($feature)) {
            return redirect()
                ->route('billing.upgrade')
                ->with('error', "This feature requires an upgrade to access.");
        }
        
        return $next($request);
    }
}

// Usage in routes
Route::middleware(['auth', 'requires-feature:advanced_analytics'])
    ->group(function () {
        Route::get('/analytics/advanced', [AnalyticsController::class, 'advanced']);
    });
```

## 5. Usage quota enforcement

```php
// app/Services/QuotaService.php
namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Cache;

class QuotaService
{
    public function checkApiQuota(Organization $org): bool
    {
        $license = $org->license;
        
        if (!$license || !$license->isUsable()) {
            return false;
        }
        
        $limit = $license->getEntitlement('api_calls_per_day');
        
        // Unlimited
        if ($limit === -1) {
            return true;
        }
        
        $key = "api_quota:{$org->id}:" . now()->format('Y-m-d');
        $current = Cache::increment($key);
        
        if ($current === 1) {
            Cache::expire($key, 86400); // Expire at end of day
        }
        
        return $current <= $limit;
    }
    
    public function getRemainingQuota(Organization $org): array
    {
        $license = $org->license;
        $limit = $license->getEntitlement('api_calls_per_day');
        
        if ($limit === -1) {
            return ['unlimited' => true];
        }
        
        $key = "api_quota:{$org->id}:" . now()->format('Y-m-d');
        $used = Cache::get($key, 0);
        
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_at' => now()->endOfDay(),
        ];
    }
}
```

---

[← Docs index](../../README.md#documentation)
