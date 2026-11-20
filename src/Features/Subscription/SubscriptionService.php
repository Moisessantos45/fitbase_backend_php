<?php

declare(strict_types=1);

namespace App\Features\Subscription;

use App\Models\Paginate;
use App\Models\StatusSubscription;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ConflictException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Services\MailerService;
use DateTimeImmutable;

class SubscriptionService
{
    private ?SubscriptionRepository $repo = null;

    public function __construct(?SubscriptionRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): SubscriptionRepository
    {
        if ($this->repo === null) {
            $this->repo = new SubscriptionRepository();
        }
        return $this->repo;
    }

    private function calculateRemainingDays(string $endDateStr): int
    {
        if (empty($endDateStr)) {
            return 0;
        }

        try {
            $today = new DateTimeImmutable('today');
            $endDate = new DateTimeImmutable($endDateStr);
            if ($endDate >= $today) {
                $diff = $today->diff($endDate);
                return (int)$diff->days + 1;
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    public function purchaseSubscription(int $authId, array $data): array
    {
        $planId = (int)($data['plan_id'] ?? 0);
        $paymentMethodId = (int)($data['payment_method_id'] ?? 0);

        if ($authId <= 0 || $planId <= 0 || $paymentMethodId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $user = $this->repo()->getUserDetailsByAuthId($authId);
        if (!$user) {
            throw new NotFoundException('Perfil de usuario no encontrado');
        }
        $userId = (int)$user['id'];

        $plan = $this->repo()->getPlanById($planId);
        if (!$plan || empty($plan['active'])) {
            throw new BadRequestException('El plan seleccionado no existe o no está activo');
        }

        $duration = (int)($plan['duration'] ?? 0);
        if ($duration <= 0) {
            throw new BadRequestException('El plan no tiene una duración válida');
        }

        $gymId = (int)$plan['gym_id'];
        $activeSub = $this->repo()->getActiveByUserId($userId, $gymId);
        if ($activeSub) {
            $endDateStr = substr((string)$activeSub['end_date'], 0, 10);
            throw new ConflictException("Ya cuentas con una suscripción activa en esta sede hasta el {$endDateStr}");
        }

        $today = new DateTimeImmutable('today');
        $startDateStr = trim((string)($data['start_date'] ?? ''));

        if ($startDateStr === '') {
            $startDate = $today;
        } else {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDateStr);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $startDateStr) {
                throw new BadRequestException('Formato de fecha de inicio inválido, use YYYY-MM-DD');
            }
            if ($parsedDate < $today) {
                throw new BadRequestException('La fecha de inicio no puede ser en el pasado');
            }
            $startDate = $parsedDate;
        }

        $endDate = $startDate->modify("+{$duration} days");
        $autoRenew = !empty($data['auto_renew']);

        $subData = [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status_id' => StatusSubscription::ACTIVE,
            'payment_method_id' => $paymentMethodId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'auto_renew' => $autoRenew,
        ];

        $slug = $this->repo()->create($subData);
        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando la suscripción creada');
        }

        $ticket['remaining_days'] = $this->calculateRemainingDays($ticket['end_date']);
        $result = SubscriptionTicketResponse::fromArray($ticket)->toArray();

        $this->sendSubscriptionEmail($ticket, trim((string)($user['email'] ?? '')), false);

        return $result;
    }

    private function sendSubscriptionEmail(array $ticket, string $email = '', bool $isRenewal = false): void
    {
        $targetEmail = trim($email);
        if ($targetEmail === '') {
            $targetEmail = trim((string)($ticket['email'] ?? ''));
        }
        if ($targetEmail === '') {
            return;
        }

        try {
            $mailer = new MailerService();
            $name = trim((string)($ticket['full_name'] ?? 'Socio'));
            $gymName = (string)($ticket['gym_name'] ?? 'Fitbase');
            $slug = (string)($ticket['slug'] ?? '');
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}";

            $html = $mailer->renderTemplate('subscription_created.html', [
                'Name'       => $name !== '' ? $name : 'Socio',
                'GymName'    => $gymName,
                'PlanName'   => (string)($ticket['plan_name'] ?? ''),
                'PlanPrice'  => (float)($ticket['plan_price'] ?? 0.0),
                'StartDate'  => substr((string)($ticket['start_date'] ?? ''), 0, 10),
                'EndDate'    => substr((string)($ticket['end_date'] ?? ''), 0, 10),
                'TicketCode' => $slug,
                'QRCodeURL'  => $qrUrl,
                'AutoRenew'  => !empty($ticket['auto_renew']),
                'IsRenewal'  => $isRenewal,
            ]);

            $subject = $isRenewal
                ? "¡Membresía Renovada Exitosamente! — {$gymName}"
                : "Tu Membresía y Credencial Digital — {$gymName}";

            $mailer->send($targetEmail, $subject, $html);
        } catch (\Throwable $e) {
            error_log('[SubscriptionService] Error enviando correo: ' . $e->getMessage());
        }
    }

    public function getMySubscriptions(int $authId, int $page, int $pageSize): array
    {
        if ($authId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $user = $this->repo()->getUserDetailsByAuthId($authId);
        if (!$user) {
            throw new NotFoundException('Perfil de usuario no encontrado');
        }
        $userId = (int)$user['id'];

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByUserId($userId, $offset, $pageSize);
        $subs = $res['data'];
        $total = $res['total'];

        $formatted = [];
        foreach ($subs as $sub) {
            $sub['remaining_days'] = $this->calculateRemainingDays((string)($sub['end_date'] ?? ''));
            $formatted[] = SubscriptionUserSummary::fromArray($sub)->toArray();
        }

        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function getTicketBySlug(string $slug): array
    {
        $slug = trim($slug);
        if (empty($slug)) {
            throw new BadRequestException('Código de ticket no proporcionado');
        }

        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Ticket de suscripción no encontrado');
        }

        $ticket['remaining_days'] = $this->calculateRemainingDays((string)($ticket['end_date'] ?? ''));
        return SubscriptionTicketResponse::fromArray($ticket)->toArray();
    }

    public function getAllByGym(int $authId, int $gymId, int $statusId, int $page, int $pageSize): array
    {
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $isAuthorized = false;
        $managerGymId = $this->repo()->getManagerGymId($authId);
        if ($managerGymId && $managerGymId === $gymId) {
            $isAuthorized = true;
        } else {
            $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
            if ($ownerId) {
                $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
                if ($gymOwnerId && $gymOwnerId === $ownerId) {
                    $isAuthorized = true;
                }
            }
        }

        if (!$isAuthorized) {
            throw new ForbiddenException('No tienes permiso para consultar las suscripciones de esta sede');
        }

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByGymId($gymId, $statusId, $offset, $pageSize);
        $subs = $res['data'];
        $total = $res['total'];

        $formatted = [];
        foreach ($subs as $sub) {
            $sub['remaining_days'] = $this->calculateRemainingDays((string)($sub['end_date'] ?? ''));
            $formatted[] = SubscriptionDetailResponse::fromArray($sub)->toArray();
        }

        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function cancelSubscription(int $authId, int $subscriptionId): array
    {
        if ($authId <= 0 || $subscriptionId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $sub = $this->repo()->getById($subscriptionId);
        if (!$sub) {
            throw new NotFoundException('Suscripción no encontrada');
        }

        if ((int)$sub['status_id'] !== StatusSubscription::ACTIVE) {
            throw new BadRequestException('Solo se pueden cancelar suscripciones activas');
        }

        $plan = $this->repo()->getPlanById((int)$sub['plan_id']);
        if (!$plan) {
            throw new NotFoundException('Plan asociado no encontrado');
        }

        $gymId = (int)$plan['gym_id'];
        $isAuthorized = false;

        $user = $this->repo()->getUserDetailsByAuthId($authId);
        if ($user && (int)$user['id'] === (int)$sub['user_id']) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
            if ($ownerId) {
                $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
                if ($gymOwnerId && $gymOwnerId === $ownerId) {
                    $isAuthorized = true;
                }
            }
        }

        if (!$isAuthorized) {
            $managerGymId = $this->repo()->getManagerGymId($authId);
            if ($managerGymId && $managerGymId === $gymId) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            throw new ForbiddenException('No tienes permiso para cancelar esta suscripción');
        }

        $remainingDays = $this->calculateRemainingDays((string)$sub['end_date']);

        $this->repo()->cancel($subscriptionId);

        $ticket = $this->repo()->getTicketBySlug((string)$sub['slug']);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando ticket de suscripción');
        }

        $ticket['remaining_days'] = $remainingDays;
        return SubscriptionTicketResponse::fromArray($ticket)->toArray();
    }

    public function renewSubscription(int $authId, int $subscriptionId, array $data): array
    {
        if ($authId <= 0 || $subscriptionId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $paymentMethodId = (int)($data['payment_method_id'] ?? 0);
        if ($paymentMethodId <= 0) {
            throw new BadRequestException('Método de pago inválido');
        }

        $sub = $this->repo()->getById($subscriptionId);
        if (!$sub) {
            throw new NotFoundException('Suscripción no encontrada');
        }

        $planId = (int)$sub['plan_id'];
        $plan = $this->repo()->getPlanById($planId);
        if (!$plan || empty($plan['active'])) {
            throw new BadRequestException('El plan asociado no se encuentra disponible o activo');
        }

        $gymId = (int)$plan['gym_id'];
        $isAuthorized = false;

        $user = $this->repo()->getUserDetailsByAuthId($authId);
        if ($user && (int)$user['id'] === (int)$sub['user_id']) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
            if ($ownerId && $ownerId > 0) {
                $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
                if ($gymOwnerId === $ownerId) {
                    $isAuthorized = true;
                }
            }
        }

        if (!$isAuthorized) {
            $managerGymId = $this->repo()->getManagerGymId($authId);
            if ($managerGymId === $gymId) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            throw new ForbiddenException('No tienes autorización para renovar esta suscripción');
        }

        $duration = (int)($plan['duration'] ?? 30);
        if ($duration <= 0) {
            $duration = 30;
        }

        $today = new DateTimeImmutable('today');
        $subEndDate = new DateTimeImmutable(substr((string)$sub['end_date'], 0, 10));

        if ((int)$sub['status_id'] === StatusSubscription::ACTIVE && $subEndDate >= $today) {
            $formattedEnd = $subEndDate->format('Y-m-d');
            throw new ConflictException("No se puede renovar la suscripción porque aún cuentas con una membresía activa hasta el {$formattedEnd}");
        }

        $activeSub = $this->repo()->getActiveByUserId((int)$sub['user_id'], $gymId);
        if ($activeSub && (int)$activeSub['id'] !== $subscriptionId) {
            $formattedEnd = substr((string)$activeSub['end_date'], 0, 10);
            throw new ConflictException("No se puede renovar porque ya cuentas con una suscripción activa en esta sede hasta el {$formattedEnd}");
        }

        $newStartDate = $today;
        $newEndDate = $today->modify("+{$duration} days");

        $this->repo()->renew(
            $subscriptionId,
            $newStartDate->format('Y-m-d'),
            $newEndDate->format('Y-m-d'),
            $paymentMethodId
        );

        $ticket = $this->repo()->getTicketById($subscriptionId);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando la suscripción renovada');
        }

        $ticket['remaining_days'] = $this->calculateRemainingDays($ticket['end_date']);
        $result = SubscriptionTicketResponse::fromArray($ticket)->toArray();

        $this->sendSubscriptionEmail($ticket, (string)($ticket['email'] ?? ''), true);

        return $result;
    }
}

