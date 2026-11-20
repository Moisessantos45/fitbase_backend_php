<?php

declare(strict_types=1);

namespace App\Features\CheckIn;

use App\Models\AccessType;
use App\Models\Paginate;
use App\Models\StatusSubscription;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use DateTimeImmutable;

class CheckInService
{
    private ?CheckInRepository $repo = null;

    public function __construct(?CheckInRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): CheckInRepository
    {
        if ($this->repo === null) {
            $this->repo = new CheckInRepository();
        }
        return $this->repo;
    }

    private function validateGymSchedule(int $gymId): void
    {
        $schedules = $this->repo()->getSchedulesByGymId($gymId);
        $now = new DateTimeImmutable('now');
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
        $openMinutes = (int)($todaySchedule['open_minutes'] ?? 0);
        $closeMinutes = (int)($todaySchedule['close_minutes'] ?? 1440);

        if ($currentMinutes < $openMinutes || $currentMinutes >= $closeMinutes) {
            throw new BadRequestException('Fuera del horario de atención del gimnasio');
        }
    }

    public function validateAccess(int $authId, array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $slugStr = trim((string)($req['slug'] ?? ''));
        $emailStr = mb_strtolower(trim((string)($req['email'] ?? '')));

        if ($slugStr === '' && $emailStr === '') {
            throw new BadRequestException('Debes ingresar el código QR (slug) o el correo electrónico del socio');
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
            throw new ForbiddenException('No tienes autorización para operar los accesos en esta sede');
        }

        $this->validateGymSchedule($gymId);

        $dayPass = null;
        $subscription = null;

        if ($slugStr !== '') {
            $dp = $this->repo()->getDayPassBySlug($slugStr);
            if ($dp) {
                $dayPass = $dp;
            } else {
                $sub = $this->repo()->getSubscriptionBySlug($slugStr);
                if ($sub) {
                    $subscription = $sub;
                }
            }
        } else {
            $sub = $this->repo()->getSubscriptionByEmail($emailStr, $gymId);
            if ($sub) {
                $subscription = $sub;
            } else {
                $dp = $this->repo()->getDayPassByEmail($emailStr, $gymId);
                if ($dp) {
                    $dayPass = $dp;
                }
            }
        }

        if ($dayPass !== null) {
            if ((int)$dayPass['gym_id'] !== $gymId) {
                throw new BadRequestException('El pase no corresponde a esta sede');
            }

            $todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
            $visitDateStr = substr((string)$dayPass['visit_date'], 0, 10);
            if ($visitDateStr !== $todayStr) {
                throw new BadRequestException("El pase diario no es válido para la fecha de hoy ({$visitDateStr})");
            }

            if (!empty($dayPass['is_used'])) {
                throw new BadRequestException('Pase ya utilizado');
            }

            $isPaid = $this->repo()->isDayPassPaid((int)$dayPass['id']);
            if (!$isPaid) {
                throw new BadRequestException('El pase diario no ha sido pagado. Debe abonarse en recepción antes de ingresar');
            }

            $planName = 'Pase de un Día';
            $plan = $this->repo()->getPlanById((int)$dayPass['plan_id']);
            if ($plan && !empty($plan['name'])) {
                $planName = (string)$plan['name'];
            }

            $accessTypeId = !empty($dayPass['user_id']) ? AccessType::DAYPASS : AccessType::GUEST;
            $accessTypeName = !empty($dayPass['user_id']) ? 'Día de Pase' : 'Invitado';

            $this->repo()->beginTransaction();
            try {
                $this->repo()->markDayPassAsUsed((int)$dayPass['id']);
                $this->repo()->register([
                    'gym_id' => $gymId,
                    'access_type_id' => $accessTypeId,
                    'user_id' => !empty($dayPass['user_id']) ? (int)$dayPass['user_id'] : null,
                    'day_pass_id' => (int)$dayPass['id'],
                    'subscription_id' => null,
                    'registered_by_id' => $authId,
                ]);
                $this->repo()->commit();
            } catch (\Throwable $e) {
                if ($this->repo()->inTransaction()) {
                    $this->repo()->rollBack();
                }
                throw $e;
            }

            return (new AccessResultResponse(
                allowed: true,
                message: 'Acceso concedido',
                accessType: $accessTypeName,
                fullName: (string)$dayPass['full_name'],
                planName: $planName,
                checkedInAt: (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            ))->toArray();
        }

        if ($subscription !== null) {
            $plan = $this->repo()->getPlanById((int)$subscription['plan_id']);
            if (!$plan || (int)$plan['gym_id'] !== $gymId) {
                throw new BadRequestException('La membresía no corresponde a esta sede');
            }

            $today = new DateTimeImmutable('today');
            $startDate = new DateTimeImmutable(substr((string)$subscription['start_date'], 0, 10));
            $endDate = new DateTimeImmutable(substr((string)$subscription['end_date'], 0, 10));

            if ($today < $startDate || $today > $endDate) {
                $sStr = $startDate->format('Y-m-d');
                $eStr = $endDate->format('Y-m-d');
                throw new BadRequestException("Membresía fuera de vigencia (vigente del {$sStr} al {$eStr})");
            }

            $statusId = (int)$subscription['status_id'];
            if ($statusId === StatusSubscription::EXPIRED || $statusId === StatusSubscription::PENDING) {
                throw new BadRequestException('La membresía no se encuentra disponible para acceso');
            }

            $userName = $this->repo()->getUserDetailsByUserId((int)$subscription['user_id']) ?? 'Socio';

            $this->repo()->register([
                'gym_id' => $gymId,
                'access_type_id' => AccessType::MEMBERSHIP,
                'user_id' => (int)$subscription['user_id'],
                'day_pass_id' => null,
                'subscription_id' => (int)$subscription['id'],
                'registered_by_id' => $authId,
            ]);

            return (new AccessResultResponse(
                allowed: true,
                message: 'Acceso concedido',
                accessType: 'Membresía',
                fullName: $userName,
                planName: (string)$plan['name'],
                checkedInAt: (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            ))->toArray();
        }

        if ($emailStr !== '') {
            throw new NotFoundException('No se encontró ninguna membresía o pase para el correo especificado en esta sede');
        }

        throw new NotFoundException('Ticket o credencial no encontrada');
    }

    public function getMyHistory(int $authId, int $page, int $pageSize): array
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
        $items = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => CheckInSummaryItem::fromArray($row)->toArray(), $items);
        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function getAllByGym(int $authId, int $gymId, ?string $fromStr, ?string $toStr, int $page, int $pageSize): array
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
            throw new ForbiddenException('No tienes permiso para consultar los accesos de esta sede');
        }

        $from = null;
        if (!empty($fromStr)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($fromStr));
            if ($parsed) {
                $from = $parsed->format('Y-m-d 00:00:00');
            }
        }

        $to = null;
        if (!empty($toStr)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($toStr));
            if ($parsed) {
                $to = $parsed->format('Y-m-d 23:59:59');
            }
        }

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByGymId($gymId, $from, $to, $offset, $pageSize);
        $items = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => CheckInSummaryItem::fromArray($row)->toArray(), $items);
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

