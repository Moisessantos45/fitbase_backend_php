<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class ValidationException extends AppException
{
    /**
     * @param array<string, string|array> $errors
     */
    public function __construct(
        string $message = 'Error de validación',
        private array $errors = [],
        string $codeString = 'VALIDATION_FAILED'
    ) {
        parent::__construct($message, 422, $codeString);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

