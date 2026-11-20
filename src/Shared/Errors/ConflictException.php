<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class ConflictException extends AppException
{
    public function __construct(string $message = 'El recurso ya existe', string $codeString = 'ALREADY_EXISTS')
    {
        parent::__construct($message, 409, $codeString);
    }
}

