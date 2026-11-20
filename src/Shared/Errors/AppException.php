<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use Exception;
use Throwable;

class AppException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 400,
        protected string $codeString = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCodeString(): string
    {
        return $this->codeString;
    }

    public static function badRequest(string $message, string $code = ''): self
    {
        return new self($message, 400, $code);
    }

    public static function unauthorized(string $message = 'No autorizado', string $code = ''): self
    {
        return new self($message, 401, $code);
    }

    public static function forbidden(string $message = 'Acceso denegado', string $code = ''): self
    {
        return new self($message, 403, $code);
    }

    public static function notFound(string $message = 'Recurso no encontrado', string $code = ''): self
    {
        return new self($message, 404, $code);
    }

    public static function conflict(string $message = 'El recurso ya existe o genera conflicto', string $code = ''): self
    {
        return new self($message, 409, $code);
    }

    public static function internal(string $message = 'Error interno del servidor', ?Throwable $cause = null): self
    {
        return new self($message, 500, 'INTERNAL_ERROR', $cause);
    }
}

