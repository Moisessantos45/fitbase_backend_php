<?php

declare(strict_types=1);

namespace App\Features\Storage;

use App\Config\Database;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class StorageRepository
{
    private ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }
        return $this->db;
    }

    public function getBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM files WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function delete(string $slug): bool
    {
        try {
            $stmt = $this->db()->prepare('DELETE FROM files WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }
}

