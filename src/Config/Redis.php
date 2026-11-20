<?php

declare(strict_types=1);

namespace App\Config;

use Predis\Client as PredisClient;
use Throwable;

class Redis
{
    private static ?PredisClient $client = null;

    public static function getInstance(): PredisClient
    {
        return self::getClient();
    }

    public static function getClient(): PredisClient
    {
        if (self::$client === null) {
            $host     = self::env(['REDIS_HOST'], '127.0.0.1');
            $port     = (int) self::env(['REDIS_PORT'], '6379');
            $password = self::env(['REDIS_PASSWORD'], '');
            $database = (int) self::env(['REDIS_DB'], '0');
            $timeout  = (float) self::env(['REDIS_TIMEOUT'], '2.0');

            $parameters = [
                'scheme'             => 'tcp',
                'host'               => $host,
                'port'               => $port,
                'timeout'            => $timeout,
                'read_write_timeout' => $timeout,
                'database'           => $database,
            ];

            if ($password !== '') {
                $parameters['password'] = $password;
            }

            $options = [
                'prefix' => 'fitbase:',
            ];

            self::$client = new PredisClient($parameters, $options);
        }

        return self::$client;
    }

    public static function checkConnection(): array
    {
        try {
            $client = self::getClient();
            $pong = $client->ping();

            return [
                'connected' => true,
                'ping'      => (string) $pong,
                'host'      => self::env(['REDIS_HOST'], '127.0.0.1'),
                'port'      => (int) self::env(['REDIS_PORT'], '6379'),
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    public static function disconnect(): void
    {
        if (self::$client !== null) {
            try {
                self::$client->disconnect();
            } catch (Throwable) {
            }
            self::$client = null;
        }
    }

    private static function env(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val !== false && $val !== null && $val !== '') {
                return (string) $val;
            }
        }
        return $default;
    }
}

