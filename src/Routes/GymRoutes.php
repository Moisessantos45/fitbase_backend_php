<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Gym\GymController;
use App\Shared\Middlewares\AuthMiddleware;
use Flight;

class GymRoutes
{
    public static function register(): void
    {
        $controller = new GymController();

        Flight::route('GET /api/v1/gyms', [$controller, 'getAllPublic']);
        Flight::route('GET /api/v1/gyms/@id:[0-9]+', [$controller, 'getDetailsById']);

        Flight::route('POST /api/v1/gyms', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->create();
        });

        Flight::route('GET /api/v1/gyms/my-gym', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getMyGymDetails();
        });

        Flight::route('GET /api/v1/gyms/my-gyms', function () use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->getAllByOwner();
        });

        Flight::route('PUT /api/v1/gyms/@id:[0-9]+', function (string $id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->update($id);
        });

        Flight::route('POST /api/v1/gyms/@id:[0-9]+/schedules', function (string $id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->setSchedules($id);
        });

        Flight::route('PUT /api/v1/gyms/@id:[0-9]+/schedules/@schedule_id:[0-9]+', function (string $id, string $schedule_id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->updateSchedule($id, $schedule_id);
        });

        Flight::route('POST /api/v1/gyms/@id:[0-9]+/cover', function (string $id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadCover($id);
        });

        Flight::route('PUT /api/v1/gyms/@id:[0-9]+/cover', function (string $id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadCover($id);
        });

        Flight::route('POST /api/v1/gyms/@id:[0-9]+/photos', function (string $id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->uploadPhotos($id);
        });

        Flight::route('DELETE /api/v1/gyms/@id:[0-9]+/photos/@photo_id:[0-9]+', function (string $id, string $photo_id) use ($controller) {
            if (!AuthMiddleware::handle()) {
                return;
            }
            $controller->deletePhoto($id, $photo_id);
        });
    }
}

