<?php

declare(strict_types=1);

namespace App\Shared\Errors;

class BadRequestException extends AppException
{
    public function __construct(string $message = 'Solicitud incorrecta', string $codeString = 'BAD_REQUEST')
    {
        parent::__construct($message, 400, $codeString);
    }
}

