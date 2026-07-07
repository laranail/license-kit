# Convert trials to paid licenses

Complete implementation of trial license with conversion tracking.

## 1. Trial registration

```php
// app/Http/Controllers/TrialController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\Licence\Kit\Services\TrialService;
use Simtabi\Laranail\Licence\Kit\Models\License;
use App\Models\User;

class TrialController extends Controller
{
    public function __construct(
        private TrialService $trialService
    ) {}
    
    public function startTrial(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'name' => 'required|string',
            'company' => 'nullable|string',
        ]);
        
        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company' => $validated['company'],
        ]);
        
        // Create trial license
        $license = License::create([
            'key_hash' => License::hashKey(Str::random(32)),
            'licensable_type' => User::class,
            'licensable_id' => $user->id,
            'status' => LicenseStatus::Active,
            'expires_at' => now()->addDays(14),
            'max_usages' => 1,
            'meta' => [
                'is_trial' => true,
                'trial_type' => 'standard',
            ],
        ]);
        
        // Generate device fingerprint
        $fingerprint = hash('sha256', $request->ip() . $request->userAgent());
        
        // Start trial with limitations
        $trial = $this->trialService->start(
            $license,
            $fingerprint,
            14, // days
            [
                'max_projects' => 3,
                'max_users' => 1,
                'watermark' => true,
                'export_disabled' => true,
                'api_access' => false,
            ]
        );
        
        // Send welcome email
        Mail::to($user->email)->send(new TrialStartedMail($user, $trial));
        
        // Track trial start
        event(new TrialStartedEvent($user, $trial));
        
        return redirect()->route('trial.dashboard')
            ->with('success', 'Your 14-day trial has started!');
    }
    
    public function extendTrial(Request $request)
    {
        $user = $request->user();
        $trial = $user->license->trials()->active()->first();
        
        if (!$trial || !$trial->canExtend()) {
            return back()->with('error', 'Trial cannot be extended');
        }
        
        // One-time 7-day extension
        if ($trial->extension_count >= 1) {
            return back()->with('error', 'Trial has already been extended');
        }
        
        $validated = $request->validate([
            'reason' => 'required|string|min:20',
        ]);
        
        $trial = $this->trialService->extend(
            $trial,
            7,
            $validated['reason']
        );
        
        // Track extension
        event(new TrialExtendedEvent($user, $trial));
        
        return back()->with('success', 'Trial extended for 7 more days!');
    }
    
    public function convertTrial(Request $request)
    {
        $user = $request->user();
        $trial = $user->license->trials()->active()->first();
        
        if (!$trial || !$trial->canConvert()) {
            return back()->with('error', 'Trial cannot be converted');
        }
        
        $validated = $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'payment_method' => 'required|string',
        ]);
        
        DB::transaction(function () use ($user, $trial, $validated) {
            // Process payment (simplified)
            $payment = $this->processPayment(
                $user,
                $validated['plan'],
                $validated['payment_method']
            );
            
            // Convert trial to full license
            $fullLicense = $this->trialService->convert(
                $trial,
                'purchase',
                $payment->amount
            );
            
            // Update license with new template
            $template = LicenseTemplate::findBySlug($validated['plan']);
            $fullLicense->update([
                'template_id' => $template->id,
                'expires_at' => now()->addYear(),
                'max_usages' => $template->base_configuration['max_usages'],
                'meta' => array_merge($fullLicense->meta->toArray(), [
                    'is_trial' => false,
                    'converted_from_trial' => true,
                    'conversion_date' => now(),
                    'payment_id' => $payment->id,
                ]),
            ]);
            
            // Remove trial limitations
            $fullLicense->meta = collect($fullLicense->meta)
                ->except(['limitations'])
                ->toArray();
            $fullLicense->save();
            
            // Track conversion
            event(new TrialConvertedEvent($user, $trial, $fullLicense, $payment));
        });
        
        return redirect()->route('dashboard')
            ->with('success', 'Welcome to the full version!');
    }
}
```

## 2. Trial monitoring dashboard

```php
// app/Http/Controllers/Admin/TrialMonitoringController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTrial;
use Illuminate\Support\Facades\DB;

class TrialMonitoringController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'active_trials' => LicenseTrial::where('status', TrialStatus::Active)->count(),
            'conversions_today' => LicenseTrial::where('status', TrialStatus::Converted)
                ->whereDate('converted_at', today())
                ->count(),
            'conversion_rate' => $this->calculateConversionRate(),
            'average_trial_days' => $this->calculateAverageTrialDays(),
        ];
        
        $recentTrials = LicenseTrial::with('license.licensable')
            ->latest()
            ->take(10)
            ->get();
        
        $conversionFunnel = $this->getConversionFunnel();
        
        return view('admin.trials.dashboard', compact(
            'stats',
            'recentTrials',
            'conversionFunnel'
        ));
    }
    
    private function calculateConversionRate(): float
    {
        $total = LicenseTrial::whereIn('status', [
            TrialStatus::Converted,
            TrialStatus::Expired,
            TrialStatus::Cancelled,
        ])->count();
        
        if ($total === 0) {
            return 0;
        }
        
        $converted = LicenseTrial::where('status', TrialStatus::Converted)->count();
        
        return round(($converted / $total) * 100, 2);
    }
    
    private function calculateAverageTrialDays(): float
    {
        return LicenseTrial::where('status', TrialStatus::Converted)
            ->selectRaw('AVG(DATEDIFF(converted_at, started_at)) as avg_days')
            ->value('avg_days') ?? 0;
    }
    
    private function getConversionFunnel(): array
    {
        return [
            'started' => LicenseTrial::count(),
            'activated' => LicenseTrial::whereNotNull('started_at')->count(),
            'engaged' => $this->getEngagedTrialsCount(),
            'extended' => LicenseTrial::where('extension_count', '>', 0)->count(),
            'converted' => LicenseTrial::where('status', TrialStatus::Converted)->count(),
        ];
    }
    
    private function getEngagedTrialsCount(): int
    {
        // Define engagement as users who used the trial for at least 3 days
        return LicenseTrial::whereRaw('DATEDIFF(COALESCE(converted_at, expires_at, NOW()), started_at) >= 3')
            ->count();
    }
}
```

---

[← Docs index](../../README.md#documentation)
