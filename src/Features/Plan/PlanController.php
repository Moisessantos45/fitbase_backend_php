<?php

declare(strict_types=1);

namespace App\Features\Plan;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class PlanController
{
    private ?PlanService $service = null;

    public function __construct(?PlanService $service = null)
    {
        $this->service = $service;
    }

    private function service(): PlanService
    {
        if ($this->service === null) {
            $this->service = new PlanService();
        }
        return $this->service;
    }

    public function create(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['name']) || empty($data['description']) || !isset($data['price']) || empty($data['duration']) || empty($data['plan_type'])) {
            throw new ValidationException('gym_id, name, description, price, duration y plan_type son requeridos');
        }

        $plan = $this->service()->create((int)$authId, $data);

        Response::created([
            'message' => 'Plan creado exitosamente',
            'data' => $plan,
        ]);
    }

    public function update(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $planId = (int)$id;
        if ($planId <= 0) {
            throw new BadRequestException('ID de plan inválido');
        }

        $data = Flight::request()->data->getData();
        $this->service()->update((int)$authId, $planId, $data);

        Response::json(['message' => 'Plan actualizado exitosamente']);
    }

    public function getDetailsById(string $id): void
    {
        $planId = (int)$id;
        if ($planId <= 0) {
            throw new BadRequestException('ID de plan inválido');
        }

        $plan = $this->service()->getDetailsById($planId);
        Response::json($plan);
    }

    public function getAllActiveByGymId(string $id): void
    {
        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $plans = $this->service()->getAllByGymId(null, $gymId, true);
        Response::json($plans);
    }

    public function getAllByGymId(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $plans = $this->service()->getAllByGymId((int)$authId, $gymId, false);
        Response::json($plans);
    }

    public function getEnrollmentPlans(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $resp = $this->service()->getEnrollmentPlans((int)$authId, $gymId);
        Response::json($resp);
    }

    public function addItem(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $planId = (int)$id;
        if ($planId <= 0) {
            throw new BadRequestException('ID de plan inválido');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['name'])) {
            throw new ValidationException('El nombre del beneficio es requerido');
        }

        $item = $this->service()->addItem((int)$authId, $planId, $data);

        Response::created($item, 'Beneficio agregado exitosamente');
    }

    public function updateItem(string $id, string $item_id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $planId = (int)$id;
        $itemId = (int)$item_id;
        if ($planId <= 0 || $itemId <= 0) {
            throw new BadRequestException('ID de plan o beneficio inválido');
        }

        $data = Flight::request()->data->getData();
        $this->service()->updateItem((int)$authId, $planId, $itemId, $data);

        Response::json(null, 'Beneficio actualizado exitosamente');
    }

    public function deleteItem(string $id, string $item_id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $planId = (int)$id;
        $itemId = (int)$item_id;
        if ($planId <= 0 || $itemId <= 0) {
            throw new BadRequestException('ID de plan o beneficio inválido');
        }

        $this->service()->deleteItem((int)$authId, $planId, $itemId);

        Response::json(['message' => 'Beneficio eliminado exitosamente']);
    }
}

