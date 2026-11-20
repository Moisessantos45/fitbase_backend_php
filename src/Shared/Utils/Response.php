<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use Flight;

class Response
{
    public static function json(mixed $data = null, string $message = 'Operación exitosa', int $status = 200): void
    {
        Flight::response()->header('Content-Type', 'application/json; charset=utf-8');
        Flight::halt($status, json_encode([
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function created(mixed $data = null, string $message = 'Recurso creado exitosamente'): void
    {
        self::json($data, $message, 201);
    }

    public static function error(string $message, int $status = 400, ?string $code = null, mixed $errors = null): void
    {
        $payload = [
            'success' => false,
            'status'  => $status,
            'message' => $message,
        ];

        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        Flight::response()->header('Content-Type', 'application/json; charset=utf-8');
        Flight::halt($status, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

