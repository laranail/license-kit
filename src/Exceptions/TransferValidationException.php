<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Exceptions;

use Exception;

class TransferValidationException extends Exception
{
    public function __construct(string $message = '', protected array $errors = [], int $code = 0, ?Exception $previous = null)
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
