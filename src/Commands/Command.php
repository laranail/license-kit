<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Illuminate\Console\Command as BaseCommand;
use Simtabi\Laranail\Licence\Kit\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base command for laranail/license-kit. Supports the `laranail::license-kit.*`
 * naming shape and applies the convenience short aliases listed in
 * {@see $commandAliases} (e.g. the legacy `licensing:*` names, kept for
 * backward compatibility).
 */
abstract class Command extends BaseCommand
{
    use SupportsNamespacedNames;

    /**
     * Convenience aliases applied after construction (written past Symfony's
     * name validator by {@see SupportsNamespacedNames}).
     *
     * @var list<string>
     */
    protected array $commandAliases = [];

    public function __construct()
    {
        parent::__construct();

        if ($this->commandAliases !== []) {
            $this->setAliases($this->commandAliases);
        }
    }
}
