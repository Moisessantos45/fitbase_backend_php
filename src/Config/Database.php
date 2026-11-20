<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getInstance(): PDO
    {
        return self::getConnection();
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host       = self::env(['DB_HOST', 'HOST'], '127.0.0.1');
            $port       = self::env(['DB_PORT', 'PORT'], '5432');
            $dbname     = self::env(['DB_DATABASE', 'DBNAME'], 'fitbase');
            $user       = self::env(['DB_USERNAME', 'DBUSER'], 'postgres');
            $password   = self::env(['DB_PASSWORD', 'PASSWORD'], '');
            $sslmode    = self::env(['DB_SSLMODE', 'SSLMODE'], 'prefer');
            $timeout    = self::env(['DB_TIMEOUT'], '5');
            $stmtLimit  = self::env(['DB_STATEMENT_TIMEOUT'], '5000');
            $appName    = self::env(['DB_APP_NAME'], 'fitbase_api');
            $persistent = filter_var(self::env(['DB_PERSISTENT'], 'true'), FILTER_VALIDATE_BOOLEAN);

            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;options='--client_encoding=UTF8 --statement_timeout=%s --application_name=%s'",
                $host,
                $port,
                $dbname,
                $sslmode,
                $stmtLimit,
                $appName
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => (int) $timeout,
                PDO::ATTR_PERSISTENT         => $persistent,
            ];

            try {
                self::$connection = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $e) {
                throw new PDOException('Error al conectar con PostgreSQL: ' . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

    public static function checkConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query('SELECT version();');
            $version = $stmt ? $stmt->fetchColumn() : 'Desconocida';

            return [
                'connected'  => true,
                'driver'     => 'pgsql',
                'persistent' => filter_var(self::env(['DB_PERSISTENT'], 'true'), FILTER_VALIDATE_BOOLEAN),
                'version'    => $version,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'driver'    => 'pgsql',
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper para obtener variables de entorno comprobando múltiples alias y valor por defecto.
     * @param string|array<string> $keys
     */
    private static function env(string|array $keys, string $default = ''): string
    {
        $keys = (array) $keys;
        foreach ($keys as $key) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val !== false && $val !== null && $val !== '') {
                return (string) $val;
            }
        }
        return $default;
    }
}
