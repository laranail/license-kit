<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Support;

/**
 * Announces a deprecated public name once per process, naming its vendor-scoped replacement.
 *
 * Route names go through laranail/package-tools' BareRouteNameAliases, which announces its own.
 * This covers the registries that have no such facility: the rate limiters, whose old closures
 * call announce() before delegating to the scoped limiter.
 */
final class DeprecatedNames
{
    /** @var array<string, true> */
    private static array $announced = [];

    public static function announce(string $kind, string $old, string $replacement): void
    {
        $key = $kind . "\0" . $old;

        if (isset(self::$announced[$key])) {
            return;
        }

        self::$announced[$key] = true;

        trigger_error(sprintf(
            'laranail/license-kit: the %s [%s] is deprecated and will be removed no earlier than the next minor after 0.1. Use [%s] instead.',
            $kind,
            $old,
            $replacement,
        ), E_USER_DEPRECATED);
    }

    /**
     * Forget which names were announced. For test suites; a process announces each name once by design.
     */
    public static function forgetWarnings(): void
    {
        self::$announced = [];
    }
}
