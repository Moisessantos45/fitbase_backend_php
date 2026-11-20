<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use App\Config\Redis;
use App\Shared\Utils\Response;
use Flight;
use Throwable;

class RateLimiterMiddleware
{
    public static function handle(int $maxRequests = 300, int $windowSeconds = 60): bool
    {
        $ip = Flight::request()->ip ?: '127.0.0.1';
        $key = "ratelimit:{$ip}";

        try {
            $redis = Redis::getClient();
            $current = $redis->incr($key);

            if ($current === 1) {
                $redis->expire($key, $windowSeconds);
            }

            if ($current > $maxRequests) {
                Response::error('Demasiadas solicitudes. Por favor intente más tarde.', 429, 'RATE_LIMIT_EXCEEDED');
                return false;
            }
        } catch (Throwable) {
        }

        return true;
    }
}

