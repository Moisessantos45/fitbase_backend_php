<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use Flight;

class CorsMiddleware
{
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = self::getAllowedOrigins();
        $isProd = ($_ENV['APP_ENV'] ?? $_ENV['GO_ENV'] ?? 'dev') === 'prod';

        $allowOrigin = '';
        if ($origin !== '') {
            if (in_array($origin, $allowedOrigins, true)) {
                $allowOrigin = $origin;
            } elseif (!$isProd && preg_match('#^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+)(:\d+)?$#', $origin)) {
                $allowOrigin = $origin;
            } elseif (in_array('*', $allowedOrigins, true)) {
                $allowOrigin = $origin;
            } else {
                $allowOrigin = $allowedOrigins[0] ?? $origin;
            }
        }

        if ($allowOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Idempotency-Key, X-Request-ID');
        header('Access-Control-Expose-Headers: Content-Length, X-Request-ID, X-Idempotency-Replayed');
        header('Access-Control-Max-Age: 43200');
        if (Flight::request()->method === 'OPTIONS') {
            Flight::response()->status(204)->send();
            exit;
        }
    }

    private static function getAllowedOrigins(): array
    {
        $isProd = ($_ENV['APP_ENV'] ?? $_ENV['GO_ENV'] ?? 'dev') === 'prod';

        $candidates = [
            $_ENV['HOST_URL_PROD'] ?? null,
            $_ENV['HOST_URL_PROD_WWW'] ?? null,
            $_ENV['HOST_API_PROD'] ?? null,
            $_ENV['HOST_API_PROD_WWW'] ?? null,
            $_ENV['HOST_PYTHON_API_URL'] ?? null,
        ];

        if (!$isProd) {
            $candidates[] = $_ENV['HOST_URL_DEV'] ?? null;
            $candidates[] = $_ENV['HOST_URL_DEV_V2'] ?? null;
            $candidates[] = 'http://localhost:5173';
            $candidates[] = 'http://127.0.0.1:5173';
            $candidates[] = 'http://192.168.100.54:5173';
        }

        $raw = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        if ($raw !== '') {
            $splits = array_map('trim', explode(',', $raw));
            $candidates = array_merge($candidates, $splits);
        }

        $allowed = [];
        foreach ($candidates as $c) {
            if ($c !== null && trim((string)$c) !== '' && trim((string)$c) !== '*') {
                $allowed[] = trim((string)$c);
            }
        }

        return !empty($allowed) ? array_unique($allowed) : ['*'];
    }
}
