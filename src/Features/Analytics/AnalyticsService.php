<?php

declare(strict_types=1);

namespace App\Features\Analytics;

use App\Models\Paginate;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use DateTimeImmutable;

class AnalyticsService
{
    private ?AnalyticsRepository $repo = null;

    public function __construct(?AnalyticsRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): AnalyticsRepository
    {
        if ($this->repo === null) {
            $this->repo = new AnalyticsRepository();
        }
        return $this->repo;
    }

    private function validateOwnerAccess(int $authId, int $gymId): void
    {
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if (!$ownerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede acceder a los reportes');
        }

        $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
        if (!$gymOwnerId || $ownerId !== $gymOwnerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede acceder a los reportes');
        }
    }

    private function parseDateRange(?string $fromStr, ?string $toStr): array
    {
        $from = null;
        $to = null;

        if (!empty($fromStr)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($fromStr));
            if ($parsed) {
                $from = $parsed->format('Y-m-d 00:00:00');
            }
        }

        if (!empty($toStr)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($toStr));
            if ($parsed) {
                $to = $parsed->format('Y-m-d 23:59:59');
            }
        }

        return [$from, $to];
    }

    public function getManagerShifts(int $authId, int $gymId, ?string $fromStr, ?string $toStr, int $page, int $pageSize): array
    {
        $this->validateOwnerAccess($authId, $gymId);

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        [$from, $to] = $this->parseDateRange($fromStr, $toStr);

        $res = $this->repo()->getManagerShiftsReport($gymId, $from, $to, $offset, $pageSize);
        $shifts = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => ManagerShiftReport::fromArray($row)->toArray(), $shifts);
        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function getAttendanceMetrics(int $authId, int $gymId, ?string $fromStr, ?string $toStr): array
    {
        $this->validateOwnerAccess($authId, $gymId);
        [$from, $to] = $this->parseDateRange($fromStr, $toStr);

        $report = $this->repo()->getGymAttendanceReport($gymId, $from, $to);
        if (!$report) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        return GymAttendanceReport::fromArray($report)->toArray();
    }

    public function getCrossedRevenue(int $authId, int $gymId, ?string $fromStr, ?string $toStr): array
    {
        $this->validateOwnerAccess($authId, $gymId);
        [$from, $to] = $this->parseDateRange($fromStr, $toStr);

        $report = $this->repo()->getRevenueCrossedReport($gymId, $from, $to);
        if (!$report) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        return RevenueCrossedReport::fromArray($report)->toArray();
    }

    public function getClientHistory(int $authId, int $gymId, int $targetUserId): array
    {
        $this->validateOwnerAccess($authId, $gymId);
        if ($targetUserId <= 0) {
            throw new BadRequestException('ID de usuario inválido');
        }

        $report = $this->repo()->getClientHistoryReport($gymId, $targetUserId);
        if (!$report) {
            throw new NotFoundException('Usuario o historial no encontrado');
        }

        return ClientMembershipHistoryReport::fromArray($report)->toArray();
    }

    public function getDashboardSummary(int $authId, int $gymId): array
    {
        $this->validateOwnerAccess($authId, $gymId);

        $report = $this->repo()->getDashboardSummary($gymId);
        if (!$report) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        return DashboardSummaryReport::fromArray($report)->toArray();
    }
}

