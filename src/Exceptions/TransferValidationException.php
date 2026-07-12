<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Exceptions;

use Throwable;

class TransferValidationException extends LicenseKitException
{
    public function __construct(string $message = '', protected array $errors = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
