<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Analytics\AnalyticsController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class AnalyticsRoutes
{
    public static function register(): void
    {
        $controller = new AnalyticsController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('GET /gyms/@id:[0-9]+/analytics/shifts', [$controller, 'getManagerShifts']);
            Flight::route('GET /gyms/@id:[0-9]+/analytics/attendance', [$controller, 'getAttendanceMetrics']);
            Flight::route('GET /gyms/@id:[0-9]+/analytics/revenue', [$controller, 'getCrossedRevenue']);
            Flight::route('GET /gyms/@id:[0-9]+/analytics/clients/@user_id:[0-9]+', [$controller, 'getClientHistory']);
            Flight::route('GET /gyms/@id:[0-9]+/analytics/dashboard-summary', [$controller, 'getDashboardSummary']);
        }, [new AuthMiddleware()]);
    }
}

