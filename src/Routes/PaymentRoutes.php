<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Payment\PaymentController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class PaymentRoutes
{
    public static function register(): void
    {
        $controller = new PaymentController();

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /payments/stripe/webhook', [$controller, 'handleStripeWebhook']);
            Flight::route('POST /payments/stripe/guest-checkout', [$controller, 'createGuestCheckoutSession']);
            Flight::route('GET /payments/stripe/session/@session_id', [$controller, 'getSessionStatus']);
        });

        Flight::group('/api/v1', function () use ($controller) {
            Flight::route('POST /payments/cash', [$controller, 'registerCashPayment']);
            Flight::route('GET /gyms/@id:[0-9]+/payments/revenue', [$controller, 'getRevenueSummary']);
            Flight::route('GET /gyms/@id:[0-9]+/payments', [$controller, 'getAllByGym']);
            Flight::route('GET /payments/my-payments', [$controller, 'getMyPayments']);
            Flight::route('POST /payments/stripe/checkout-session', [$controller, 'createCheckoutSession']);
        }, [new AuthMiddleware()]);
    }
}

