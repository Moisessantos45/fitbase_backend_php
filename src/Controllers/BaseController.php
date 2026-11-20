<?php

namespace App\Controllers;

use Flight;

abstract class BaseController
{
    protected function jsonResponse(mixed $data = null, string $message = 'Operación exitosa', int $status = 200): void
    {
        Flight::json([
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function errorResponse(string $message = 'Error en la solicitud', int $status = 400, mixed $errors = null): void
    {
        $payload = [
            'success' => false,
            'status'  => $status,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        Flight::json($payload, $status);
    }

    protected function getRequestBody(): array
    {
        $body = Flight::request()->getBody();
        if (!empty($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return Flight::request()->data->getData();
    }

    protected function getAuthId(): int
    {
        return (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: Flight::get('authId') ?: 0);
    }

    protected function db(): \PDO
    {
        return Flight::db();
    }
}
