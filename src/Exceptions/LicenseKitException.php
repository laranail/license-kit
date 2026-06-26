<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Exceptions;

use Exception;

/**
 * Base for every exception thrown by laranail/license-kit, so consumers can
 * catch the whole family with a single `catch (LicenseKitException)`.
 */
class LicenseKitException extends Exception {}
