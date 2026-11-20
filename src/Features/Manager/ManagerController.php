<?php

declare(strict_types=1);

namespace App\Features\Manager;

use App\Shared\Errors\AppException;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class ManagerController
{
    private ?ManagerService $service = null;

    public function __construct(?ManagerService $service = null)
    {
        $this->service = $service;
    }

    private function service(): ManagerService
    {
        if ($this->service === null) {
            $this->service = new ManagerService();
        }
        return $this->service;
    }

    public function createManager(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['email']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['gym_id'])) {
            throw new ValidationException('Email, first_name, last_name y gym_id son requeridos');
        }

        $this->service()->create($authId, $data);

        Response::created(null, 'Encargado registrado exitosamente. Se ha enviado un correo de confirmación.');
    }

    public function getAllManagersByGym(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);
        $q = Flight::request()->query->q ?? '';

        $managers = $this->service()->getAllManagersByGym($authId, $page, $pageSize, (string)$q);

        Response::json($managers);
    }

    public function getDetailsMe(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $detail = $this->service()->getDetailByAuthId($authId);
        Response::json($detail);
    }

    public function update(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        $firstName = isset($data['first_name']) ? (string)$data['first_name'] : null;
        $lastName = isset($data['last_name']) ? (string)$data['last_name'] : null;
        $phone = isset($data['phone']) ? (string)$data['phone'] : null;

        $this->service()->update($authId, $firstName, $lastName, $phone);

        Response::json(['message' => 'User updated successfully']);
    }

    public function uploadAvatar(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $fileInfo = \App\Shared\Utils\FileUtils::extractImage(['image', 'avatar', 'file']);
        $saved = \App\Shared\Utils\FileUtils::saveFile('managers/avatars', $fileInfo);

        try {
            $this->service()->uploadAvatar($authId, $saved['slug'], $saved['path'], $saved['mime_type'], $saved['size']);
            Response::json(['message' => 'Avatar uploaded successfully', 'slug' => $saved['slug']]);
        } catch (\Throwable $e) {
            if (file_exists($saved['path'])) {
                @unlink($saved['path']);
            }
            throw $e;
        }
    }

    public function openCashRegister(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (!isset($data['gym_id']) || !isset($data['open_cash'])) {
            throw new ValidationException('gym_id y open_cash son requeridos');
        }

        $gymId = (int) $data['gym_id'];
        $openCash = (float) $data['open_cash'];

        if ($gymId <= 0) {
            throw new ValidationException('gym_id debe ser un entero positivo válido');
        }

        $register = $this->service()->openCashRegister(
            $authId,
            $gymId,
            $openCash
        );

        Response::created($register->toArray(), 'Caja abierta exitosamente');
    }

    public function closeCashRegister(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (!isset($data['close_cash'])) {
            throw new ValidationException('close_cash es requerido');
        }

        $register = $this->service()->closeCashRegister(
            $authId,
            (float) $data['close_cash']
        );

        Response::json($register->toArray(), 'Caja cerrada exitosamente');
    }

    public function getCurrentCashRegister(): void
    {
        $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $register = $this->service()->getCurrentCashRegister($authId);
        Response::json($register->toArray());
    }
}

