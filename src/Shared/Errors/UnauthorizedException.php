<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class UnauthorizedException extends AppException
{
    public function __construct(string $message = 'No autorizado', string $codeString = 'UNAUTHORIZED')
    {
        parent::__construct($message, 401, $codeString);
    }
}

