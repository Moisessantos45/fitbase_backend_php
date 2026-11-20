<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use App\Shared\Utils\Response;
use Flight;

class RequestGuardMiddleware
{
    public static function handle(int $maxSizeBytes = 8388608): bool
    {
        $method = Flight::request()->method;
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return true;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $maxSizeBytes) {
            Response::error('Cuerpo de la petición demasiado grande', 413, 'PAYLOAD_TOO_LARGE');
            return false;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawBody = Flight::request()->getBody();
            if (!empty($rawBody)) {
                json_decode($rawBody);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::error('Cuerpo JSON mal formado: ' . json_last_error_msg(), 400, 'INVALID_JSON');
                    return false;
                }
            }
        }

        return true;
    }
}

