<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Manager\ManagerController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class ManagerRoutes
{
    public static function register(): void
    {
        $controller = new ManagerController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /managers', [$controller, 'createManager']);
            Flight::route('GET /managers/me', [$controller, 'getDetailsMe']);
            Flight::route('GET /gyms/managers', [$controller, 'getAllManagersByGym']);
            Flight::route('POST /managers/me/upload-avatar', [$controller, 'uploadAvatar']);
            Flight::route('PUT /managers/me', [$controller, 'update']);

            Flight::route('POST /cash-register/open', [$controller, 'openCashRegister']);
            Flight::route('POST /cash-register/close', [$controller, 'closeCashRegister']);
            Flight::route('GET /cash-register/current', [$controller, 'getCurrentCashRegister']);
        }, [new AuthMiddleware()]);
    }
}

