<?php

declare(strict_types=1);

namespace App\Features\Plan;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\UnauthorizedException;

class PlanService
{
    private ?PlanRepository $repo = null;

    public function __construct(?PlanRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): PlanRepository
    {
        if ($this->repo === null) {
            $this->repo = new PlanRepository();
        }
        return $this->repo;
    }

    private function validatePlanOwner(int $authId, int $planId): void
    {
        if ($authId <= 0 || $planId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if (!$ownerId) {
            throw new NotFoundException('Perfil de dueño no encontrado');
        }

        $plan = $this->repo()->getById($planId);
        if (!$plan) {
            throw new NotFoundException('Plan no encontrado');
        }

        $gymOwnerId = $this->repo()->getGymOwnerId((int)$plan['gym_id']);
        if (!$gymOwnerId || $gymOwnerId !== $ownerId) {
            throw new ForbiddenException('No tienes permiso para modificar este plan');
        }
    }

    public function create(int $authId, array $data): array
    {
        $gymId = (int)($data['gym_id'] ?? 0);
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if (!$ownerId) {
            throw new NotFoundException('Perfil de dueño no encontrado');
        }

        $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
        if (!$gymOwnerId) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        if ($gymOwnerId !== $ownerId) {
            throw new ForbiddenException('No tienes permiso para crear planes en este gimnasio');
        }

        $name = trim((string)($data['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new BadRequestException('El nombre del plan debe tener entre 2 y 100 caracteres');
        }

        $description = trim((string)($data['description'] ?? ''));
        if (empty($description)) {
            throw new BadRequestException('La descripción es requerida');
        }

        $planType = trim((string)($data['plan_type'] ?? ''));
        if (empty($planType)) {
            throw new BadRequestException('El tipo de plan es requerido');
        }

        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            throw new BadRequestException('El precio debe ser mayor a 0');
        }

        $discount = (float)($data['discount'] ?? 0);
        if ($discount < 0 || $discount > 100) {
            throw new BadRequestException('El descuento debe estar entre 0 y 100');
        }

        $duration = (int)($data['duration'] ?? 0);
        if ($duration < 1) {
            throw new BadRequestException('La duración debe ser de al menos 1 día');
        }

        $maxVisits = isset($data['max_visits']) && $data['max_visits'] !== null ? (int)$data['max_visits'] : null;

        $planData = [
            'gym_id' => $gymId,
            'name' => $name,
            'description' => $description,
            'plan_type' => $planType,
            'price' => $price,
            'discount' => $discount,
            'duration' => $duration,
            'max_visits' => $maxVisits,
            'active' => true,
        ];

        $items = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $it) {
                $itemName = trim((string)($it['name'] ?? ''));
                if (!empty($itemName)) {
                    $items[] = [
                        'name' => $itemName,
                        'description' => isset($it['description']) ? trim((string)$it['description']) : null,
                    ];
                }
            }
        }

        $planId = $this->repo()->createWithItems($planData, $items);
        $detail = $this->repo()->getDetailsById($planId);
        if (!$detail) {
            throw new NotFoundException('Plan creado pero no pudo ser recuperado');
        }

        return $detail;
    }

    public function update(int $authId, int $planId, array $data): void
    {
        $this->validatePlanOwner($authId, $planId);

        $update = [];
        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if (!empty($name)) {
                $update['name'] = $name;
            }
        }

        if (isset($data['description'])) {
            $update['description'] = trim((string)$data['description']);
        }

        if (isset($data['plan_type'])) {
            $planType = trim((string)$data['plan_type']);
            if (!empty($planType)) {
                $update['plan_type'] = $planType;
            }
        }

        if (isset($data['price'])) {
            $price = (float)$data['price'];
            if ($price > 0) {
                $update['price'] = $price;
            }
        }

        if (isset($data['discount'])) {
            $discount = (float)$data['discount'];
            if ($discount >= 0 && $discount <= 100) {
                $update['discount'] = $discount;
            }
        }

        if (isset($data['duration'])) {
            $duration = (int)$data['duration'];
            if ($duration >= 1) {
                $update['duration'] = $duration;
            }
        }

        if (array_key_exists('max_visits', $data)) {
            $update['max_visits'] = $data['max_visits'] !== null && (int)$data['max_visits'] > 0
                ? (int)$data['max_visits']
                : null;
        }

        if (isset($data['active'])) {
            $update['active'] = (bool)$data['active'];
        }

        if (empty($update)) {
            return;
        }

        $this->repo()->update($planId, $update);
    }

    public function getDetailsById(int $id): array
    {
        if ($id <= 0) {
            throw new BadRequestException('ID de plan inválido');
        }

        $plan = $this->repo()->getDetailsById($id);
        if (!$plan) {
            throw new NotFoundException('Plan no encontrado');
        }

        return $plan;
    }

    public function getAllByGymId(?int $authId, int $gymId, bool $activeOnly): array
    {
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        if (!$activeOnly) {
            if (!$authId) {
                throw new UnauthorizedException('No autorizado');
            }

            $isAuthorized = false;
            $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
            if ($ownerId) {
                $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
                if ($gymOwnerId === $ownerId) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                $managerGymId = $this->repo()->getManagerGymId($authId);
                if ($managerGymId === $gymId) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                throw new ForbiddenException('No tienes autorización para consultar los planes de esta sede');
            }
        }

        return $this->repo()->getAllByGymId($gymId, $activeOnly);
    }

    public function getEnrollmentPlans(int $authId, int $gymId): array
    {
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $resp = $this->repo()->getEnrollmentPlans($authId, $gymId);
        if (!$resp) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        return $resp;
    }

    public function addItem(int $authId, int $planId, array $data): array
    {
        $this->validatePlanOwner($authId, $planId);

        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            throw new BadRequestException('El nombre del beneficio es requerido');
        }

        $description = isset($data['description']) ? trim((string)$data['description']) : null;

        return $this->repo()->createItem($planId, $name, $description);
    }

    public function updateItem(int $authId, int $planId, int $itemId, array $data): void
    {
        if ($itemId <= 0) {
            throw new BadRequestException('ID de beneficio inválido');
        }

        $this->validatePlanOwner($authId, $planId);

        $item = $this->repo()->getItemById($itemId);
        if (!$item) {
            throw new NotFoundException('Beneficio no encontrado');
        }

        if ((int)$item['plan_id'] !== $planId) {
            throw new BadRequestException('El beneficio no pertenece a este plan');
        }

        $update = [];
        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if (!empty($name)) {
                $update['name'] = $name;
            }
        }

        if (isset($data['description'])) {
            $update['description'] = trim((string)$data['description']);
        }

        if (empty($update)) {
            return;
        }

        $this->repo()->updateItem($itemId, $update);
    }

    public function deleteItem(int $authId, int $planId, int $itemId): void
    {
        if ($itemId <= 0) {
            throw new BadRequestException('ID de beneficio inválido');
        }

        $this->validatePlanOwner($authId, $planId);

        $item = $this->repo()->getItemById($itemId);
        if (!$item) {
            throw new NotFoundException('Beneficio no encontrado');
        }

        if ((int)$item['plan_id'] !== $planId) {
            throw new BadRequestException('El beneficio no pertenece a este plan');
        }

        $this->repo()->deleteItem($itemId);
    }
}

