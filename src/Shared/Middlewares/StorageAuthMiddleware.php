<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use App\Config\Redis;
use App\Shared\Utils\PasetoMaker;
use App\Shared\Utils\Response;
use Flight;
use Throwable;

class StorageAuthMiddleware
{
    private static ?PasetoMaker $maker = null;

    public function before(): void
    {
        if (!self::handle()) {
            exit;
        }
    }

    public function __invoke(): bool
    {
        return self::handle();
    }

    public static function handle(): bool
    {
        $authHeader = Flight::request()->getHeader('Authorization') ?: '';
        $token = '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        } else {
            $token = Flight::request()->cookies['refresh_token']
                ?? $_COOKIE['refresh_token']
                ?? '';

            if (!$token) {
                $cookieHeader = Flight::request()->getHeader('Cookie') ?: '';
                if (preg_match('/refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                    $token = urldecode(trim($matches[1]));
                }
            }
        }

        if ($token === '' || strlen($token) < 20) {
            Response::error('unauthorized', 401, 'UNAUTHORIZED');
            return false;
        }

        try {
            $redis = Redis::getClient();
            if ($redis->exists("blacklist:{$token}") > 0) {
                Response::error('token revoked', 401, 'TOKEN_REVOKED');
                return false;
            }
        } catch (Throwable) {
        }

        try {
            if (self::$maker === null) {
                self::$maker = new PasetoMaker();
            }

            $payload = self::$maker->verifyToken($token);
            $userId = (string) ($payload['sub'] ?? '');

            if ($userId === '') {
                Response::error('unauthorized', 401, 'UNAUTHORIZED');
                return false;
            }

            $platform = (string) ($payload['platform'] ?? 'web');
            if (!self::validateStorageSessionToken($userId, $platform, $token)) {
                Response::error('session expired or invalid', 401, 'UNAUTHORIZED');
                return false;
            }

            Flight::set('userId', (int) $userId);
            Flight::set('auth_id', (int) $userId);
            Flight::set('authId', (int) $userId);
            Flight::set('tokenPayload', $payload);
            Flight::set('token', $token);

            return true;
        } catch (Throwable) {
            Response::error('unauthorized', 401, 'UNAUTHORIZED');
            return false;
        }
    }

    public static function validateStorageSessionToken(string $userId, string $platform, string $token): bool
    {
        try {
            $redis = Redis::getClient();
            $sessionId = null;

            if ($platform !== '') {
                $sessionId = $redis->get("user_session:{$platform}:{$userId}");
            }

            if (!$sessionId) {
                $candidateKeys = [
                    "user_session:web:{$userId}",
                    "user_session:mobile:{$userId}",
                    "user_session:{$userId}",
                ];
                foreach ($candidateKeys as $k) {
                    $sessionId = $redis->get($k);
                    if ($sessionId) {
                        break;
                    }
                }
            }

            if (!$sessionId) {
                return false;
            }

            $sessionKey = "session:{$sessionId}";
            $accToken = $redis->hget($sessionKey, 'access_token');
            $refToken = $redis->hget($sessionKey, 'refresh_token');

            return $token === $accToken || $token === $refToken;
        } catch (Throwable) {
            return true;
        }
    }
}

