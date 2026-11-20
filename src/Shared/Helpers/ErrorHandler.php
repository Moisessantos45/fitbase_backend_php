<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Shared\Errors\AppException;
use App\Shared\Errors\ConflictException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use PDOException;
use Throwable;

class ErrorHandler
{
    public static function handleDatabaseError(Throwable $e): AppException
    {
        return self::translateDBError($e);
    }

    public static function translateDBError(Throwable $e): AppException
    {
        if ($e instanceof PDOException) {
            $sqlState = (string) $e->getCode();

            return match ($sqlState) {
                '23505' => new ConflictException('El recurso ya existe en la base de datos (registro duplicado)'),
                '23503' => AppException::badRequest('Referencia a entidad inexistente (llave foránea inválida)', 'INVALID_FOREIGN_KEY'),
                '23502' => AppException::badRequest('Falta un campo obligatorio requerido por la base de datos', 'NOT_NULL_VIOLATION'),
                '23514' => AppException::badRequest('Restricción de validación no superada en base de datos', 'CHECK_VIOLATION'),
                '22001' => AppException::badRequest('Texto demasiado largo para el campo permitido', 'STRING_DATA_RIGHT_TRUNCATION'),
                '22003' => AppException::badRequest('Valor numérico fuera de rango', 'NUMERIC_VALUE_OUT_OF_RANGE'),
                '22P02' => AppException::badRequest('Formato de dato no válido (ej. UUID o entero mal formado)', 'INVALID_TEXT_REPRESENTATION'),
                '22007', '22008' => AppException::badRequest('Formato de fecha u hora no válido', 'INVALID_DATETIME_FORMAT'),
                '08006', '57P01', '57P03' => AppException::internal('No se pudo establecer conexión con la base de datos', $e),
                default => AppException::internal('Error en la operación con la base de datos: ' . $e->getMessage(), $e),
            };
        }

        if ($e instanceof AppException) {
            return $e;
        }

        return AppException::internal('Error interno inesperado', $e);
    }

    public static function handle(Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            Response::error(
                message: $e->getMessage(),
                status: $e->getStatus(),
                code: $e->getCodeString(),
                errors: $e->getErrors()
            );
            return;
        }

        if ($e instanceof AppException) {
            if ($e->getStatus() >= 500) {
                error_log(sprintf('[ERROR] %s: %s', $e->getMessage(), $e->getPrevious()?->getMessage() ?? ''));
            }

            Response::error(
                message: $e->getMessage(),
                status: $e->getStatus(),
                code: $e->getCodeString() ?: null
            );
            return;
        }

        if ($e instanceof PDOException) {
            $translated = self::translateDBError($e);
            self::handle($translated);
            return;
        }

        error_log(sprintf('[UNHANDLED ERROR] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        Response::error('Error interno en el servidor', 500, 'INTERNAL_SERVER_ERROR');
    }
}

