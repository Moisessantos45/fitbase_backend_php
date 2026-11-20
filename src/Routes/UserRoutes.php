<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\User\UserController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class UserRoutes
{
    public static function register(): void
    {
        $controller = new UserController();

        Flight::route('GET /api/v1/users/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getMe();
        });
        Flight::route('GET /api/v1/user/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getMe();
        });

        Flight::route('POST /api/v1/users', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->completeProfile();
        });
        Flight::route('POST /api/v1/user', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->completeProfile();
        });

        Flight::route('PUT /api/v1/users/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->update();
        });
        Flight::route('PUT /api/v1/user/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->update();
        });

        Flight::route('POST /api/v1/users/me/upload-avatar', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadAvatar();
        });
        Flight::route('POST /api/v1/user/me/upload-avatar', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadAvatar();
        });
        Flight::route('POST /api/v1/me/upload-avatar', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadAvatar();
        });
    }
}

