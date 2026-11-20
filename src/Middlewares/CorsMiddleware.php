<?php

namespace App\Middlewares;

use Flight;

class CorsMiddleware
{
    public static function handle(): void
    {
        $response = Flight::response();

        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->header('Content-Type', 'application/json; charset=utf-8');

        if (Flight::request()->method === 'OPTIONS') {
            $response->status(204)->send();
            exit;
        }
    }
}
