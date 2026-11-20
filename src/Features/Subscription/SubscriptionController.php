<?php

declare(strict_types=1);

namespace App\Features\Subscription;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class SubscriptionController
{
    private ?SubscriptionService $service = null;

    public function __construct(?SubscriptionService $service = null)
    {
        $this->service = $service;
    }

    private function service(): SubscriptionService
    {
        if ($this->service === null) {
            $this->service = new SubscriptionService();
        }
        return $this->service;
    }

    public function purchaseSubscription(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['plan_id']) || empty($data['payment_method_id'])) {
            throw new ValidationException('plan_id y payment_method_id son requeridos');
        }

        $ticket = $this->service()->purchaseSubscription((int)$authId, $data);

        Response::created($ticket, 'Suscripción adquirida exitosamente');
    }

    public function getMySubscriptions(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);

        $res = $this->service()->getMySubscriptions((int)$authId, $page, $pageSize);
        Response::json($res);
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
        $statusId = (int)(Flight::request()->query->status_id ?? 0);

        $res = $this->service()->getAllByGym((int)$authId, $gymId, $statusId, $page, $pageSize);
        Response::json($res);
    }

    public function cancelSubscription(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $subscriptionId = (int)$id;
        if ($subscriptionId <= 0) {
            throw new BadRequestException('ID de suscripción inválido');
        }

        $ticket = $this->service()->cancelSubscription((int)$authId, $subscriptionId);

        $remaining = (int)($ticket['remaining_days'] ?? 0);
        $endDate = (string)($ticket['end_date'] ?? '');
        $msg = 'Suscripción cancelada exitosamente';
        if ($remaining > 0) {
            $msg = sprintf(
                'Suscripción cancelada exitosamente. Cuentas con %d días de acceso restantes hasta el %s',
                $remaining,
                $endDate
            );
        }

        Response::json($ticket, $msg);
    }

    public function renewSubscription(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $subscriptionId = (int)$id;
        if ($subscriptionId <= 0) {
            throw new BadRequestException('ID de suscripción inválido');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['payment_method_id'])) {
            throw new ValidationException('payment_method_id es requerido');
        }

        $ticket = $this->service()->renewSubscription((int)$authId, $subscriptionId, $data);

        Response::json($ticket, 'Suscripción renovada exitosamente');
    }
}

