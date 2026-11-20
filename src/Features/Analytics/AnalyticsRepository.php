<?php

declare(strict_types=1);

namespace App\Features\Analytics;

use App\Config\Database;
use App\Models\AccessType;
use App\Models\PaymentStatus;
use App\Shared\Helpers\ErrorHandler;
use PDO;

class AnalyticsRepository
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

    public function getManagerShiftsReport(int $gymId, ?string $from, ?string $to, int $offset, int $limit): array
    {
        try {
            $whereClause = 'WHERE cr.gym_id = :gym_id';
            $params = ['gym_id' => $gymId];

            if (!empty($from)) {
                $whereClause .= ' AND cr.open_time >= :from_time';
                $params['from_time'] = $from;
            }
            if (!empty($to)) {
                $whereClause .= ' AND cr.open_time <= :to_time';
                $params['to_time'] = $to;
            }

            $countSql = "SELECT COUNT(*) FROM cash_registers cr {$whereClause}";
            $stmtCount = $this->db()->prepare($countSql);
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $paidStatus = PaymentStatus::PAID;
            $dataSql = "
                WITH collected AS (
                    SELECT
                        cash_register_id,
                        SUM(amount) AS total_cash
                    FROM payments
                    WHERE payment_status_id = {$paidStatus}
                    GROUP BY cash_register_id
                )
                SELECT
                    cr.id AS shift_id,
                    cr.gym_id,
                    g.name AS gym_name,
                    cr.auth_id AS manager_auth_id,
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', gm.first_name, gm.last_name)), ''),
                        a.email
                    ) AS manager_name,
                    a.email AS manager_email,
                    cr.open_time,
                    cr.close_time,
                    cr.open_cash,
                    cr.close_cash,
                    COALESCE(c.total_cash, 0) AS total_collected,
                    CASE
                        WHEN cr.close_time IS NULL THEN NULL
                        ELSE ROUND(
                            (cr.close_cash - (cr.open_cash + COALESCE(c.total_cash, 0)))::numeric,
                            2
                        )
                    END AS difference,
                    CASE
                        WHEN cr.close_time IS NULL THEN 'Abierta'
                        WHEN ABS(
                            cr.close_cash - (cr.open_cash + COALESCE(c.total_cash, 0))
                        ) < 0.01 THEN 'Cuadrada'
                        ELSE 'Con Descuadre'
                    END AS status
                FROM cash_registers cr
                JOIN gyms g ON g.id = cr.gym_id
                JOIN auths a ON a.id = cr.auth_id
                LEFT JOIN gym_managers gm ON gm.auth_id = cr.auth_id
                LEFT JOIN collected c ON c.cash_register_id = cr.id
                {$whereClause}
                ORDER BY cr.open_time DESC
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

    public function getGymAttendanceReport(int $gymId, ?string $from, ?string $to): ?array
    {
        try {
            $membershipType = AccessType::MEMBERSHIP;
            $daypassType = AccessType::DAYPASS;
            $guestType = AccessType::GUEST;

            $dateFilterCi = '';
            $dateFilterMain = '';
            $params = ['gym_id' => $gymId];

            if (!empty($from)) {
                $dateFilterCi .= ' AND ci.checked_in_at >= :from_date';
                $dateFilterMain .= ' AND check_ins.checked_in_at >= :from_date';
                $params['from_date'] = $from;
            }
            if (!empty($to)) {
                $dateFilterCi .= ' AND ci.checked_in_at <= :to_date';
                $dateFilterMain .= ' AND check_ins.checked_in_at <= :to_date';
                $params['to_date'] = $to;
            }

            $sql = "
                SELECT
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    COUNT(check_ins.id) AS total_check_ins,
                    COALESCE(peak.hr, 0) AS peak_hour,
                    COALESCE(peak.cnt, 0) AS peak_hour_count,
                    COUNT(CASE WHEN check_ins.access_type_id = {$membershipType} THEN 1 END) AS memberships_count,
                    COUNT(CASE WHEN check_ins.access_type_id = {$daypassType} THEN 1 END) AS day_passes_count,
                    COUNT(CASE WHEN check_ins.access_type_id = {$guestType} THEN 1 END) AS guests_count,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object('hour', h.hr, 'count', h.cnt) ORDER BY h.hr ASC)
                        FROM (
                            SELECT EXTRACT(HOUR FROM ci.checked_in_at)::int AS hr, COUNT(ci.id) AS cnt
                            FROM check_ins ci
                            WHERE ci.gym_id = :gym_id {$dateFilterCi}
                            GROUP BY EXTRACT(HOUR FROM ci.checked_in_at)::int
                        ) h
                    ), '[]'::jsonb) AS hourly_distribution
                FROM gyms
                LEFT JOIN check_ins ON check_ins.gym_id = gyms.id {$dateFilterMain}
                LEFT JOIN (
                    SELECT EXTRACT(HOUR FROM ci.checked_in_at)::int AS hr, COUNT(ci.id) AS cnt
                    FROM check_ins ci
                    WHERE ci.gym_id = :gym_id {$dateFilterCi}
                    GROUP BY EXTRACT(HOUR FROM ci.checked_in_at)::int
                    ORDER BY cnt DESC, hr ASC
                    LIMIT 1
                ) peak ON true
                WHERE gyms.id = :gym_id
                GROUP BY gyms.id, gyms.name, peak.hr, peak.cnt
            ";

            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getRevenueCrossedReport(int $gymId, ?string $from, ?string $to): ?array
    {
        try {
            $paidStatus = PaymentStatus::PAID;
            $dateFilterP = '';
            $params = ['gym_id' => $gymId];

            if (!empty($from)) {
                $dateFilterP .= ' AND p.created_at >= :from_date';
                $params['from_date'] = $from;
            }
            if (!empty($to)) {
                $dateFilterP .= ' AND p.created_at <= :to_date';
                $params['to_date'] = $to;
            }

            $sql = "
                SELECT
                    gyms.id AS gym_id,
                    gyms.name AS gym_name,
                    COALESCE(SUM(payments.amount), 0) AS total_revenue,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object(
                            'payment_method_id', pm.id,
                            'payment_method_name', pm.name,
                            'total_revenue', COALESCE(agg.sum_amount, 0),
                            'transaction_count', COALESCE(agg.cnt, 0)
                        ))
                        FROM payment_methods pm
                        JOIN (
                            SELECT p.payment_method_id, SUM(p.amount) AS sum_amount, COUNT(p.id) AS cnt
                            FROM payments p
                            WHERE p.gym_id = :gym_id AND p.payment_status_id = {$paidStatus} {$dateFilterP}
                            GROUP BY p.payment_method_id
                        ) agg ON agg.payment_method_id = pm.id
                    ), '[]'::jsonb) AS by_payment_method,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object(
                            'plan_id', pl.id,
                            'plan_name', pl.name,
                            'total_revenue', COALESCE(agg.sum_amount, 0),
                            'subscription_count', COALESCE(agg.cnt, 0)
                        ))
                        FROM plans pl
                        JOIN (
                            SELECT s.plan_id, SUM(p.amount) AS sum_amount, COUNT(p.id) AS cnt
                            FROM payments p
                            JOIN subscriptions s ON s.id = p.subscription_id
                            WHERE p.gym_id = :gym_id AND p.payment_status_id = {$paidStatus} {$dateFilterP}
                            GROUP BY s.plan_id
                        ) agg ON agg.plan_id = pl.id
                    ), '[]'::jsonb) AS by_plan
                FROM gyms
                LEFT JOIN payments ON payments.gym_id = gyms.id AND payments.payment_status_id = {$paidStatus}
                WHERE gyms.id = :gym_id
                GROUP BY gyms.id, gyms.name
            ";

            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getClientHistoryReport(int $gymId, int $targetUserId): ?array
    {
        try {
            $paidStatus = PaymentStatus::PAID;
            $sql = "
                SELECT
                    users.id AS user_id,
                    CONCAT(users.first_name, ' ', users.last_name) AS full_name,
                    auths.email,
                    users.phone,
                    COALESCE((
                        SELECT SUM(p.amount)
                        FROM payments p
                        LEFT JOIN subscriptions s ON s.id = p.subscription_id
                        LEFT JOIN day_passes dp ON dp.id = p.day_pass_id
                        WHERE p.gym_id = :gym_id
                          AND p.payment_status_id = {$paidStatus}
                          AND (s.user_id = users.id OR dp.user_id = users.id)
                    ), 0) AS total_spent,
                    COALESCE((
                        SELECT COUNT(ci.id)
                        FROM check_ins ci
                        WHERE ci.gym_id = :gym_id AND ci.user_id = users.id
                    ), 0) AS total_check_ins,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object(
                            'id', sub.id,
                            'plan_name', plans.name,
                            'status', status_subscriptions.name,
                            'start_date', to_char(sub.start_date, 'YYYY-MM-DD'),
                            'end_date', to_char(sub.end_date, 'YYYY-MM-DD')
                        ) ORDER BY sub.created_at DESC)
                        FROM subscriptions sub
                        JOIN plans ON plans.id = sub.plan_id
                        JOIN status_subscriptions ON status_subscriptions.id = sub.status_id
                        WHERE sub.user_id = users.id AND plans.gym_id = :gym_id
                    ), '[]'::jsonb) AS subscriptions,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object(
                            'id', p.id,
                            'amount', p.amount,
                            'method', payment_methods.name,
                            'status', payment_statuses.name,
                            'date', p.payment_date
                        ) ORDER BY p.created_at DESC)
                        FROM payments p
                        JOIN payment_methods ON payment_methods.id = p.payment_method_id
                        JOIN payment_statuses ON payment_statuses.id = p.payment_status_id
                        LEFT JOIN subscriptions s ON s.id = p.subscription_id
                        LEFT JOIN day_passes dp ON dp.id = p.day_pass_id
                        WHERE p.gym_id = :gym_id AND (s.user_id = users.id OR dp.user_id = users.id)
                    ), '[]'::jsonb) AS payments,
                    COALESCE((
                        SELECT jsonb_agg(jsonb_build_object(
                            'id', ci.id,
                            'access_type', access_types.name,
                            'date', ci.checked_in_at
                        ) ORDER BY ci.checked_in_at DESC)
                        FROM (
                            SELECT ci_sub.id, ci_sub.access_type_id, ci_sub.checked_in_at
                            FROM check_ins ci_sub
                            WHERE ci_sub.gym_id = :gym_id AND ci_sub.user_id = users.id
                            ORDER BY ci_sub.checked_in_at DESC
                            LIMIT 10
                        ) ci
                        JOIN access_types ON access_types.id = ci.access_type_id
                    ), '[]'::jsonb) AS recent_check_ins
                FROM users
                JOIN auths ON auths.id = users.auth_id
                WHERE users.id = :target_user_id
            ";

            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'gym_id' => $gymId,
                'target_user_id' => $targetUserId,
            ]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            return $res ?: null;
        } catch (\PDOException $e) {
            throw ErrorHandler::handleDatabaseError($e);
        }
    }

    public function getDashboardSummary(int $gymId): ?array
    {
        try {
            $paidStatus = PaymentStatus::PAID;
            $sql = "
                SELECT
                    g.id AS gym_id,
                    g.name AS gym_name,
                    jsonb_build_object(
                        'total_revenue', COALESCE(rev.total_revenue, 0),
                        'total_transactions', COALESCE(rev.total_transactions, 0),
                        'active_subscribers', COALESCE(sub.active_subscribers, 0),
                        'total_check_ins', COALESCE(chk.total_check_ins, 0),
                        'peak_hour', COALESCE(pk.peak_hour, 0),
                        'peak_hour_count', COALESCE(pk.peak_hour_count, 0)
                    ) AS kpis,
                    COALESCE((
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'id', p.id,
                                'amount', p.amount,
                                'payment_method_name', COALESCE(pm.name, 'Efectivo'),
                                'payment_status_name', COALESCE(ps.name, 'Pagado'),
                                'payment_date', COALESCE(p.payment_date, p.created_at)
                            )
                        )
                        FROM (
                            SELECT p_sub.*
                            FROM payments p_sub
                            WHERE p_sub.gym_id = g.id
                            ORDER BY p_sub.created_at DESC
                            LIMIT 5
                        ) p
                        LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
                        LEFT JOIN payment_statuses ps ON ps.id = p.payment_status_id
                    ), '[]'::jsonb) AS recent_payments,
                    COALESCE((
                        SELECT jsonb_agg(
                            jsonb_build_object(
                                'shift_id', cr.id,
                                'manager_name', COALESCE(NULLIF(TRIM(CONCAT_WS(' ', gm.first_name, gm.last_name)), ''), a.email),
                                'open_time', cr.open_time,
                                'close_time', cr.close_time,
                                'open_cash', cr.open_cash,
                                'status', CASE
                                    WHEN cr.close_time IS NULL THEN 'Abierta'
                                    WHEN ABS(cr.close_cash - (cr.open_cash + COALESCE(col.total_cash, 0))) < 0.01 THEN 'Cuadrada'
                                    ELSE 'Con Descuadre'
                                END
                            )
                        )
                        FROM (
                            SELECT cr_sub.*
                            FROM cash_registers cr_sub
                            WHERE cr_sub.gym_id = g.id
                            ORDER BY cr_sub.open_time DESC
                            LIMIT 5
                        ) cr
                        JOIN auths a ON a.id = cr.auth_id
                        LEFT JOIN gym_managers gm ON gm.auth_id = cr.auth_id
                        LEFT JOIN (
                            SELECT cash_register_id, SUM(amount) AS total_cash
                            FROM payments
                            WHERE payment_status_id = {$paidStatus}
                            GROUP BY cash_register_id
                        ) col ON col.cash_register_id = cr.id
                    ), '[]'::jsonb) AS recent_shifts
                FROM gyms g
                LEFT JOIN (
                    SELECT gym_id, COALESCE(SUM(amount), 0) AS total_revenue, COUNT(id) AS total_transactions
                    FROM payments
                    WHERE payment_status_id = {$paidStatus}
                    GROUP BY gym_id
                ) rev ON rev.gym_id = g.id
                LEFT JOIN (
                    SELECT p.gym_id, COUNT(DISTINCT s.id) AS active_subscribers
                    FROM subscriptions s
                    JOIN plans p ON p.id = s.plan_id
                    WHERE s.status_id = 1 AND s.end_date >= CURRENT_DATE
                    GROUP BY p.gym_id
                ) sub ON sub.gym_id = g.id
                LEFT JOIN (
                    SELECT gym_id, COUNT(id) AS total_check_ins
                    FROM check_ins
                    GROUP BY gym_id
                ) chk ON chk.gym_id = g.id
                LEFT JOIN (
                    SELECT ci.gym_id, EXTRACT(HOUR FROM ci.checked_in_at)::int AS peak_hour, COUNT(ci.id) AS peak_hour_count
                    FROM check_ins ci
                    GROUP BY ci.gym_id, EXTRACT(HOUR FROM ci.checked_in_at)::int
                    ORDER BY COUNT(ci.id) DESC
                    LIMIT 1
                ) pk ON pk.gym_id = g.id
                WHERE g.id = :gym_id
            ";

            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['gym_id' => $gymId]);
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
}

