<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Exceptions;

use Throwable;

class TransferNotAllowedException extends LicenseKitException
{
    protected string $reason;

    public function __construct(string $message = '', string $reason = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason ?: $message;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
