<?php

declare(strict_types=1);

namespace App\Features\Plan;

use App\Config\Database;
use App\Models\StatusSubscription;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class PlanRepository
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

    public function createWithItems(array $plan, array $items): int
    {
        try {
            $this->db()->beginTransaction();

            $sql = 'INSERT INTO plans (gym_id, name, description, price, discount, duration, plan_type, max_visits, active, created_at, updated_at)
                    VALUES (:gym_id, :name, :description, :price, :discount, :duration, :plan_type, :max_visits, :active, NOW(), NOW())
                    RETURNING id';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $plan['gym_id'],
                'name' => $plan['name'],
                'description' => $plan['description'],
                'price' => $plan['price'],
                'discount' => $plan['discount'] ?? 0.0,
                'duration' => $plan['duration'],
                'plan_type' => $plan['plan_type'],
                'max_visits' => $plan['max_visits'] ?? null,
                'active' => $plan['active'] ?? true,
            ]);

            $planId = (int)$stmt->fetchColumn();

            if (!empty($items)) {
                $itemSql = 'INSERT INTO plan_items (plan_id, name, description, created_at, updated_at)
                            VALUES (:plan_id, :name, :description, NOW(), NOW())';
                $itemStmt = $this->db()->prepare($itemSql);
                foreach ($items as $item) {
                    $itemStmt->execute([
                        'plan_id' => $planId,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            }

            $this->db()->commit();
            return $planId;
        } catch (\PDOException $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM plans WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getDetailsById(int $id): ?array
    {
        try {
            $sql = "
                SELECT
                    plans.id,
                    plans.gym_id,
                    gyms.name AS gym_name,
                    plans.name,
                    plans.description,
                    plans.price,
                    plans.discount,
                    (plans.price * (1 - (plans.discount / 100.0))) AS final_price,
                    plans.duration,
                    plans.plan_type,
                    plans.max_visits,
                    plans.active,
                    COALESCE(
                        jsonb_agg(
                            DISTINCT jsonb_build_object(
                                'id', pi.id,
                                'name', pi.name,
                                'description', pi.description
                            )
                        ) FILTER (WHERE pi.id IS NOT NULL),
                        '[]'::jsonb
                    ) AS items
                FROM plans
                JOIN gyms ON gyms.id = plans.gym_id
                LEFT JOIN plan_items pi ON pi.plan_id = plans.id
                WHERE plans.id = :id
                GROUP BY
                    plans.id,
                    plans.gym_id,
                    gyms.name,
                    plans.name,
                    plans.description,
                    plans.price,
                    plans.discount,
                    plans.duration,
                    plans.plan_type,
                    plans.max_visits,
                    plans.active
                LIMIT 1
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res) {
                return null;
            }
            return PlanDetailResponse::fromArray($res)->toArray();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByGymId(int $gymId, bool $activeOnly = false): array
    {
        try {
            $whereClause = 'WHERE plans.gym_id = :gym_id';
            if ($activeOnly) {
                $whereClause .= ' AND plans.active = true';
            }

            $sql = "
                SELECT
                    plans.id,
                    plans.gym_id,
                    gyms.name AS gym_name,
                    plans.name,
                    plans.description,
                    plans.price,
                    plans.discount,
                    (plans.price * (1 - (plans.discount / 100.0))) AS final_price,
                    plans.duration,
                    plans.plan_type,
                    plans.max_visits,
                    plans.active,
                    COALESCE(
                        jsonb_agg(
                            DISTINCT jsonb_build_object(
                                'id', pi.id,
                                'name', pi.name,
                                'description', pi.description
                            )
                        ) FILTER (WHERE pi.id IS NOT NULL),
                        '[]'::jsonb
                    ) AS items
                FROM plans
                JOIN gyms ON gyms.id = plans.gym_id
                LEFT JOIN plan_items pi ON pi.plan_id = plans.id
                {$whereClause}
                GROUP BY plans.id, gyms.name
                ORDER BY plans.price ASC
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['gym_id' => $gymId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($row) => PlanDetailResponse::fromArray($row)->toArray(), $rows);
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getEnrollmentPlans(int $authId, int $gymId): ?array
    {
        try {
            $activeId = StatusSubscription::ACTIVE;
            $inactiveId = StatusSubscription::INACTIVE;

            $sql = "
                SELECT
                    g.id AS gym_id,
                    g.name AS gym_name,
                    CASE WHEN active_sub.id IS NOT NULL THEN true ELSE false END AS has_active_subscription,
                    CASE WHEN active_sub.id IS NOT NULL THEN
                        jsonb_build_object(
                            'id', active_sub.id,
                            'slug', active_sub.slug::text,
                            'plan_id', active_sub.plan_id,
                            'plan_name', active_sub.plan_name,
                            'start_date', COALESCE(TO_CHAR(active_sub.start_date, 'YYYY-MM-DD'), ''),
                            'end_date', COALESCE(TO_CHAR(active_sub.end_date, 'YYYY-MM-DD'), ''),
                            'remaining_days', GREATEST(0, (active_sub.end_date - CURRENT_DATE) + 1),
                            'status_name', active_sub.status_name,
                            'auto_renew', active_sub.auto_renew
                        )
                    ELSE NULL END AS active_subscription,
                    COALESCE((
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'id', p.id,
                                'gym_id', p.gym_id,
                                'gym_name', g.name,
                                'name', p.name,
                                'description', p.description,
                                'price', p.price,
                                'discount', p.discount,
                                'final_price', ROUND((p.price * (1 - (p.discount / 100.0)))::numeric, 2),
                                'duration', p.duration,
                                'plan_type', p.plan_type,
                                'max_visits', p.max_visits,
                                'active', p.active,
                                'is_current', CASE WHEN active_sub.plan_id = p.id THEN true ELSE false END,
                                'items', COALESCE((
                                    SELECT jsonb_agg(
                                        jsonb_build_object(
                                            'id', pi.id,
                                            'name', pi.name,
                                            'description', pi.description
                                        )
                                        ORDER BY pi.id ASC
                                    )
                                    FROM plan_items pi
                                    WHERE pi.plan_id = p.id
                                ), '[]'::jsonb)
                            )
                            ORDER BY p.price ASC
                        )
                        FROM plans p
                        WHERE p.gym_id = g.id AND p.active = true
                    ), '[]'::jsonb) AS plans
                FROM gyms g
                LEFT JOIN (
                    SELECT
                        s.id,
                        s.slug,
                        s.plan_id,
                        p.name AS plan_name,
                        s.start_date,
                        s.end_date,
                        ss.name AS status_name,
                        s.auto_renew,
                        p.gym_id
                    FROM subscriptions s
                    JOIN users u ON u.id = s.user_id
                    JOIN plans p ON p.id = s.plan_id
                    JOIN status_subscriptions ss ON ss.id = s.status_id
                    WHERE u.auth_id = :auth_id AND p.gym_id = :gym_id_sub AND s.status_id IN (:status1, :status2) AND s.end_date >= CURRENT_DATE
                    ORDER BY s.end_date DESC
                    LIMIT 1
                ) active_sub ON active_sub.gym_id = g.id
                WHERE g.id = :gym_id
            ";

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'auth_id' => $authId,
                'gym_id_sub' => $gymId,
                'status1' => $activeId,
                'status2' => $inactiveId,
                'gym_id' => $gymId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['gym_id'])) {
                return null;
            }

            return EnrollmentResponse::fromArray($row)->toArray();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        try {
            $fields = [];
            $params = ['id' => $id];
            foreach ($data as $key => $val) {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
            $fields[] = 'updated_at = NOW()';

            $sql = 'UPDATE plans SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
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

    public function createItem(int $planId, string $name, ?string $description): array
    {
        try {
            $sql = 'INSERT INTO plan_items (plan_id, name, description, created_at, updated_at)
                    VALUES (:plan_id, :name, :description, NOW(), NOW())
                    RETURNING id, name, description';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'plan_id' => $planId,
                'name' => $name,
                'description' => $description,
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getItemById(int $id): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM plan_items WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function updateItem(int $id, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        try {
            $fields = [];
            $params = ['id' => $id];
            foreach ($data as $key => $val) {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
            $fields[] = 'updated_at = NOW()';

            $sql = 'UPDATE plan_items SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function deleteItem(int $id): bool
    {
        try {
            $stmt = $this->db()->prepare('DELETE FROM plan_items WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }
}

