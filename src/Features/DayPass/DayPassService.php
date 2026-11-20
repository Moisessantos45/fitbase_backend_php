<?php

declare(strict_types=1);

namespace App\Features\DayPass;

use App\Models\Paginate;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Services\MailerService;
use DateTimeImmutable;

class DayPassService
{
    private ?DayPassRepository $repo = null;

    public function __construct(?DayPassRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): DayPassRepository
    {
        if ($this->repo === null) {
            $this->repo = new DayPassRepository();
        }
        return $this->repo;
    }

    private function sendDayPassEmail(array $ticket): void
    {
        $email = trim((string)($ticket['email'] ?? ''));
        if (empty($email)) {
            return;
        }

        try {
            $mailer = new MailerService();
            $isPaid = !empty($ticket['is_paid']);
            $gymName = (string)($ticket['gym_name'] ?? 'Fitbase');
            $name = trim((string)($ticket['full_name'] ?? 'Visitante'));

            $slug = (string)($ticket['slug'] ?? '');
            $html = $mailer->renderTemplate('day_pass_ticket.html', [
                'Name'       => $name !== '' ? $name : 'Visitante',
                'GymName'    => $gymName,
                'PlanName'   => (string)($ticket['plan_name'] ?? ''),
                'PlanPrice'  => (float)($ticket['plan_price'] ?? 0.0),
                'VisitDate'  => (string)($ticket['visit_date'] ?? ''),
                'TicketCode' => $slug,
                'QRCodeURL'  => "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}",
                'IsPaid'     => $isPaid,
            ]);

            $subject = $isPaid 
                ? "Tu Pase de un Día en {$gymName}" 
                : "Ticket de Pase de un Día (Pago en Mostrador) — {$gymName}";

            $mailer->send($email, $subject, $html);
        } catch (\Throwable $e) {
            error_log('[DayPassService] Error enviando correo: ' . $e->getMessage());
        }
    }

    private function validateVisitDateAndSchedule(int $gymId, DateTimeImmutable $visitDate): void
    {
        $now = new DateTimeImmutable('now');
        $today = new DateTimeImmutable('today');
        $visitDay = new DateTimeImmutable($visitDate->format('Y-m-d'));

        if ($visitDay < $today) {
            throw new BadRequestException('No se pueden agendar visitas para fechas pasadas');
        }

        if ($visitDay == $today) {
            $schedules = $this->repo()->getSchedulesByGymId($gymId);
            $dayOfWeek = (int)$now->format('w');

            $todaySchedule = null;
            foreach ($schedules as $sch) {
                if ((int)$sch['day_of_week'] === $dayOfWeek) {
                    $todaySchedule = $sch;
                    break;
                }
            }

            if (!$todaySchedule || !empty($todaySchedule['is_closed'])) {
                throw new BadRequestException('El gimnasio se encuentra cerrado hoy');
            }

            $currentMinutes = ((int)$now->format('G')) * 60 + ((int)$now->format('i'));
            if ($currentMinutes >= (int)$todaySchedule['close_minutes']) {
                throw new BadRequestException('El horario de atención del gimnasio ha concluido por hoy');
            }
        }
    }

    private function validatePlanForDayPass(int $gymId, int $planId): array
    {
        $plan = $this->repo()->getPlanById($planId);
        if (!$plan || (int)$plan['gym_id'] !== $gymId || empty($plan['active'])) {
            throw new BadRequestException('Plan no válido para esta sede');
        }

        $duration = (int)($plan['duration'] ?? 1);
        $maxVisits = isset($plan['max_visits']) && $plan['max_visits'] !== null ? (int)$plan['max_visits'] : null;

        if ($duration > 1 && ($maxVisits === null || $maxVisits > 1)) {
            throw new BadRequestException('El plan seleccionado no es de un solo uso. Para planes de membresía, el socio debe crear una cuenta y suscribirse al plan');
        }

        return $plan;
    }

    public function purchaseDayPass(int $authId, array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $planId = (int)($req['plan_id'] ?? 0);
        if ($authId <= 0 || $gymId <= 0 || $planId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $user = $this->repo()->getUserDetailsByAuthId($authId);
        if (!$user) {
            throw new NotFoundException('Perfil de usuario no encontrado');
        }
        $userId = (int)$user['id'];

        $visitDateStr = trim((string)($req['visit_date'] ?? ''));
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $visitDateStr);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $visitDateStr) {
            throw new BadRequestException('Formato de fecha de visita inválido, use YYYY-MM-DD');
        }

        $this->validateVisitDateAndSchedule($gymId, $parsedDate);
        $this->validatePlanForDayPass($gymId, $planId);

        $dayPassData = [
            'gym_id' => $gymId,
            'plan_id' => $planId,
            'user_id' => $userId,
            'full_name' => (string)$user['full_name'],
            'phone' => (string)$user['phone'],
            'email' => (string)$user['email'],
            'visit_date' => $parsedDate->format('Y-m-d'),
        ];

        $slug = $this->repo()->create($dayPassData);
        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando el ticket generado');
        }

        $this->sendDayPassEmail($ticket);

        return DayPassTicketResponse::fromArray($ticket)->toArray();
    }

    public function registerGuest(int $authId, array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $planId = (int)($req['plan_id'] ?? 0);
        if ($authId <= 0 || $gymId <= 0 || $planId <= 0) {
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
            throw new ForbiddenException('No tienes autorización para registrar visitas en esta sede');
        }

        $visitDateStr = trim((string)($req['visit_date'] ?? ''));
        if ($visitDateStr === '') {
            $visitDate = new DateTimeImmutable('today');
        } else {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $visitDateStr);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $visitDateStr) {
                throw new BadRequestException('Formato de fecha inválido, use YYYY-MM-DD');
            }
            $visitDate = $parsedDate;
        }

        $this->validateVisitDateAndSchedule($gymId, $visitDate);
        $this->validatePlanForDayPass($gymId, $planId);

        $fullName = trim((string)($req['full_name'] ?? ''));
        $phone = trim((string)($req['phone'] ?? ''));
        $email = trim((string)($req['email'] ?? ''));

        if (empty($fullName) || empty($phone) || empty($email)) {
            throw new BadRequestException('Nombre, teléfono y correo del invitado son requeridos');
        }

        $dayPassData = [
            'gym_id' => $gymId,
            'plan_id' => $planId,
            'user_id' => null,
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'visit_date' => $visitDate->format('Y-m-d'),
        ];

        $slug = $this->repo()->create($dayPassData);
        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando el ticket generado');
        }

        $this->sendDayPassEmail($ticket);

        return DayPassTicketResponse::fromArray($ticket)->toArray();
    }

    public function createPublicDayPass(array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $planId = (int)($req['plan_id'] ?? 0);
        if ($gymId <= 0 || $planId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $visitDateStr = trim((string)($req['visit_date'] ?? ''));
        if ($visitDateStr === '') {
            $visitDate = new DateTimeImmutable('today');
        } else {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $visitDateStr);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $visitDateStr) {
                throw new BadRequestException('Formato de fecha inválido, use YYYY-MM-DD');
            }
            $visitDate = $parsedDate;
        }

        $this->validateVisitDateAndSchedule($gymId, $visitDate);
        $this->validatePlanForDayPass($gymId, $planId);

        $fullName = trim((string)($req['full_name'] ?? ''));
        $phone = trim((string)($req['phone'] ?? ''));
        $email = trim((string)($req['email'] ?? ''));

        if (empty($fullName) || empty($phone) || empty($email)) {
            throw new BadRequestException('Nombre, teléfono y correo son requeridos');
        }

        $dayPassData = [
            'gym_id' => $gymId,
            'plan_id' => $planId,
            'user_id' => null,
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'visit_date' => $visitDate->format('Y-m-d'),
        ];

        $slug = $this->repo()->create($dayPassData);
        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Error recuperando el ticket generado');
        }

        $this->sendDayPassEmail($ticket);

        return DayPassTicketResponse::fromArray($ticket)->toArray();
    }

    public function getTicketBySlug(string $slug): array
    {
        $slug = trim($slug);
        if (empty($slug)) {
            throw new BadRequestException('Código de ticket no proporcionado');
        }

        $ticket = $this->repo()->getTicketBySlug($slug);
        if (!$ticket) {
            throw new NotFoundException('Ticket de pase no encontrado');
        }

        return DayPassTicketResponse::fromArray($ticket)->toArray();
    }

    public function getAllByGym(int $authId, int $gymId, ?string $dateStr, ?string $search, int $page, int $pageSize): array
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
            throw new ForbiddenException('No tienes permiso para consultar los pases de esta sede');
        }

        $filterDate = null;
        if (!empty($dateStr)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($dateStr));
            if ($parsed) {
                $filterDate = $parsed->format('Y-m-d');
            }
        }

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByGymId($gymId, $filterDate, $search, $offset, $pageSize);
        $passes = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => DayPassDetailResponse::fromArray($row)->toArray(), $passes);
        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }
}

