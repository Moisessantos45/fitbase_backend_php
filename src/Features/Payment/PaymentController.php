<?php

declare(strict_types=1);

namespace App\Features\Payment;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Response;
use Flight;

class PaymentController
{
    private ?PaymentService $service = null;

    public function __construct(?PaymentService $service = null)
    {
        $this->service = $service;
    }

    private function service(): PaymentService
    {
        if ($this->service === null) {
            $this->service = new PaymentService();
        }
        return $this->service;
    }

    public function registerCashPayment(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['payment_method_id']) || !isset($data['amount'])) {
            throw new ValidationException('gym_id, payment_method_id y amount son requeridos');
        }

        $receipt = $this->service()->registerCashPayment((int)$authId, $data);

        Response::created($receipt, 'Pago registrado exitosamente');
    }

    public function getRevenueSummary(string $id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $gymId = (int)$id;
        if ($gymId <= 0) {
            throw new BadRequestException('ID de gimnasio inválido');
        }

        $summary = $this->service()->getRevenueSummary((int)$authId, $gymId);
        Response::json($summary);
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

        $res = $this->service()->getAllByGym((int)$authId, $gymId, $page, $pageSize);
        Response::json($res);
    }

    public function getMyPayments(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $page = (int)(Flight::request()->query->page ?? 1);
        $pageSize = (int)(Flight::request()->query->page_size ?? 10);

        $res = $this->service()->getMyPayments((int)$authId, $page, $pageSize);
        Response::json($res);
    }

    public function createCheckoutSession(): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (!$authId) {
            throw new UnauthorizedException('No autorizado');
        }

        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['plan_id']) || empty($data['type'])) {
            throw new ValidationException('gym_id, plan_id y type son requeridos');
        }

        $result = $this->service()->createCheckoutSession((int)$authId, $data);

        Response::created($result, 'Sesión de checkout creada exitosamente');
    }

    public function createGuestCheckoutSession(): void
    {
        $data = Flight::request()->data->getData();
        if (empty($data['gym_id']) || empty($data['plan_id']) || empty($data['full_name']) || empty($data['phone']) || empty($data['email'])) {
            throw new ValidationException('gym_id, plan_id, full_name, phone y email son requeridos');
        }

        $result = $this->service()->createGuestCheckoutSession($data);

        Response::created($result, 'Sesión de checkout para invitado creada exitosamente');
    }

    public function handleStripeWebhook(): void
    {
        $signature = Flight::request()->getHeader('Stripe-Signature') ?? '';
        $payload = Flight::request()->getBody();

        $this->service()->handleStripeWebhook($payload, $signature);
        Response::json(['received' => true]);
    }

    public function getSessionStatus(string $session_id): void
    {
        $authId = (int)(Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
        if (empty($session_id)) {
            throw new BadRequestException('ID de sesión requerido');
        }

        $status = $this->service()->getSessionStatus($authId ?: null, $session_id);
        Response::json($status);
    }
}

