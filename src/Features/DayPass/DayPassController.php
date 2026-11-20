<?php

declare(strict_types=1);

namespace App\Features\DayPass;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class DayPassController
{
    private ?DayPassService $service = null;

    public function __construct(?DayPassService $service = null)
    {
        $this->service = $service;
    }

    private function service(): DayPassService
    {
        if ($this->service === null) {
            $this->service = new DayPassService();
        }
        return $this->service;
    }

    public function purchaseDayPass(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['plan_id']) || empty($data['visit_date'])) {
            throw new ValidationException('gym_id, plan_id y visit_date son requeridos');
        }

        $ticket = $this->service()->purchaseDayPass((int)$authId, $data);

        Response::created($ticket, 'Pase de un día generado exitosamente');
    }

    public function registerGuest(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['plan_id']) || empty($data['full_name']) || empty($data['phone']) || empty($data['email'])) {
            throw new ValidationException('gym_id, plan_id, full_name, phone y email son requeridos');
        }

        $ticket = $this->service()->registerGuest((int)$authId, $data);

        Response::created($ticket, 'Invitado registrado exitosamente');
    }

    public function createPublicDayPass(): void
    {
        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['plan_id']) || empty($data['full_name']) || empty($data['phone']) || empty($data['email'])) {
            throw new ValidationException('gym_id, plan_id, full_name, phone y email son requeridos');
        }

        $ticket = $this->service()->createPublicDayPass($data);

        Response::created($ticket, 'Pase diario generado exitosamente');
    }

    public function getTicketBySlug(string $slug): void
    {
        if (empty($slug)) {
            throw new BadRequestException('Código de ticket no proporcionado');
        }

        $ticket = $this->service()->getTicketBySlug($slug);
        Response::json($ticket);
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

        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);
        $date = Flight::request()->query->date ?? null;
        $search = Flight::request()->query->search ?? Flight::request()->query->query ?? null;

        $res = $this->service()->getAllByGym((int)$authId, $gymId, $date, $search, $page, $pageSize);
        Response::json($res);
    }
}

