<?php

declare(strict_types=1);

namespace App\Routes;

use App\Features\Storage\StorageController;
use App\Shared\Middlewares\StorageAuthMiddleware;
use Flight;

class StorageRoutes
{
    public static function register(): void
    {
        $controller = new StorageController();

        Flight::group('/api/v1/storage/public', function () use ($controller) {
            Flight::route('GET /@slug', [$controller, 'getBySlug']);
        });
        Flight::group('/storage/public', function () use ($controller) {
            Flight::route('GET /@slug', [$controller, 'getBySlug']);
        });

        Flight::group('/api/v1/storage', function () use ($controller) {
            Flight::route('GET /@slug', [$controller, 'getBySlug']);
            Flight::route('DELETE /@slug', [$controller, 'deleteBySlug']);
        }, [new StorageAuthMiddleware()]);

        Flight::group('/storage', function () use ($controller) {
            Flight::route('GET /@slug', [$controller, 'getBySlug']);
            Flight::route('DELETE /@slug', [$controller, 'deleteBySlug']);
        }, [new StorageAuthMiddleware()]);
    }
}

