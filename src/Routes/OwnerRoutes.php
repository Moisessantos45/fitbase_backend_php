<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Owner\OwnerController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class OwnerRoutes
{
    public static function register(): void
    {
        $controller = new OwnerController();

        Flight::route('POST /api/v1/owner/complete-profile', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->completeProfile();
        });

        Flight::route('GET /api/v1/owner/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getProfile();
        });

        Flight::route('PUT /api/v1/owner/me', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->updateProfile();
        });

        Flight::route('POST /api/v1/owner/me/upload-avatar', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadAvatar();
        });
    }
}

