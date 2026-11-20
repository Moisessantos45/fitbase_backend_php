<?php

declare(strict_types=1);

namespace App\Features\Payment;

use App\Config\Database;
use App\Models\PaymentStatus;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class PaymentRepository
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

    public function create(array $payment): int
    {
        try {
            $sql = 'INSERT INTO payments (
                        gym_id, payment_method_id, payment_status_id, amount,
                        subscription_id, day_pass_id, cash_register_id,
                        payment_date, created_at, updated_at
                    ) VALUES (
                        :gym_id, :payment_method_id, :payment_status_id, :amount,
                        :subscription_id, :day_pass_id, :cash_register_id,
                        NOW(), NOW(), NOW()
                    ) RETURNING id';

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $payment['gym_id'],
                'payment_method_id' => $payment['payment_method_id'],
                'payment_status_id' => $payment['payment_status_id'],
                'amount' => $payment['amount'],
                'subscription_id' => $payment['subscription_id'] ?? null,
                'day_pass_id' => $payment['day_pass_id'] ?? null,
                'cash_register_id' => $payment['cash_register_id'] ?? null,
            ]);

            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getTotalRevenueByGymId(int $gymId): array
    {
        try {
            $paidStatus = PaymentStatus::PAID;
            $sql = '
                SELECT
                    COALESCE(SUM(amount), 0) AS total_revenue,
                    COUNT(*) AS total_transactions
                FROM payments
                WHERE gym_id = :gym_id AND payment_status_id = :paid_status
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $gymId,
                'paid_status' => $paidStatus,
            ]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'total_revenue' => (float)($res['total_revenue'] ?? 0.0),
                'total_transactions' => (int)($res['total_transactions'] ?? 0),
            ];
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getAllByGymId(int $gymId, int $offset, int $limit): array
    {
        try {
            $countSql = 'SELECT COUNT(*) FROM payments WHERE gym_id = :gym_id';
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute(['gym_id' => $gymId]);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = '
                SELECT
                    payments.id,
                    payments.gym_id,
                    gyms.name AS gym_name,
                    payments.payment_method_id,
                    payment_methods.name AS payment_method_name,
                    payments.payment_status_id,
                    payment_statuses.name AS payment_status_name,
                    payments.amount,
                    payments.subscription_id,
                    payments.day_pass_id,
                    payments.cash_register_id,
                    payments.payment_date,
                    payments.created_at
                FROM payments
                JOIN gyms ON gyms.id = payments.gym_id
                JOIN payment_methods ON payment_methods.id = payments.payment_method_id
                JOIN payment_statuses ON payment_statuses.id = payments.payment_status_id
                WHERE payments.gym_id = :gym_id
                ORDER BY payments.created_at DESC
                OFFSET :offset LIMIT :limit
            ';

            $stmtData = $this->db()->prepare($dataSql);
            $stmtData->bindValue('gym_id', $gymId, PDO::PARAM_INT);
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
            $countSql = '
                SELECT COUNT(*)
                FROM payments
                LEFT JOIN subscriptions s ON s.id = payments.subscription_id
                LEFT JOIN day_passes dp ON dp.id = payments.day_pass_id
                WHERE s.user_id = :u1 OR dp.user_id = :u2
            ';
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute(['u1' => $userId, 'u2' => $userId]);
            $total = (int)$stmtCount->fetchColumn();

            $dataSql = '
                SELECT
                    payments.id,
                    payments.gym_id,
                    gyms.name AS gym_name,
                    payments.payment_method_id,
                    payment_methods.name AS payment_method_name,
                    payments.payment_status_id,
                    payment_statuses.name AS payment_status_name,
                    payments.amount,
                    payments.subscription_id,
                    payments.day_pass_id,
                    payments.cash_register_id,
                    payments.payment_date,
                    payments.created_at
                FROM payments
                JOIN gyms ON gyms.id = payments.gym_id
                JOIN payment_methods ON payment_methods.id = payments.payment_method_id
                JOIN payment_statuses ON payment_statuses.id = payments.payment_status_id
                LEFT JOIN subscriptions s ON s.id = payments.subscription_id
                LEFT JOIN day_passes dp ON dp.id = payments.day_pass_id
                WHERE s.user_id = :u1 OR dp.user_id = :u2
                ORDER BY payments.created_at DESC
                OFFSET :offset LIMIT :limit
            ';

            $stmtData = $this->db()->prepare($dataSql);
            $stmtData->bindValue('u1', $userId, PDO::PARAM_INT);
            $stmtData->bindValue('u2', $userId, PDO::PARAM_INT);
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

    public function getActiveCashRegisterByAuthId(int $authId, int $gymId): ?array
    {
        try {
            $sql = '
                SELECT cr.*
                FROM cash_registers cr
                JOIN gym_managers gm ON gm.id = cr.gym_manager_id
                WHERE gm.auth_id = :auth_id AND cr.gym_id = :gym_id AND cr.closed_at IS NULL
                ORDER BY cr.opened_at DESC
                LIMIT 1
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['auth_id' => $authId, 'gym_id' => $gymId]);
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

    public function getGymName(int $gymId): ?string
    {
        try {
            $stmt = $this->db()->prepare('SELECT name FROM gyms WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $gymId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string)$val : null;
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

    public function getUserDetailsByAuthId(int $authId): ?int
    {
        try {
            $stmt = $this->db()->prepare('SELECT id FROM users WHERE auth_id = :auth_id LIMIT 1');
            $stmt->execute(['auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getPaymentMethodById(int $methodId): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM payment_methods WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $methodId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getReceiptById(int $paymentId): ?array
    {
        try {
            $sql = '
                SELECT
                    payments.id,
                    payments.gym_id,
                    gyms.name AS gym_name,
                    payments.payment_method_id,
                    payment_methods.name AS payment_method_name,
                    payments.payment_status_id,
                    payment_statuses.name AS payment_status_name,
                    payments.amount,
                    payments.subscription_id,
                    payments.day_pass_id,
                    payments.cash_register_id,
                    payments.payment_date,
                    payments.created_at
                FROM payments
                JOIN gyms ON gyms.id = payments.gym_id
                JOIN payment_methods ON payment_methods.id = payments.payment_method_id
                JOIN payment_statuses ON payment_statuses.id = payments.payment_status_id
                WHERE payments.id = :id
                LIMIT 1
            ';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $paymentId]);
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

    public function getPaymentById(int $paymentId): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $paymentId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function update(int $paymentId, array $updates): bool
    {
        if (empty($updates)) {
            return true;
        }

        try {
            $fields = [];
            $params = ['id' => $paymentId];
            foreach ($updates as $k => $v) {
                $fields[] = "{$k} = :{$k}";
                $params[$k] = $v;
            }
            $fields[] = 'updated_at = NOW()';

            $sql = 'UPDATE payments SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function createSubscription(array $sub): int
    {
        try {
            $sql = 'INSERT INTO subscriptions (
                        user_id, plan_id, status_id, payment_method_id,
                        start_date, end_date, auto_renew, created_at, updated_at
                    ) VALUES (
                        :user_id, :plan_id, :status_id, :payment_method_id,
                        :start_date, :end_date, :auto_renew, NOW(), NOW()
                    ) RETURNING id';

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

            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function createDayPass(array $dp): int
    {
        try {
            $sql = 'INSERT INTO day_passes (
                        gym_id, plan_id, user_id, full_name, phone, email,
                        visit_date, is_used, created_at, updated_at
                    ) VALUES (
                        :gym_id, :plan_id, :user_id, :full_name, :phone, :email,
                        :visit_date, false, NOW(), NOW()
                    ) RETURNING id';

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

            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getDayPassDetailsById(int $dayPassId): ?array
    {
        $sql = "
            SELECT 
                day_passes.id,
                day_passes.slug::text AS slug,
                day_passes.gym_id,
                gyms.name AS gym_name,
                day_passes.plan_id,
                plans.name AS plan_name,
                day_passes.full_name,
                day_passes.email,
                day_passes.is_used,
                COALESCE(TO_CHAR(day_passes.visit_date, 'YYYY-MM-DD'), '') AS visit_date
            FROM day_passes
            JOIN gyms ON gyms.id = day_passes.gym_id
            JOIN plans ON plans.id = day_passes.plan_id
            WHERE day_passes.id = :id
            LIMIT 1
        ";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $dayPassId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getSubscriptionDetailsById(int $subId): ?array
    {
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
                auths.email,
                COALESCE(TO_CHAR(subscriptions.start_date, 'YYYY-MM-DD'), '') AS start_date,
                COALESCE(TO_CHAR(subscriptions.end_date, 'YYYY-MM-DD'), '') AS end_date,
                subscriptions.auto_renew
            FROM subscriptions
            JOIN plans ON plans.id = subscriptions.plan_id
            JOIN gyms ON gyms.id = plans.gym_id
            JOIN users ON users.id = subscriptions.user_id
            JOIN auths ON auths.id = users.auth_id
            WHERE subscriptions.id = :id
            LIMIT 1
        ";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $subId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function isDayPassPaid(int $dayPassId): bool
    {
        try {
            $stmt = $this->db()->prepare('
                SELECT 1 FROM payments
                WHERE day_pass_id = :dp_id AND payment_status_id = :paid_status
                LIMIT 1
            ');
            $stmt->execute([
                'dp_id' => $dayPassId,
                'paid_status' => PaymentStatus::PAID,
            ]);
            return (bool)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getSchedulesByGymId(int $gymId): array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM gym_schedules WHERE gym_id = :gym_id');
            $stmt->execute(['gym_id' => $gymId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getUserProfileByAuthId(int $authId): ?array
    {
        try {
            $sql = "
                SELECT
                    users.id,
                    users.auth_id,
                    users.first_name,
                    users.last_name,
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

    public function getGymById(int $gymId): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM gyms WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $gymId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }
}

