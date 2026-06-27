<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command as BaseCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base command for laranail/license-kit. Extends laranail/console's command base
 * (managed lifecycle + `$this->services`) and applies {@see SupportsNamespacedNames}
 * so the `laranail::license-kit.*` shape and the `licensing:*` aliases (listed in
 * each command's `$commandAliases`) write past Symfony's name validator. The base
 * constructor already applies `$commandAliases`.
 */
abstract class Command extends BaseCommand
{
    use SupportsNamespacedNames;
}
