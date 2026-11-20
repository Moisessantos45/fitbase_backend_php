<?php

declare(strict_types=1);

namespace App\Features\CheckIn;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class CheckInController
{
    private ?CheckInService $service = null;

    public function __construct(?CheckInService $service = null)
    {
        $this->service = $service;
    }

    private function service(): CheckInService
    {
        if ($this->service === null) {
            $this->service = new CheckInService();
        }
        return $this->service;
    }

    public function validateAccess(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id'])) {
            throw new ValidationException('gym_id es requerido');
        }

        $result = $this->service()->validateAccess((int)$authId, $data);

        Response::json($result, 'Acceso validado exitosamente');
    }

    public function getMyHistory(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);

        $res = $this->service()->getMyHistory((int)$authId, $page, $pageSize);
        Response::json($res);
    }

    public function getAllByGym(string $id): void
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

        $res = $this->service()->getAllByGym((int)$authId, $gymId, $from, $to, $page, $pageSize);
        Response::json($res);
    }
}

