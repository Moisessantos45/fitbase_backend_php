<?php

declare(strict_types=1);

namespace App\Features\Owner;

use App\Config\Database;
use App\Models\FileModel;
use App\Models\Owner;
use App\Shared\Errors\NotFoundException;
use App\Shared\Helpers\ErrorHandler;
use PDO;
use PDOException;
use Throwable;

class OwnerRepository
{
    public function __construct(private ?PDO $db = null)
    {
    }

    private function db(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    public function withTransaction(callable $fn): mixed
    {
        $pdo = $this->db();
        if ($pdo->inTransaction()) {
            return $fn($this);
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getByAuthId(int $authId): ?OwnerDetail
    {
        $sql = "SELECT 
                    o.id,
                    o.auth_id,
                    o.first_name,
                    o.last_name,
                    o.phone,
                    a.email,
                    f.slug AS slug
                FROM owners o
                INNER JOIN auths a ON a.id = o.auth_id
                LEFT JOIN files f ON f.id = o.avatar_id
                WHERE o.auth_id = :auth_id
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return OwnerDetail::fromArray($row);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function existsByAuthId(int $authId): bool
    {
        $sql = "SELECT COUNT(1) FROM owners WHERE auth_id = :auth_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function create(Owner $owner): int
    {
        $sql = "INSERT INTO owners (auth_id, first_name, last_name, phone, avatar_id, created_at, updated_at)
                VALUES (:auth_id, :first_name, :last_name, :phone, :avatar_id, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':auth_id'    => $owner->authId,
                ':first_name' => $owner->firstName,
                ':last_name'  => $owner->lastName,
                ':phone'      => $owner->phone,
                ':avatar_id'  => $owner->avatarId,
            ]);

            $id = (int) $stmt->fetchColumn();
            $owner->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    /**
     * Actualiza directamente los campos de dueño por auth_id.
     *
     * @param array<string, mixed> $data
     */
    public function updateByAuthId(int $authId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $fields = [];
        $params = [':auth_id' => $authId];

        foreach ($data as $col => $val) {
            $fields[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }
        $fields[] = "updated_at = NOW()";

        $setClause = implode(', ', $fields);
        $sql = "UPDATE owners SET {$setClause} WHERE auth_id = :auth_id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Perfil de dueño no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateAvatarByAuthId(int $authId, int $avatarId): void
    {
        $sql = "UPDATE owners SET avatar_id = :avatar_id, updated_at = NOW() WHERE auth_id = :auth_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':avatar_id' => $avatarId,
                ':auth_id'   => $authId,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Dueño no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function createFile(FileModel $file): int
    {
        $sql = "INSERT INTO files (slug, path, mime_type, size, author_id, created_at, updated_at)
                VALUES (:slug, :path, :mime_type, :size, :author_id, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':slug'      => $file->slug,
                ':path'      => $file->path,
                ':mime_type' => $file->mimeType,
                ':size'      => $file->size,
                ':author_id' => $file->authorId,
            ]);

            $id = (int) $stmt->fetchColumn();
            $file->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function markAuthProfileCompleted(int $authId): void
    {
        $sql = "UPDATE auths SET full_profile = true, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':id' => $authId]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Registro de autenticación no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }
}

