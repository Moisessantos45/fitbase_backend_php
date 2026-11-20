<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Config\Database;
use App\Models\Auth;
use App\Models\Role;
use App\Shared\Errors\AppException;
use App\Shared\Helpers\ErrorHandler;
use PDO;
use PDOException;
use Throwable;

class AuthRepository
{
    public function __construct(private ?PDO $db = null)
    {
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }
        return $this->db;
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

    public function register(Auth $user): int
    {
        $sql = "INSERT INTO auths (
                    role_id, email, password_hash, two_factor_secret, two_factor_enabled, 
                    first_session, full_profile, email_confirmed, active, created_at, updated_at
                ) VALUES (
                    :role_id, :email, :password_hash, :two_factor_secret, :two_factor_enabled, 
                    :first_session, :full_profile, :email_confirmed, :active, NOW(), NOW()
                ) RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':role_id'            => $user->roleId,
                ':email'              => $user->email,
                ':password_hash'      => $user->passwordHash,
                ':two_factor_secret'  => $user->twoFactorSecret,
                ':two_factor_enabled' => $user->twoFactorEnabled ? 'true' : 'false',
                ':first_session'      => $user->firstSession ? 'true' : 'false',
                ':full_profile'       => $user->fullProfile ? 'true' : 'false',
                ':email_confirmed'    => $user->emailConfirmed ? 'true' : 'false',
                ':active'             => $user->active ? 'true' : 'false',
            ]);

            $id = (int) $stmt->fetchColumn();
            $user->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function existsByEmail(string $email): bool
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

    public function getByEmailAny(string $email): ?Auth
    {
        $sql = "SELECT a.*, r.id AS r_id, r.name AS r_name 
                FROM auths a
                LEFT JOIN roles r ON r.id = a.role_id
                WHERE LOWER(a.email) = LOWER(:email) 
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':email' => trim($email)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapRowToAuth($row) : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getActiveUserByEmail(string $email): array
    {
        $user = $this->getByEmailAny($email);
        if ($user === null) {
            return ['user' => null, 'status' => 'not_found'];
        }

        if (!$user->active) {
            return ['user' => null, 'status' => 'account_inactive'];
        }

        if (!$user->emailConfirmed) {
            return ['user' => $user, 'status' => 'email_not_verified'];
        }

        return ['user' => $user, 'status' => 'ok'];
    }

    public function getById(int $userId): ?Auth
    {
        $sql = "SELECT a.*, r.id AS r_id, r.name AS r_name 
                FROM auths a
                LEFT JOIN roles r ON r.id = a.role_id
                WHERE a.id = :id AND a.active = true AND a.email_confirmed = true
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapRowToAuth($row) : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateEmailConfirm(int $userId): bool
    {
        $sql = "UPDATE auths SET email_confirmed = true, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            return $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $sql = "UPDATE auths SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            return $stmt->execute([
                ':id'            => $userId,
                ':password_hash' => $hashedPassword,
            ]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateEmail(int $userId, string $email): bool
    {
        $sql = "UPDATE auths SET email = :email, email_confirmed = false, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':id'    => $userId,
                ':email' => trim($email),
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function changeStatus(int $userId, bool $active): bool
    {
        $sql = "UPDATE auths SET active = :active, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            return $stmt->execute([
                ':id'     => $userId,
                ':active' => $active ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    private function mapRowToAuth(array $row): Auth
    {
        $role = null;
        if (!empty($row['r_id'])) {
            $role = new Role(
                id: (int) $row['r_id'],
                name: (string) ($row['r_name'] ?? '')
            );
        }

        return new Auth(
            id: (int) $row['id'],
            roleId: (int) $row['role_id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            twoFactorSecret: $row['two_factor_secret'] ?? null,
            twoFactorEnabled: filter_var($row['two_factor_enabled'], FILTER_VALIDATE_BOOLEAN),
            firstSession: filter_var($row['first_session'], FILTER_VALIDATE_BOOLEAN),
            fullProfile: filter_var($row['full_profile'], FILTER_VALIDATE_BOOLEAN),
            emailConfirmed: filter_var($row['email_confirmed'], FILTER_VALIDATE_BOOLEAN),
            active: filter_var($row['active'], FILTER_VALIDATE_BOOLEAN),
            token: null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            role: $role
        );
    }
}
