<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use Flight;

class RequestIDMiddleware
{
    public static function handle(): string
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        if (empty($requestId)) {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $requestId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        Flight::set('request_id', $requestId);
        header('X-Request-ID: ' . $requestId);

        return $requestId;
    }
}

