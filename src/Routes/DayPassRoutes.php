<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\DayPass\DayPassController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class DayPassRoutes
{
    public static function register(): void
    {
        $controller = new DayPassController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('GET /daypass/@slug/ticket', [$controller, 'getTicketBySlug']);
            Flight::route('POST /daypass/guest', [$controller, 'createPublicDayPass']);
        });

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /daypass/purchase', [$controller, 'purchaseDayPass']);
            Flight::route('POST /daypass/register-guest', [$controller, 'registerGuest']);
            Flight::route('GET /gyms/@id:[0-9]+/daypasses', [$controller, 'getAllByGym']);
        }, [new AuthMiddleware()]);
    }
}

