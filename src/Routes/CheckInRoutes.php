<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\CheckIn\CheckInController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class CheckInRoutes
{
    public static function register(): void
    {
        $controller = new CheckInController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /checkin/scan', [$controller, 'validateAccess']);
            Flight::route('GET /checkin/my-history', [$controller, 'getMyHistory']);
            Flight::route('GET /gyms/@id:[0-9]+/checkins', [$controller, 'getAllByGym']);
        }, [new AuthMiddleware()]);
    }
}

