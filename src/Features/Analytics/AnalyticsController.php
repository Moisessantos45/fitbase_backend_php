<?php

declare(strict_types=1);

namespace App\Features\Analytics;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Utils\Response;
use Flight;

class AnalyticsController
{
    private ?AnalyticsService $service = null;

    public function __construct(?AnalyticsService $service = null)
    {
        $this->service = $service;
    }

    private function service(): AnalyticsService
    {
        if ($this->service === null) {
            $this->service = new AnalyticsService();
        }
        return $this->service;
    }

    public function getManagerShifts(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $from = Flight::request()->query->from ?? null;
        $to = Flight::request()->query->to ?? null;
        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);

        $res = $this->service()->getManagerShifts((int)$authId, $gymId, $from, $to, $page, $pageSize);
        Response::json($res);
    }

    public function getAttendanceMetrics(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $from = Flight::request()->query->from ?? null;
        $to = Flight::request()->query->to ?? null;

        $report = $this->service()->getAttendanceMetrics((int)$authId, $gymId, $from, $to);
        Response::json($report);
    }

    public function getCrossedRevenue(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $from = Flight::request()->query->from ?? null;
        $to = Flight::request()->query->to ?? null;

        $report = $this->service()->getCrossedRevenue((int)$authId, $gymId, $from, $to);
        Response::json($report);
    }

    public function getClientHistory(string $id, string $user_id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        $targetUserId = (int)$user_id;
        if ($gymId <= 0 || $targetUserId <= 0) {
            throw new BadRequestException('ID de gimnasio o usuario inválido');
        }

        $report = $this->service()->getClientHistory((int)$authId, $gymId, $targetUserId);
        Response::json($report);
    }

    public function getDashboardSummary(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $report = $this->service()->getDashboardSummary((int)$authId, $gymId);
        Response::json($report);
    }
}

