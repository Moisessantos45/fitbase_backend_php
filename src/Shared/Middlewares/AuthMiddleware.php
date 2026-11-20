<?php

declare(strict_types=1);

namespace App\Shared\Middlewares;

use App\Config\Redis;
use App\Shared\Utils\PasetoMaker;
use App\Shared\Utils\Response;
use Flight;
use Throwable;

class AuthMiddleware
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

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::error('unauthorized', 401, 'UNAUTHORIZED');
            return false;
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '' || strlen($token) < 20) {
            Response::error('unauthorized', 401, 'UNAUTHORIZED');
            return false;
        }

        try {
            $redis = Redis::getClient();
            $isBlacklisted = $redis->exists("blacklist:{$token}");
            if ($isBlacklisted > 0) {
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
            if (!self::validateSessionAccessToken($userId, $platform, $token)) {
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

    public static function validateSessionAccessToken(string $userId, string $platform, string $token): bool
    {
        try {
            $redis = Redis::getClient();
            $sessionId = null;

            if ($platform !== '') {
                $userSessionKey = "user_session:{$platform}:{$userId}";
                $sessionId = $redis->get($userSessionKey);
            }

            if (!$sessionId) {
                $candidateKeys = [
                    "user_session:mobile:{$userId}",
                    "user_session:web:{$userId}",
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
                $confirmToken = $redis->get("confirm_account:{$userId}");
                if ($confirmToken === $token) {
                    return true;
                }
                $resetToken = $redis->get("reset:{$userId}");
                if ($resetToken === $token) {
                    return true;
                }
                return false;
            }

            $sessionKey = "session:{$sessionId}";
            $accToken = $redis->hget($sessionKey, 'access_token');
            if (!$accToken) {
                return false;
            }

            return $token === $accToken;
        } catch (Throwable) {
            return true;
        }
    }
}
