<?php

declare(strict_types=1);

namespace App\Shared\Utils;

class UrlHelper
{
    public static function buildEvidenceUrl(?string $slug): ?string
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return null;
        }

        $env = strtolower(trim((string)($_ENV['GO_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev')));
        if ($env === 'prod') {
            $apiBase = $_ENV['PUBLIC_API_BASE'] ?? $_ENV['HOST_API_PROD'] ?? 'http://localhost:4107';
        } else {
            $apiBase = $_ENV['HOST_API_DEV'] ?? $_ENV['PUBLIC_API_BASE'] ?? 'http://localhost:4107';
        }

        $apiBase = rtrim($apiBase, '/');
        return "{$apiBase}/api/v1/storage/public/{$slug}";
    }
}

