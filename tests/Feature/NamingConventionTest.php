<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Simtabi\Laranail\Licence\Kit\Contracts\TokenIssuer;
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\NameRegistry;
use Simtabi\Laranail\Licence\Kit\Support\DeprecatedNames;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;
use Simtabi\Laranail\Package\Tools\Support\Routing\BareRouteNameAliases;

uses(AssertsRegisteredNames::class);

/*
 * Every public name license-kit registers carries the vendor and slug, read from the live
 * registries of the booted application. The old bare names stay registered as deprecated
 * aliases, and each is listed here so a stale entry fails instead of covering a new name.
 */

const LICENSE_KIT_BARE_ROUTES = [
    'licensing.health'        => 'laranail-license-kit.health',
    'licensing.activate'      => 'laranail-license-kit.activate',
    'licensing.deactivate'    => 'laranail-license-kit.deactivate',
    'licensing.refresh'       => 'laranail-license-kit.refresh',
    'licensing.validate'      => 'laranail-license-kit.validate',
    'licensing.heartbeat'     => 'laranail-license-kit.heartbeat',
    'licensing.usages.index'  => 'laranail-license-kit.usages.index',
    'licensing.usages.revoke' => 'laranail-license-kit.usages.revoke',
    'licensing.licenses.show' => 'laranail-license-kit.licenses.show',
    'licensing.token.issue'   => 'laranail-license-kit.token.issue',
];

const LICENSE_KIT_BARE_LIMITERS = [
    'licensing-validate' => 'laranail-license-kit.validate',
    'licensing-register' => 'laranail-license-kit.register',
    'licensing-token'    => 'laranail-license-kit.token',
];

function licenseKitScope(): NamingScope
{
    return NamingScope::for(
        'laranail/license-kit',
        'Simtabi\\Laranail\\Licence\\Kit\\',
        // src/, not the package root: in the package's own suite the root also holds vendor/
        // and tests/, so every framework closure and the harness's own `api` limiter would
        // read as this package's.
        basePath: dirname(__DIR__, 2) . '/src',
    );
}

beforeEach(function (): void {
    BareRouteNameAliases::forgetWarnings();
    DeprecatedNames::forgetWarnings();
});

it('scopes every route name', function (): void {
    $scoped = $this->assertRouteNamesScoped(licenseKitScope(), atLeast: 10);

    expect($scoped)->toContain(...array_values(LICENSE_KIT_BARE_ROUTES));
});

it('keeps every bare route name resolving to its scoped route', function (): void {
    $this->assertDeprecatedRouteNamesResolve(LICENSE_KIT_BARE_ROUTES);
});

it('announces a bare route name once', function (): void {
    $notices = [];
    set_error_handler(function (int $level, string $message) use (&$notices): bool {
        $notices[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        route('licensing.activate');
        route('licensing.activate');
    } finally {
        restore_error_handler();
    }

    expect($notices)->toHaveCount(1)
        ->and($notices[0])->toContain('licensing.activate')->toContain('laranail-license-kit.activate');
});

it('scopes every rate limiter, keeping the bare ones as deprecated aliases', function (): void {
    // The harness's own `api` limiter is defined in Tests\TestCase, inside the owner namespace.
    // Re-register it through a framework closure so only the package's limiters are judged.
    RateLimiter::for('api', Limit::none(...));

    $scoped = $this->assertRateLimitersScoped(
        licenseKitScope(),
        deprecated: array_keys(LICENSE_KIT_BARE_LIMITERS),
        atLeast: 3,
    );

    expect($scoped)->toContain(...array_values(LICENSE_KIT_BARE_LIMITERS));
});

it('throttles every API route through a scoped limiter', function (): void {
    $throttled = 0;

    foreach (LICENSE_KIT_BARE_ROUTES as $scoped) {
        $middleware = app('router')->getRoutes()->getByName($scoped)->gatherMiddleware();
        $ours = array_filter(
            $middleware,
            static fn (mixed $m): bool => is_string($m) && preg_match('/^throttle:(licensing-|laranail-license-kit\.)/', $m) === 1,
        );

        foreach ($ours as $throttle) {
            expect($throttle)->toStartWith('throttle:laranail-license-kit.');
            $throttled++;
        }
    }

    // Every route but health is throttled by one of the package's limiters.
    expect($throttled)->toBe(9);
});

it('delegates each bare rate limiter to its scoped limiter and announces it once', function (): void {
    $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $notices = [];
    set_error_handler(function (int $level, string $message) use (&$notices): bool {
        $notices[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        foreach (LICENSE_KIT_BARE_LIMITERS as $bare => $scoped) {
            $old = RateLimiter::limiter($bare)($request);
            RateLimiter::limiter($bare)($request);
            $new = RateLimiter::limiter($scoped)($request);

            expect($old->maxAttempts)->toBe($new->maxAttempts)
                ->and($old->key)->toBe($new->key);
        }
    } finally {
        restore_error_handler();
    }

    expect($notices)->toHaveCount(3);
    foreach (LICENSE_KIT_BARE_LIMITERS as $bare => $scoped) {
        expect(implode("\n", $notices))->toContain("[{$bare}]")->toContain("[{$scoped}]");
    }
});

it('scopes the token service binding and keeps the bare name as an alias', function (): void {
    $this->assertRegisteredNamesScoped(
        NameRegistry::ContainerAlias,
        licenseKitScope(),
        deprecated: ['licensing.token'],
    );

    expect(app()->bound('laranail.license-kit.token'))->toBeTrue()
        ->and(app()->isAlias('licensing.token'))->toBeTrue()
        ->and(app()->getAlias('licensing.token'))->toBe('laranail.license-kit.token')
        ->and(app('licensing.token'))->toBe(app('laranail.license-kit.token'))
        ->and(app('laranail.license-kit.token'))->toBeInstanceOf(TokenIssuer::class);
});
