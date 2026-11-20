<?php

declare(strict_types=1);

namespace App\Features\Subscription;

use App\Config\Database;
use App\Models\StatusSubscription;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class SubscriptionRepository
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

    public function create(array $sub): string
    {
        try {
            $sql = 'INSERT INTO subscriptions (
                        user_id, plan_id, status_id, payment_method_id,
                        start_date, end_date, auto_renew, created_at, updated_at
                    ) VALUES (
                        :user_id, :plan_id, :status_id, :payment_method_id,
                        :start_date, :end_date, :auto_renew, NOW(), NOW()
                    ) RETURNING slug';

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'user_id' => $sub['user_id'],
                'plan_id' => $sub['plan_id'],
                'status_id' => $sub['status_id'],
                'payment_method_id' => $sub['payment_method_id'],
                'start_date' => $sub['start_date'],
                'end_date' => $sub['end_date'],
                'auto_renew' => $sub['auto_renew'] ? 'true' : 'false',
            ]);

            return (string)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM subscriptions WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getBySlug(string $slug): ?array
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

    public function getTicketBySlug(string $slug): ?array
    {
        try {
            $sql = "
                SELECT
                    subscriptions.id,
                    subscriptions.slug::text AS slug,
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    plans.id AS plan_id,
                    plans.name AS plan_name,
                    plans.price AS plan_price,
                    TRIM(CONCAT(users.first_name, ' ', users.last_name)) AS full_name,
                    auths.email AS email,
                    status_subscriptions.id AS status_id,
                    status_subscriptions.name AS status_name,
                    COALESCE(TO_CHAR(subscriptions.start_date, 'YYYY-MM-DD'), '') AS start_date,
                    COALESCE(TO_CHAR(subscriptions.end_date, 'YYYY-MM-DD'), '') AS end_date,
                    subscriptions.auto_renew,
                    subscriptions.created_at
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                JOIN gyms ON gyms.id = plans.gym_id
                JOIN users ON users.id = subscriptions.user_id
                JOIN auths ON auths.id = users.auth_id
                JOIN status_subscriptions ON status_subscriptions.id = subscriptions.status_id
                WHERE subscriptions.slug = :slug::uuid
                LIMIT 1
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['slug' => $slug]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getTicketById(int $id): ?array
    {
        try {
            $sql = "
                SELECT
                    subscriptions.id,
                    subscriptions.slug::text AS slug,
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    plans.id AS plan_id,
                    plans.name AS plan_name,
                    plans.price AS plan_price,
                    TRIM(CONCAT(users.first_name, ' ', users.last_name)) AS full_name,
                    auths.email AS email,
                    status_subscriptions.id AS status_id,
                    status_subscriptions.name AS status_name,
                    COALESCE(TO_CHAR(subscriptions.start_date, 'YYYY-MM-DD'), '') AS start_date,
                    COALESCE(TO_CHAR(subscriptions.end_date, 'YYYY-MM-DD'), '') AS end_date,
                    subscriptions.auto_renew,
                    subscriptions.created_at
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                JOIN gyms ON gyms.id = plans.gym_id
                JOIN users ON users.id = subscriptions.user_id
                JOIN auths ON auths.id = users.auth_id
                JOIN status_subscriptions ON status_subscriptions.id = subscriptions.status_id
                WHERE subscriptions.id = :id
                LIMIT 1
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getActiveByUserId(int $userId, int $gymId): ?array
    {
        try {
            $activeId = StatusSubscription::ACTIVE;
            $inactiveId = StatusSubscription::INACTIVE;

            $sql = '
                SELECT subscriptions.*
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                WHERE subscriptions.user_id = :user_id
                  AND plans.gym_id = :gym_id
                  AND subscriptions.status_id IN (:status1, :status2)
                  AND subscriptions.end_date >= CURRENT_DATE
                ORDER BY subscriptions.end_date DESC
                LIMIT 1
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'gym_id' => $gymId,
                'status1' => $activeId,
                'status2' => $inactiveId,
            ]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByGymId(int $gymId, int $statusId, int $offset, int $limit): array
    {
        try {
            $whereClause = 'WHERE plans.gym_id = :gym_id';
            $params = ['gym_id' => $gymId];

            if ($statusId > 0) {
                $whereClause .= ' AND subscriptions.status_id = :status_id';
                $params['status_id'] = $statusId;
            }

            $countSql = "
                SELECT COUNT(*)
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                {$whereClause}
            ";
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = "
                SELECT
                    subscriptions.id,
                    subscriptions.slug::text AS slug,
                    subscriptions.user_id,
                    TRIM(CONCAT(users.first_name, ' ', users.last_name)) AS user_full_name,
                    auths.email AS user_email,
                    users.phone AS user_phone,
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    plans.id AS plan_id,
                    plans.name AS plan_name,
                    plans.price AS plan_price,
                    status_subscriptions.id AS status_id,
                    status_subscriptions.name AS status_name,
                    COALESCE(TO_CHAR(subscriptions.start_date, 'YYYY-MM-DD'), '') AS start_date,
                    COALESCE(TO_CHAR(subscriptions.end_date, 'YYYY-MM-DD'), '') AS end_date,
                    payment_methods.id AS payment_method_id,
                    payment_methods.name AS payment_method_name,
                    subscriptions.auto_renew,
                    subscriptions.created_at
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                JOIN gyms ON gyms.id = plans.gym_id
                JOIN users ON users.id = subscriptions.user_id
                JOIN auths ON auths.id = users.auth_id
                JOIN status_subscriptions ON status_subscriptions.id = subscriptions.status_id
                JOIN payment_methods ON payment_methods.id = subscriptions.payment_method_id
                {$whereClause}
                ORDER BY subscriptions.created_at DESC
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
            $countSql = 'SELECT COUNT(*) FROM subscriptions WHERE user_id = :user_id';
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute(['user_id' => $userId]);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = "
                SELECT
                    subscriptions.id,
                    subscriptions.slug::text AS slug,
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    plans.id AS plan_id,
                    plans.name AS plan_name,
                    plans.price AS plan_price,
                    status_subscriptions.id AS status_id,
                    status_subscriptions.name AS status_name,
                    COALESCE(TO_CHAR(subscriptions.start_date, 'YYYY-MM-DD'), '') AS start_date,
                    COALESCE(TO_CHAR(subscriptions.end_date, 'YYYY-MM-DD'), '') AS end_date,
                    subscriptions.auto_renew,
                    subscriptions.created_at
                FROM subscriptions
                JOIN plans ON plans.id = subscriptions.plan_id
                JOIN gyms ON gyms.id = plans.gym_id
                JOIN status_subscriptions ON status_subscriptions.id = subscriptions.status_id
                WHERE subscriptions.user_id = :user_id
                ORDER BY subscriptions.created_at DESC
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

    public function updateStatus(int $id, int $statusId): bool
    {
        try {
            $stmt = $this->db()->prepare('UPDATE subscriptions SET status_id = :status_id, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id, 'status_id' => $statusId]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function renew(int $id, string $newStartDate, string $newEndDate, int $paymentMethodId): bool
    {
        try {
            $activeId = StatusSubscription::ACTIVE;
            $stmt = $this->db()->prepare('
                UPDATE subscriptions
                SET start_date = :start_date,
                    end_date = :end_date,
                    status_id = :status_id,
                    payment_method_id = :payment_method_id,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $id,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'status_id' => $activeId,
                'payment_method_id' => $paymentMethodId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function cancel(int $id): bool
    {
        try {
            $inactiveId = StatusSubscription::INACTIVE;
            $stmt = $this->db()->prepare('
                UPDATE subscriptions
                SET status_id = :status_id, auto_renew = false, updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $id,
                'status_id' => $inactiveId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getUserDetailsByAuthId(int $authId): ?array
    {
        try {
            $sql = "
                SELECT
                    users.id,
                    TRIM(CONCAT(users.first_name, ' ', users.last_name)) AS full_name,
                    users.phone,
                    auths.email
                FROM users
                JOIN auths ON auths.id = users.auth_id
                WHERE users.auth_id = :auth_id
                LIMIT 1
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['auth_id' => $authId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
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
}

