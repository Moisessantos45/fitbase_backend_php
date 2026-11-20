<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class ForbiddenException extends AppException
{
    public function __construct(string $message = 'Acceso denegado', string $codeString = 'FORBIDDEN')
    {
        parent::__construct($message, 403, $codeString);
    }
}

