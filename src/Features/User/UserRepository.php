<?php

declare(strict_types=1);

namespace App\Features\User;

use App\Config\Database;
use App\Models\FileModel;
use App\Models\User;
use App\Shared\Errors\NotFoundException;
use App\Shared\Helpers\ErrorHandler;
use PDO;
use PDOException;
use Throwable;

class UserRepository
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

    public function getIdByAuthId(int $authId): ?int
    {
        $sql = "SELECT id FROM users WHERE auth_id = :auth_id LIMIT 1";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int) $val : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getByAuthId(int $authId): ?UserDetail
    {
        $sql = "SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    a.email,
                    f.slug AS slug
                FROM users u
                INNER JOIN auths a ON a.id = u.auth_id
                LEFT JOIN files f ON f.id = u.avatar_id
                WHERE u.auth_id = :auth_id
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return UserDetail::fromArray($row);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function create(User $user): int
    {
        $sql = "INSERT INTO users (auth_id, first_name, last_name, phone, avatar_id, created_at, updated_at)
                VALUES (:auth_id, :first_name, :last_name, :phone, :avatar_id, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':auth_id'    => $user->authId,
                ':first_name' => $user->firstName,
                ':last_name'  => $user->lastName,
                ':phone'      => $user->phone,
                ':avatar_id'  => $user->avatarId,
            ]);

            $id = (int) $stmt->fetchColumn();
            $user->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    /**
     * Actualiza directamente los campos de usuario por auth_id sin consultas intermedias.
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
        $sql = "UPDATE users SET {$setClause} WHERE auth_id = :auth_id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Perfil de usuario no encontrado para actualizar');
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

    public function updateAvatarByAuthId(int $authId, int $avatarId): void
    {
        $sql = "UPDATE users SET avatar_id = :avatar_id, updated_at = NOW() WHERE auth_id = :auth_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':avatar_id' => $avatarId,
                ':auth_id'   => $authId,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Usuario no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function changeCompletedProfile(int $authId, bool $completed): void
    {
        $sql = "UPDATE auths SET full_profile = :full_profile, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':full_profile' => $completed ? 'true' : 'false',
                ':id'           => $authId,
            ]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }
}

