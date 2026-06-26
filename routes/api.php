<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\HealthController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\LicenseController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\TokenController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\UsageController;

Route::prefix(config('licensing.api.prefix', 'api/licensing/v1'))
    ->middleware(config('licensing.api.middleware', ['api']))
    ->group(function () {
        Route::get('health', [HealthController::class, 'show'])->name('licensing.health');
        Route::post('activate', [LicenseController::class, 'activate'])->name('licensing.activate')->middleware('throttle:licensing-register');
        Route::post('deactivate', [LicenseController::class, 'deactivate'])->name('licensing.deactivate')->middleware('throttle:licensing-register');
        Route::post('refresh', [LicenseController::class, 'refresh'])->name('licensing.refresh')->middleware('throttle:licensing-token');
        Route::post('validate', [LicenseController::class, 'validateLicense'])->name('licensing.validate')->middleware('throttle:licensing-validate');
        Route::post('heartbeat', [UsageController::class, 'heartbeat'])->name('licensing.heartbeat')->middleware('throttle:licensing-validate');
        Route::post('usages', [UsageController::class, 'index'])->name('licensing.usages.index')->middleware('throttle:licensing-validate');
        Route::post('usages/revoke', [UsageController::class, 'revoke'])->name('licensing.usages.revoke')->middleware('throttle:licensing-register');
        Route::post('licenses/show', [LicenseController::class, 'show'])->name('licensing.licenses.show')->middleware('throttle:licensing-validate');
        Route::post('token', [TokenController::class, 'issue'])->name('licensing.token.issue')->middleware('throttle:licensing-token');
    });
