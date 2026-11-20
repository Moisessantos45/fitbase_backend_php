<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Plan\PlanController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class PlanRoutes
{
    public static function register(): void
    {
        $controller = new PlanController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('GET /plans/@id:[0-9]+', [$controller, 'getDetailsById']);
            Flight::route('GET /gyms/@id:[0-9]+/plans', [$controller, 'getAllActiveByGymId']);
        });

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /plans', [$controller, 'create']);
            Flight::route('PUT /plans/@id:[0-9]+', [$controller, 'update']);
            Flight::route('GET /gyms/@id:[0-9]+/all-plans', [$controller, 'getAllByGymId']);
            Flight::route('GET /gyms/@id:[0-9]+/plans/enrollment', [$controller, 'getEnrollmentPlans']);

            Flight::route('POST /plans/@id:[0-9]+/items', [$controller, 'addItem']);
            Flight::route('PUT /plans/@id:[0-9]+/items/@item_id:[0-9]+', [$controller, 'updateItem']);
            Flight::route('DELETE /plans/@id:[0-9]+/items/@item_id:[0-9]+', [$controller, 'deleteItem']);
        }, [new AuthMiddleware()]);
    }
}

