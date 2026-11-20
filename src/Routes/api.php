<?php

declare(strict_types=1);

use App\Routes\AnalyticsRoutes;
use App\Routes\AuthRoutes;
use App\Routes\CheckInRoutes;
use App\Routes\DayPassRoutes;
use App\Routes\GymRoutes;
use App\Routes\ManagerRoutes;
use App\Routes\OwnerRoutes;
use App\Routes\PaymentRoutes;
use App\Routes\PlanRoutes;
use App\Routes\StorageRoutes;
use App\Routes\SubscriptionRoutes;
use App\Routes\UserRoutes;
use App\Shared\Utils\Response;

Flight::route('GET /', function () {
    Response::json([
        'name'        => 'Fitbase REST API (PHP)',
        'version'     => '1.0.0',
        'status'      => 'running',
        'description' => 'API construida con FlightPHP, PostgreSQL y Redis exclusivamente en formato JSON',
        'endpoints'   => [
            'health' => 'GET /api/health',
            'auth'   => [
                'register'             => 'POST /api/v1/auth/register',
                'register_owner'       => 'POST /api/v1/auth/register-owner',
                'login'                => 'POST /api/v1/auth/login',
                'login_mobile'         => 'POST /api/v1/auth/login-mobile',
                'logout'               => 'POST /api/v1/auth/logout',
                'refresh_token'        => 'POST /api/v1/auth/refresh-token',
                'refresh_token_mobile' => 'POST /api/v1/auth/refresh-token-mobile',
                'session'              => 'GET /api/v1/auth/session (Protected)',
                'confirm_account'      => 'GET /api/v1/auth/confirm-account (Protected)',
                'forgot_password'      => 'POST /api/v1/auth/forgot-password',
                'reset_password'       => 'PATCH /api/v1/auth/reset-password (Protected)',
                'change_password'      => 'PATCH /api/v1/auth/change-password (Protected)',
            ],
            'users' => [
                'me'            => 'GET /api/v1/users/me (Protected)',
                'complete'      => 'POST /api/v1/users (Protected)',
                'update'        => 'PUT /api/v1/users/me (Protected)',
                'upload_avatar' => 'POST /api/v1/users/me/upload-avatar (Protected)',
            ],
            'owner' => [
                'me'               => 'GET /api/v1/owner/me (Protected)',
                'complete_profile' => 'POST /api/v1/owner/complete-profile (Protected)',
                'update'           => 'PUT /api/v1/owner/me (Protected)',
                'upload_avatar'    => 'POST /api/v1/owner/me/upload-avatar (Protected)',
            ],
            'gyms' => [
                'search'          => 'GET /api/v1/gyms (Public search)',
                'detail'          => 'GET /api/v1/gyms/@id (Public detail)',
                'create'          => 'POST /api/v1/gyms (Protected)',
                'my_gym'          => 'GET /api/v1/gyms/my-gym (Protected)',
                'my_gyms'         => 'GET /api/v1/gyms/my-gyms (Protected)',
                'update'          => 'PUT /api/v1/gyms/@id (Protected)',
                'set_schedules'   => 'POST /api/v1/gyms/@id/schedules (Protected)',
                'update_schedule' => 'PUT /api/v1/gyms/@id/schedules/@schedule_id (Protected)',
                'cover'           => 'POST|PUT /api/v1/gyms/@id/cover (Protected)',
                'photos'          => 'POST /api/v1/gyms/@id/photos (Protected)',
                'delete_photo'    => 'DELETE /api/v1/gyms/@id/photos/@photo_id (Protected)',
            ],
        ],
    ], 'Bienvenido a la API de Fitbase');
});

Flight::route('GET /api/test', function () {
    Response::json(['message' => 'OK']);
});

Flight::route('GET /health', function () {
    Response::json(['status' => 'ok']);
});

AuthRoutes::register();
UserRoutes::register();
OwnerRoutes::register();
GymRoutes::register();
ManagerRoutes::register();
PlanRoutes::register();
SubscriptionRoutes::register();
DayPassRoutes::register();
PaymentRoutes::register();
CheckInRoutes::register();
StorageRoutes::register();
AnalyticsRoutes::register();
