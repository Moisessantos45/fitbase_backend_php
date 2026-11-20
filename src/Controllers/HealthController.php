<?php

namespace App\Controllers;

use App\Config\Database;
use Flight;

class HealthController extends BaseController
{
    public function check(): void
    {
        $dbStatus = Database::checkConnection();

        $this->jsonResponse([
            'status'    => 'ok',
            'timestamp' => date('c'),
            'service'   => 'Fitbase API',
            'version'   => '1.0.0',
            'php'       => PHP_VERSION,
            'database'  => $dbStatus,
        ], 'API en funcionamiento');
    }
}
