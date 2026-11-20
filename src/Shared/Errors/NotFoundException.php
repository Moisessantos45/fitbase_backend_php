<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class NotFoundException extends AppException
{
    public function __construct(string $message = 'Recurso no encontrado', string $codeString = 'NOT_FOUND')
    {
        parent::__construct($message, 404, $codeString);
    }
}

