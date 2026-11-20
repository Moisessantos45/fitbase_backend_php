<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Subscription\SubscriptionController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class SubscriptionRoutes
{
    public static function register(): void
    {
        $controller = new SubscriptionController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('GET /subscriptions/@slug/ticket', [$controller, 'getTicketBySlug']);
        });

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /subscriptions/subscribe', [$controller, 'purchaseSubscription']);
            Flight::route('POST /subscriptions/@id:[0-9]+/renew', [$controller, 'renewSubscription']);
            Flight::route('GET /subscriptions/my-subscriptions', [$controller, 'getMySubscriptions']);
            Flight::route('GET /gyms/@id:[0-9]+/subscriptions', [$controller, 'getAllByGym']);
            Flight::route('PUT /subscriptions/@id:[0-9]+/cancel', [$controller, 'cancelSubscription']);
        }, [new AuthMiddleware()]);
    }
}

