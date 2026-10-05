<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route as RouteFacade;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\TokenController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\UsageController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\HealthController;
use Simtabi\Laranail\Licence\Kit\Http\Controllers\Api\LicenseController;

function routeByName(string $name): Route
{
    $route = RouteFacade::getRoutes()->getByName($name);

    expect($route)->not->toBeNull("Route {$name} should be registered");

    return $route;
}

test('license API routes expose expected URIs and middleware', function (): void {
    // Routes are registered by the provider at boot (see the regression test below) — no
    // manual require needed.
    $prefix = config('licensing.api.prefix');

    $activateRoute = routeByName('laranail-license-kit.activate');
    expect($activateRoute->uri())->toBe($prefix . '/activate');

    $deactivateRoute = routeByName('laranail-license-kit.deactivate');
    expect($deactivateRoute->uri())->toBe($prefix . '/deactivate');

    $refreshRoute = routeByName('laranail-license-kit.refresh');
    expect($refreshRoute->uri())->toBe($prefix . '/refresh');

    $validateRoute = routeByName('laranail-license-kit.validate');
    expect($validateRoute->uri())->toBe($prefix . '/validate')
        ->and(collect($validateRoute->gatherMiddleware()))
        ->toContain('api');

    $heartbeatRoute = routeByName('laranail-license-kit.heartbeat');
    expect($heartbeatRoute->uri())->toBe($prefix . '/heartbeat');

    $licenseShowRoute = routeByName('laranail-license-kit.licenses.show');
    expect($licenseShowRoute->uri())->toBe($prefix . '/licenses/show');

    $healthRoute = routeByName('laranail-license-kit.health');
    expect($healthRoute->uri())->toBe($prefix . '/health');

    $tokenRoute = routeByName('laranail-license-kit.token.issue');
    expect($tokenRoute->uri())->toBe($prefix . '/token');
});

// Regression: the provider must register the API routes ITSELF, from the merged config
// default (`licensing.api.enabled => true`), with no manual require/publish. Before the
// boot-time fix the gate ran in configurePackage() — before the package config was merged —
// so config('licensing.api.enabled') was null and the routes were silently never registered.
test('the provider registers API routes from the merged config default', function (): void {
    expect(config('licensing.api.enabled'))->toBeTrue();

    expect(RouteFacade::has('laranail-license-kit.activate'))->toBeTrue('Provider should register API routes at boot');
    expect(RouteFacade::has('laranail-license-kit.validate'))->toBeTrue();
    expect(RouteFacade::has('laranail-license-kit.health'))->toBeTrue();
});

// Regression test for issue #4: controller classes were missing in v1.0.3,
// causing "Class does not exist" ReflectionException on `php artisan route:list`.
test('all API route controller classes exist', function (): void {
    expect(class_exists(LicenseController::class))->toBeTrue();
    expect(class_exists(TokenController::class))->toBeTrue();
    expect(class_exists(UsageController::class))->toBeTrue();
    expect(class_exists(HealthController::class))->toBeTrue();
});

test('route:list does not throw when API routes are enabled', function (): void {
    $exitCode = Artisan::call('route:list');

    expect($exitCode)->toBe(0);
});
