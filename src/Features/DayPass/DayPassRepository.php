<?php

declare(strict_types=1);

namespace App\Features\DayPass;

use App\Config\Database;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class DayPassRepository
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

    public function create(array $dp): string
    {
        try {
            $sql = 'INSERT INTO day_passes (
                        gym_id, plan_id, user_id, full_name, phone, email,
                        visit_date, is_used, created_at, updated_at
                    ) VALUES (
                        :gym_id, :plan_id, :user_id, :full_name, :phone, :email,
                        :visit_date, false, NOW(), NOW()
                    ) RETURNING slug';

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $dp['gym_id'],
                'plan_id' => $dp['plan_id'],
                'user_id' => $dp['user_id'] ?? null,
                'full_name' => $dp['full_name'],
                'phone' => $dp['phone'],
                'email' => $dp['email'],
                'visit_date' => $dp['visit_date'],
            ]);

            return (string)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getBySlug(string $slug): ?array
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

    public function getTicketBySlug(string $slug): ?array
    {
        try {
            $sql = "
                SELECT
                    day_passes.id,
                    day_passes.slug::text AS slug,
                    day_passes.gym_id,
                    gyms.name AS gym_name,
                    day_passes.plan_id,
                    plans.name AS plan_name,
                    COALESCE(plans.price, 0) AS plan_price,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM payments p WHERE p.day_pass_id = day_passes.id AND p.payment_status_id = 2
                    ) THEN true ELSE false END AS is_paid,
                    day_passes.full_name,
                    day_passes.phone,
                    day_passes.email,
                    COALESCE(TO_CHAR(day_passes.visit_date, 'YYYY-MM-DD'), '') AS visit_date,
                    day_passes.is_used,
                    day_passes.created_at
                FROM day_passes
                JOIN gyms ON gyms.id = day_passes.gym_id
                JOIN plans ON plans.id = day_passes.plan_id
                WHERE day_passes.slug = :slug::uuid
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

    public function markAsUsed(int $id): bool
    {
        try {
            $stmt = $this->db()->prepare('UPDATE day_passes SET is_used = true, updated_at = NOW() WHERE id = :id AND is_used = false');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByGymId(int $gymId, ?string $date, ?string $search, int $offset, int $limit): array
    {
        try {
            $whereClause = 'WHERE day_passes.gym_id = :gym_id';
            $params = ['gym_id' => $gymId];

            if (!empty($date)) {
                $whereClause .= ' AND day_passes.visit_date = :visit_date';
                $params['visit_date'] = $date;
            }

            if (!empty($search)) {
                $term = '%' . mb_strtolower(trim($search)) . '%';
                $whereClause .= ' AND (
                    LOWER(day_passes.full_name) LIKE :s1 OR
                    LOWER(day_passes.email) LIKE :s2 OR
                    day_passes.phone LIKE :s3 OR
                    day_passes.slug::text LIKE :s4
                )';
                $params['s1'] = $term;
                $params['s2'] = $term;
                $params['s3'] = $term;
                $params['s4'] = $term;
            }

            $countSql = "SELECT COUNT(*) FROM day_passes {$whereClause}";
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = "
                SELECT
                    day_passes.id,
                    day_passes.slug::text AS slug,
                    day_passes.user_id,
                    day_passes.gym_id,
                    gyms.name AS gym_name,
                    day_passes.plan_id,
                    plans.name AS plan_name,
                    COALESCE(plans.price, 0) AS plan_price,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM payments p WHERE p.day_pass_id = day_passes.id AND p.payment_status_id = 2
                    ) THEN true ELSE false END AS is_paid,
                    day_passes.full_name,
                    day_passes.phone,
                    day_passes.email,
                    COALESCE(TO_CHAR(day_passes.visit_date, 'YYYY-MM-DD'), '') AS visit_date,
                    day_passes.is_used,
                    day_passes.created_at
                FROM day_passes
                JOIN gyms ON gyms.id = day_passes.gym_id
                JOIN plans ON plans.id = day_passes.plan_id
                {$whereClause}
                ORDER BY day_passes.created_at DESC
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
}

