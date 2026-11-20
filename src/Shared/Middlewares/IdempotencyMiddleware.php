<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use App\Config\Redis;
use App\Shared\Utils\Response;
use Flight;

class IdempotencyMiddleware
{
    private const TTL = 300;
    public static function handle(): bool
    {
        $method = Flight::request()->method;
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return true;
        }

        $key = Flight::request()->getHeader('X-Idempotency-Key');
        if (empty($key)) {
            return true;
        }

        $redis = Redis::getClient();
        $redisKey = "idemp:{$key}";

        $val = $redis->get($redisKey);
        if ($val !== null) {
            $cached = json_decode($val, true);
            if (is_array($cached) && !empty($cached['status_code'])) {
                header('Content-Type: application/json');
                header('X-Idempotency-Replayed: true');
                Flight::response()->status((int)$cached['status_code'])->write((string)($cached['body'] ?? ''))->send();
                return false;
            }

            Response::error(
                message: 'Request ya en proceso',
                status: 409,
                code: 'CONFLICT'
            );
            return false;
        }

        $redis->setex($redisKey, 30, json_encode(['status_code' => 0]));
        Flight::set('idempotency_key', $redisKey);

        return true;
    }

    public static function store(int $statusCode, string $body): void
    {
        $redisKey = Flight::get('idempotency_key');
        if (empty($redisKey)) {
            return;
        }

        $redis = Redis::getClient();
        if ($statusCode >= 200 && $statusCode < 400) {
            $data = json_encode([
                'status_code' => $statusCode,
                'body' => $body,
            ]);
            $redis->setex($redisKey, self::TTL, $data);
        } else {
            $redis->del([$redisKey]);
        }
    }
}

