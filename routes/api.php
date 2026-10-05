<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\TokenController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\UsageController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\HealthController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\LicenseController;

// Route names are vendor-scoped (`laranail-license-kit.*`). The bare `licensing.*` names used
// until 0.1 still resolve through the provider's hasDeprecatedRouteNames(), and the bare
// `licensing-*` rate limiters stay registered and delegate to the scoped ones.
Route::prefix(config('licensing.api.prefix', 'api/licensing/v1'))
    ->middleware(config('licensing.api.middleware', ['api']))
    ->name('laranail-license-kit.')
    ->group(function () {
        Route::get('health', [HealthController::class, 'show'])->name('health');
        Route::post('activate', [LicenseController::class, 'activate'])->name('activate')->middleware('throttle:laranail-license-kit.register');
        Route::post('deactivate', [LicenseController::class, 'deactivate'])->name('deactivate')->middleware('throttle:laranail-license-kit.register');
        Route::post('refresh', [LicenseController::class, 'refresh'])->name('refresh')->middleware('throttle:laranail-license-kit.token');
        Route::post('validate', [LicenseController::class, 'validateLicense'])->name('validate')->middleware('throttle:laranail-license-kit.validate');
        Route::post('heartbeat', [UsageController::class, 'heartbeat'])->name('heartbeat')->middleware('throttle:laranail-license-kit.validate');
        Route::post('usages', [UsageController::class, 'index'])->name('usages.index')->middleware('throttle:laranail-license-kit.validate');
        Route::post('usages/revoke', [UsageController::class, 'revoke'])->name('usages.revoke')->middleware('throttle:laranail-license-kit.register');
        Route::post('licenses/show', [LicenseController::class, 'show'])->name('licenses.show')->middleware('throttle:laranail-license-kit.validate');
        Route::post('token', [TokenController::class, 'issue'])->name('token.issue')->middleware('throttle:laranail-license-kit.token');
    });
