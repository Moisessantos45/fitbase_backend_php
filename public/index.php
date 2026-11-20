<?php

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Config\Redis;
use App\Shared\Helpers\ErrorHandler;
use App\Shared\Middlewares\CorsMiddleware;
use App\Shared\Middlewares\IdempotencyMiddleware;
use App\Shared\Middlewares\RateLimiterMiddleware;
use App\Shared\Middlewares\RequestGuardMiddleware;
use App\Shared\Middlewares\RequestIDMiddleware;
use App\Shared\Middlewares\SecurityHeadersMiddleware;
use App\Shared\Utils\Response;
use Dotenv\Dotenv;
use Flight;
use Throwable;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

Flight::map('db', [Database::class, 'getConnection']);
Flight::map('redis', [Redis::class, 'getClient']);

Flight::before('start', function () {
    RequestIDMiddleware::handle();

    SecurityHeadersMiddleware::handle();

    CorsMiddleware::handle();

    if (!RequestGuardMiddleware::handle()) {
        exit;
    }

    if (!RateLimiterMiddleware::handle(maxRequests: 300, windowSeconds: 60)) {
        exit;
    }

    if (!IdempotencyMiddleware::handle()) {
        exit;
    }
});

Flight::after('start', function () {
    $status = Flight::response()->status();
    $body = Flight::response()->getBody();
    IdempotencyMiddleware::store($status, $body);
});

Flight::route('GET /api/health', function () {
    $dbCheck = Database::checkConnection();
    $redisCheck = Redis::checkConnection();

    $allOk = ($dbCheck['connected'] ?? false) && ($redisCheck['connected'] ?? false);

    Response::json([
        'service'   => 'Fitbase REST API (PHP)',
        'version'   => '1.0.0',
        'status'    => $allOk ? 'healthy' : 'degraded',
        'timestamp' => date('c'),
        'database'  => $dbCheck,
        'redis'     => $redisCheck,
    ], $allOk ? 'Todos los servicios en funcionamiento' : 'Servicio degradado', $allOk ? 200 : 503);
});

Flight::map('notFound', function () {
    Response::error(
        message: 'Ruta no encontrada: ' . Flight::request()->method . ' ' . Flight::request()->url,
        status: 404,
        code: 'NOT_FOUND'
    );
});

Flight::map('error', function (Throwable $e) {
    ErrorHandler::handle($e);
});

require_once __DIR__ . '/../src/Routes/api.php';

Flight::start();
