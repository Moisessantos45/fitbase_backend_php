<?php

declare(strict_types=1);

namespace App\Features\CheckIn;

use App\Config\Database;
use App\Models\PaymentStatus;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class CheckInRepository
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

    public function beginTransaction(): bool
    {
        return $this->db()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->db()->commit();
    }

    public function rollBack(): bool
    {
        return $this->db()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->db()->inTransaction();
    }

    public function register(array $checkIn): int
    {
        try {
            $sql = 'INSERT INTO check_ins (
                        gym_id, access_type_id, user_id, day_pass_id, subscription_id, registered_by_id, checked_in_at
                    ) VALUES (
                        :gym_id, :access_type_id, :user_id, :day_pass_id, :subscription_id, :registered_by_id, NOW()
                    ) RETURNING id';

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $checkIn['gym_id'],
                'access_type_id' => $checkIn['access_type_id'],
                'user_id' => $checkIn['user_id'] ?? null,
                'day_pass_id' => $checkIn['day_pass_id'] ?? null,
                'subscription_id' => $checkIn['subscription_id'] ?? null,
                'registered_by_id' => $checkIn['registered_by_id'] ?? null,
            ]);

            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByGymId(int $gymId, ?string $from, ?string $to, int $offset, int $limit): array
    {
        try {
            $whereClause = 'WHERE check_ins.gym_id = :gym_id';
            $params = ['gym_id' => $gymId];

            if (!empty($from)) {
                $whereClause .= ' AND check_ins.checked_in_at >= :from_date';
                $params['from_date'] = $from;
            }

            if (!empty($to)) {
                $whereClause .= ' AND check_ins.checked_in_at <= :to_date';
                $params['to_date'] = $to;
            }

            $countSql = "SELECT COUNT(*) FROM check_ins {$whereClause}";
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = "
                SELECT
                    check_ins.id,
                    check_ins.gym_id,
                    gyms.name AS gym_name,
                    check_ins.access_type_id,
                    access_types.name AS access_type_name,
                    check_ins.user_id,
                    COALESCE(TRIM(CONCAT(users.first_name, ' ', users.last_name)), dp.full_name, 'Socio') AS user_full_name,
                    check_ins.day_pass_id,
                    check_ins.subscription_id,
                    check_ins.registered_by_id,
                    COALESCE(TRIM(CONCAT(reg_m.first_name, ' ', reg_m.last_name)), TRIM(CONCAT(reg_o.first_name, ' ', reg_o.last_name)), 'Sistema') AS registered_by_name,
                    check_ins.checked_in_at
                FROM check_ins
                JOIN gyms ON gyms.id = check_ins.gym_id
                JOIN access_types ON access_types.id = check_ins.access_type_id
                LEFT JOIN users ON users.id = check_ins.user_id
                LEFT JOIN day_passes dp ON dp.id = check_ins.day_pass_id
                LEFT JOIN gym_managers reg_m ON reg_m.auth_id = check_ins.registered_by_id
                LEFT JOIN owners reg_o ON reg_o.auth_id = check_ins.registered_by_id
                {$whereClause}
                ORDER BY check_ins.checked_in_at DESC
                OFFSET :offset LIMIT :limit
            ";

            $stmtData = $this->db()->prepare($dataSql);
            foreach ($params as $k => $v) {
                $stmtData->bindValue($k, $v);
            }
            $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmtData->execute();

            $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            return [
                'data' => $rows,
                'total' => $total,
            ];
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByUserId(int $userId, int $offset, int $limit): array
    {
        try {
            $countSql = 'SELECT COUNT(*) FROM check_ins WHERE user_id = :user_id';
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute(['user_id' => $userId]);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = "
                SELECT
                    check_ins.id,
                    check_ins.gym_id,
                    gyms.name AS gym_name,
                    check_ins.access_type_id,
                    access_types.name AS access_type_name,
                    check_ins.user_id,
                    TRIM(CONCAT(users.first_name, ' ', users.last_name)) AS user_full_name,
                    check_ins.day_pass_id,
                    check_ins.subscription_id,
                    check_ins.registered_by_id,
                    COALESCE(TRIM(CONCAT(reg_m.first_name, ' ', reg_m.last_name)), TRIM(CONCAT(reg_o.first_name, ' ', reg_o.last_name)), 'Sistema') AS registered_by_name,
                    check_ins.checked_in_at
                FROM check_ins
                JOIN gyms ON gyms.id = check_ins.gym_id
                JOIN access_types ON access_types.id = check_ins.access_type_id
                JOIN users ON users.id = check_ins.user_id
                LEFT JOIN gym_managers reg_m ON reg_m.auth_id = check_ins.registered_by_id
                LEFT JOIN owners reg_o ON reg_o.auth_id = check_ins.registered_by_id
                WHERE check_ins.user_id = :user_id
                ORDER BY check_ins.checked_in_at DESC
                OFFSET :offset LIMIT :limit
            ";

            $stmtData = $this->db()->prepare($dataSql);
            $stmtData->bindValue('user_id', $userId, PDO::PARAM_INT);
            $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmtData->execute();

            $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            return [
                'data' => $rows,
                'total' => $total,
            ];
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getDayPassBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM day_passes WHERE slug = :slug::uuid LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getSubscriptionBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM subscriptions WHERE slug = :slug::uuid LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getSubscriptionByEmail(string $email, int $gymId): ?array
    {
        try {
            $sql = '
                SELECT s.*
                FROM subscriptions s
                JOIN users u ON u.id = s.user_id
                JOIN auths a ON a.id = u.auth_id
                JOIN plans p ON p.id = s.plan_id
                WHERE LOWER(a.email) = :email AND p.gym_id = :gym_id
                ORDER BY s.end_date DESC
                LIMIT 1
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['email' => $email, 'gym_id' => $gymId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getDayPassByEmail(string $email, int $gymId): ?array
    {
        try {
            $sql = '
                SELECT dp.*
                FROM day_passes dp
                WHERE LOWER(dp.email) = :email AND dp.gym_id = :gym_id AND dp.is_used = false
                ORDER BY dp.created_at DESC
                LIMIT 1
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['email' => $email, 'gym_id' => $gymId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function isDayPassPaid(int $dayPassId): bool
    {
        try {
            $paidStatus = PaymentStatus::PAID;
            $stmt = $this->db()->prepare('
                SELECT 1 FROM payments
                WHERE day_pass_id = :dp_id AND payment_status_id = :paid_status
                LIMIT 1
            ');
            $stmt->execute([
                'dp_id' => $dayPassId,
                'paid_status' => $paidStatus,
            ]);
            return (bool)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function markDayPassAsUsed(int $id): bool
    {
        try {
            $stmt = $this->db()->prepare('UPDATE day_passes SET is_used = true, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getSchedulesByGymId(int $gymId): array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM gym_schedules WHERE gym_id = :gym_id');
            $stmt->execute(['gym_id' => $gymId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getPlanById(int $planId): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM plans WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $planId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getUserDetailsByUserId(int $userId): ?string
    {
        try {
            $stmt = $this->db()->prepare("SELECT TRIM(CONCAT(first_name, ' ', last_name)) FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $userId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string)$val : null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getGymOwnerId(int $gymId): ?int
    {
        try {
            $stmt = $this->db()->prepare('SELECT owner_id FROM gyms WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $gymId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getOwnerIdByAuthId(int $authId): ?int
    {
        try {
            $stmt = $this->db()->prepare('SELECT id FROM owners WHERE auth_id = :auth_id LIMIT 1');
            $stmt->execute(['auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getManagerGymId(int $authId): ?int
    {
        try {
            $stmt = $this->db()->prepare('SELECT gym_id FROM gym_managers WHERE auth_id = :auth_id LIMIT 1');
            $stmt->execute(['auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getUserDetailsByAuthId(int $authId): ?array
    {
        try {
            $stmt = $this->db()->prepare("SELECT id, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users WHERE auth_id = :auth_id LIMIT 1");
            $stmt->execute(['auth_id' => $authId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }
}

