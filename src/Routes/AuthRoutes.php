<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Auth\AuthController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class AuthRoutes
{
    public static function register(): void
    {
        $controller = new AuthController();

        Flight::route('POST /api/v1/auth/register', [$controller, 'register']);
        Flight::route('POST /api/v1/auth/register-owner', [$controller, 'registerOwner']);
        Flight::route('POST /api/v1/auth/login', [$controller, 'login']);
        Flight::route('POST /api/v1/auth/login-mobile', [$controller, 'loginMobile']);
        Flight::route('POST /api/v1/auth/logout', [$controller, 'logout']);
        Flight::route('POST /api/v1/auth/logout-mobile', [$controller, 'logout']);
        Flight::route('POST /api/v1/auth/refresh-token', [$controller, 'refreshToken']);
        Flight::route('POST /api/v1/auth/refresh-token-mobile', [$controller, 'refreshTokenMobile']);
        Flight::route('POST /api/v1/auth/forward-email-verification', [$controller, 'forwardEmailVerification']);
        Flight::route('POST /api/v1/auth/forgot-password', [$controller, 'sendPasswordReset']);

        Flight::route('GET /api/v1/auth/confirm-account', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->confirmAccount();
        });

        Flight::route('GET /api/v1/auth/session', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getSession();
        });

        Flight::route('PATCH /api/v1/auth/reset-password', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->resetPassword();
        });

        Flight::route('PATCH /api/v1/auth/change-password', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->updatePassword();
        });
    }
}
