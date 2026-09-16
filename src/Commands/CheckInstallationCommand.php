<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Doctor\Checks;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorReporter;

class CheckInstallationCommand extends Command
{
    protected $signature = 'laranail::license-kit.check {--json}';

    protected $description = 'Verify the licensing package installation status';

    public function handle(): int
    {
        return DoctorReporter::render($this, Checks::all(), (bool) $this->option('json'));
    }
}
