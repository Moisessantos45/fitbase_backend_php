<?php

declare(strict_types=1);

namespace App\Features\Manager;

use App\Config\Database;
use App\Models\Auth;
use App\Models\CashRegister;
use App\Models\FileModel;
use App\Models\GymManager;
use App\Shared\Errors\NotFoundException;
use App\Shared\Helpers\ErrorHandler;
use PDO;
use PDOException;
use Throwable;

class ManagerRepository
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

    public function getGymIdByOwnerAuthId(int $authId): ?int
    {
        $sql = "SELECT g.id 
                FROM gyms g
                INNER JOIN owners o ON o.id = g.owner_id
                WHERE o.auth_id = :auth_id
                ORDER BY g.id ASC
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int) $val : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function existsAuthByEmail(string $email): bool
    {
        $sql = "SELECT COUNT(1) FROM auths WHERE LOWER(email) = LOWER(:email)";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':email' => trim($email)]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function createManagerWithAuth(Auth $auth, GymManager $manager): void
    {
        $this->withTransaction(function () use ($auth, $manager) {
            $sqlAuth = "INSERT INTO auths (
                            role_id, email, password_hash, full_profile, email_confirmed, active, created_at, updated_at
                        ) VALUES (
                            :role_id, :email, :password_hash, :full_profile, :email_confirmed, :active, NOW(), NOW()
                        ) RETURNING id";

            $stmtAuth = $this->db()->prepare($sqlAuth);
            $stmtAuth->execute([
                ':role_id'         => $auth->roleId,
                ':email'           => $auth->email,
                ':password_hash'   => $auth->passwordHash,
                ':full_profile'    => $auth->fullProfile ? 'true' : 'false',
                ':email_confirmed' => $auth->emailConfirmed ? 'true' : 'false',
                ':active'          => $auth->active ? 'true' : 'false',
            ]);

            $authId = (int) $stmtAuth->fetchColumn();
            $auth->id = $authId;
            $manager->authId = $authId;

            $sqlManager = "INSERT INTO gym_managers (
                               gym_id, auth_id, created_by_auth_id, first_name, last_name, phone, created_at, updated_at
                           ) VALUES (
                               :gym_id, :auth_id, :created_by_auth_id, :first_name, :last_name, :phone, NOW(), NOW()
                           ) RETURNING id";

            $stmtManager = $this->db()->prepare($sqlManager);
            $stmtManager->execute([
                ':gym_id'             => $manager->gymId,
                ':auth_id'            => $manager->authId,
                ':created_by_auth_id' => $manager->createdByAuthId,
                ':first_name'         => $manager->firstName,
                ':last_name'          => $manager->lastName,
                ':phone'              => $manager->phone,
            ]);

            $manager->id = (int) $stmtManager->fetchColumn();
        });
    }

    public function getAllByOwnerAuthId(int $authId, int $offset, int $limit, string $query = ''): array
    {
        try {
            $whereParts = ["gm.created_by_auth_id = :auth_id"];
            $params = [':auth_id' => $authId];

            if ($query !== '') {
                $whereParts[] = "(gm.first_name ILIKE :q OR gm.last_name ILIKE :q OR a.email ILIKE :q OR gm.phone ILIKE :q)";
                $params[':q'] = "%{$query}%";
            }

            $whereSql = implode(' AND ', $whereParts);

            $countSql = "SELECT COUNT(1) 
                         FROM gym_managers gm
                         INNER JOIN auths a ON a.id = gm.auth_id
                         WHERE {$whereSql}";

            $countStmt = $this->db()->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT 
                        gm.id,
                        gm.gym_id,
                        g.name AS gym_name,
                        gm.auth_id,
                        gm.created_by_auth_id,
                        gm.first_name,
                        gm.last_name,
                        gm.phone,
                        a.email,
                        COALESCE(f.slug, '') AS slug,
                        gm.created_at
                    FROM gym_managers gm
                    INNER JOIN auths a ON a.id = gm.auth_id
                    INNER JOIN gyms g ON g.id = gm.gym_id
                    LEFT JOIN files f ON f.id = gm.avatar_id
                    WHERE {$whereSql}
                    ORDER BY gm.created_at DESC
                    OFFSET :offset LIMIT :limit";

            $stmt = $this->db()->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(fn($r) => ManagerItems::fromArray($r), $rows);

            return ['data' => $items, 'total' => $total];
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getDetailByAuthId(int $authId): ?ManagerDetail
    {
        $sql = "SELECT 
                    gm.id,
                    gm.auth_id,
                    gm.first_name,
                    gm.last_name,
                    gm.phone,
                    COALESCE(f.slug, '') AS slug
                FROM gym_managers gm
                INNER JOIN auths a ON a.id = gm.auth_id
                LEFT JOIN files f ON f.id = gm.avatar_id
                WHERE gm.auth_id = :auth_id
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return ManagerDetail::fromArray($row);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function update(int $authId, array $data): void
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
        $sql = "UPDATE gym_managers SET {$setClause} WHERE auth_id = :auth_id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Encargado no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateAvatarByAuthId(int $authId, int $avatarId): void
    {
        $sql = "UPDATE gym_managers SET avatar_id = :avatar_id, updated_at = NOW() WHERE auth_id = :auth_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':avatar_id' => $avatarId, ':auth_id' => $authId]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Encargado no encontrado');
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

    public function getActiveCashRegister(int $gymId, int $authId): ?CashRegister
    {
        $sql = "SELECT * FROM cash_registers 
                WHERE gym_id = :gym_id AND auth_id = :auth_id AND close_time IS NULL 
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':gym_id' => $gymId, ':auth_id' => $authId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? CashRegister::fromArray($row) : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getActiveCashRegisterByAuthId(int $authId): ?CashRegister
    {
        $sql = "SELECT * FROM cash_registers 
                WHERE auth_id = :auth_id AND close_time IS NULL 
                ORDER BY id DESC LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? CashRegister::fromArray($row) : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function openCashRegister(CashRegister $register): int
    {
        $sql = "INSERT INTO cash_registers (gym_id, auth_id, open_time, open_cash, created_at, updated_at)
                VALUES (:gym_id, :auth_id, NOW(), :open_cash, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':gym_id'    => $register->gymId,
                ':auth_id'   => $register->authId,
                ':open_cash' => $register->openCash,
            ]);

            $id = (int) $stmt->fetchColumn();
            $register->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function closeCashRegister(int $registerId, float $closeCash, string $closeTime): void
    {
        $sql = "UPDATE cash_registers 
                SET close_cash = :close_cash, close_time = :close_time, updated_at = NOW()
                WHERE id = :id AND close_time IS NULL";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':close_cash' => $closeCash,
                ':close_time' => $closeTime,
                ':id'         => $registerId,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Caja no encontrada o ya fue cerrada previamente');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }
}

