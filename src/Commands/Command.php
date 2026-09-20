<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Package\Tools\Commands\Concerns\ReadsOptions;
use Simtabi\Laranail\Console\Tools\Commands\Command as BaseCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base command for laranail/license-kit. Extends laranail/console's command base
 * (managed lifecycle + `$this->services`) and applies {@see SupportsNamespacedNames}
 * so the `laranail::license-kit.*` shape writes past Symfony's name validator.
 *
 * The `licensing:*` aliases these commands used to declare are gone. An alias that
 * is not vendor-scoped hands back exactly the collision the namespaced name exists
 * to prevent -- `licensing:doctor` is a plausible claim for any licensing package,
 * and the registry is a flat map where the second claimant silently wins. The base
 * constructor still applies `$commandAliases` if a command declares one, so a
 * vendor-scoped alias remains possible.
 */
abstract class Command extends BaseCommand
{
    use ReadsOptions;
    use SupportsNamespacedNames;
}
