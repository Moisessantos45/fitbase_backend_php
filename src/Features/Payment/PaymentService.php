<?php

declare(strict_types=1);

namespace App\Features\Payment;

use App\Models\Paginate;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\StatusSubscription;
use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Services\MailerService;
use DateTimeImmutable;

class PaymentService
{
    private ?PaymentRepository $repo = null;
    private string $stripeSecretKey;
    private string $stripeWebhookSecret;
    private string $frontendUrl;

    public function __construct(
        ?PaymentRepository $repo = null,
        ?string $stripeSecretKey = null,
        ?string $stripeWebhookSecret = null,
        ?string $frontendUrl = null
    ) {
        $this->repo = $repo;
        $this->stripeSecretKey = $stripeSecretKey ?? (string)($_ENV['STRIPE_SECRET_KEY'] ?? '');
        $this->stripeWebhookSecret = $stripeWebhookSecret ?? (string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
        $this->frontendUrl = $frontendUrl ?? (string)($_ENV['HOST_URL_PROD'] ?? $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000');
    }

    private function repo(): PaymentRepository
    {
        if ($this->repo === null) {
            $this->repo = new PaymentRepository();
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
            throw new BadRequestException('El gimnasio se encuentra cerrado hoy. No es posible registrar cobros en mostrador');
        }

        $currentMinutes = ((int)$now->format('G')) * 60 + ((int)$now->format('i'));
        $openMinutes = (int)($todaySchedule['open_minutes'] ?? 0);
        $closeMinutes = (int)($todaySchedule['close_minutes'] ?? 1440);

        if ($currentMinutes < $openMinutes || $currentMinutes >= $closeMinutes) {
            throw new BadRequestException('Fuera del horario de atención del gimnasio. No es posible registrar cobros en mostrador');
        }
    }

    public function registerCashPayment(int $authId, array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $amount = (float)($req['amount'] ?? 0);
        $paymentMethodId = (int)($req['payment_method_id'] ?? 0);

        if ($authId <= 0 || $gymId <= 0 || $amount <= 0 || $paymentMethodId <= 0) {
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
            throw new ForbiddenException('No tienes permiso para registrar pagos en esta sede');
        }

        $this->validateGymSchedule($gymId);

        $method = $this->repo()->getPaymentMethodById($paymentMethodId);
        if (!$method || empty($method['active'])) {
            throw new BadRequestException('Método de pago no válido o inactivo');
        }

        $cashRegisterId = null;
        if ($paymentMethodId === PaymentMethod::CASH) {
            $activeBox = $this->repo()->getActiveCashRegisterByAuthId($authId, $gymId);
            if (!$activeBox) {
                throw new BadRequestException('Debes abrir un turno de caja física antes de registrar cobros en efectivo');
            }
            $cashRegisterId = (int)$activeBox['id'];
        }

        if (!empty($req['day_pass_id'])) {
            $dayPassId = (int)$req['day_pass_id'];
            $dp = $this->repo()->getDayPassDetailsById($dayPassId);
            if (!$dp) {
                throw new NotFoundException('Pase de un solo uso no encontrado');
            }

            if ((int)$dp['gym_id'] !== $gymId) {
                throw new BadRequestException('El pase de un solo uso no corresponde a esta sede');
            }

            if (!empty($dp['is_used'])) {
                throw new BadRequestException('El pase ya ha sido utilizado previamente');
            }

            if ($this->repo()->isDayPassPaid($dayPassId)) {
                throw new BadRequestException('El pase ya cuenta con un pago completado registrado');
            }

            $today = new DateTimeImmutable('today');
            $visitDate = new DateTimeImmutable((string)$dp['visit_date']);
            if ($visitDate < $today) {
                throw new BadRequestException("El pase de un solo uso está vencido (fecha programada: {$dp['visit_date']}). No se puede cobrar un pase expirado");
            }
        }

        $paymentData = [
            'gym_id' => $gymId,
            'payment_method_id' => $paymentMethodId,
            'payment_status_id' => PaymentStatus::PAID,
            'amount' => $amount,
            'subscription_id' => isset($req['subscription_id']) ? (int)$req['subscription_id'] : null,
            'day_pass_id' => isset($req['day_pass_id']) ? (int)$req['day_pass_id'] : null,
            'cash_register_id' => $cashRegisterId,
        ];

        $paymentId = $this->repo()->create($paymentData);
        $receipt = $this->repo()->getReceiptById($paymentId);
        if (!$receipt) {
            throw new NotFoundException('Recibo no encontrado tras registrar el pago');
        }

        if (!empty($paymentData['day_pass_id'])) {
            $dayPassId = (int) $paymentData['day_pass_id'];
            try {
                $dp = $this->repo()->getDayPassDetailsById($dayPassId);
                if ($dp && !empty($dp['email'])) {
                    $mailer = new MailerService();
                    $name = trim((string)($dp['full_name'] ?? 'Visitante'));
                    $slug = (string)($dp['slug'] ?? '');
                    $html = $mailer->renderTemplate('day_pass_payment_confirmed.html', [
                        'Name'              => $name !== '' ? $name : 'Visitante',
                        'GymName'           => $dp['gym_name'] ?? '',
                        'PlanName'          => $dp['plan_name'] ?? '',
                        'Amount'            => (float)($receipt['amount'] ?? 0.0),
                        'PaymentMethodName' => $receipt['payment_method_name'] ?? 'Efectivo',
                        'TicketCode'        => $slug,
                        'QRCodeURL'         => "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}",
                        'VisitDate'         => $dp['visit_date'] ?? '',
                    ]);
                    $mailer->send($dp['email'], 'Pago Confirmado: Tu Pase de un Día en ' . ($dp['gym_name'] ?? 'Fitbase'), $html);
                }
            } catch (\Throwable $e) {
                error_log('[PaymentService] Error enviando correo de confirmación de pago: ' . $e->getMessage());
            }
        }

        if (!empty($paymentData['subscription_id'])) {
            $subId = (int) $paymentData['subscription_id'];
            try {
                $sub = $this->repo()->getSubscriptionDetailsById($subId);
                if ($sub && !empty($sub['email'])) {
                    $mailer = new MailerService();
                    $name = trim((string)($sub['full_name'] ?? 'Socio'));
                    $slug = (string)($sub['slug'] ?? '');
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}";

                    $html = $mailer->renderTemplate('subscription_created.html', [
                        'Name'       => $name !== '' ? $name : 'Socio',
                        'GymName'    => $sub['gym_name'] ?? '',
                        'PlanName'   => $sub['plan_name'] ?? '',
                        'PlanPrice'  => (float)($receipt['amount'] ?? 0.0),
                        'StartDate'  => substr((string)($sub['start_date'] ?? ''), 0, 10),
                        'EndDate'    => substr((string)($sub['end_date'] ?? ''), 0, 10),
                        'TicketCode' => $slug,
                        'QRCodeURL'  => $qrUrl,
                        'AutoRenew'  => !empty($sub['auto_renew']),
                        'IsRenewal'  => false,
                    ]);
                    $mailer->send($sub['email'], 'Pago Confirmado: Membresía y Credencial en ' . ($sub['gym_name'] ?? 'Fitbase'), $html);
                }
            } catch (\Throwable $e) {
                error_log('[PaymentService] Error enviando correo de confirmación de suscripción: ' . $e->getMessage());
            }
        }

        return PaymentReceiptResponse::fromArray($receipt)->toArray();
    }

    public function getRevenueSummary(int $authId, int $gymId): array
    {
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if (!$ownerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede consultar los reportes de ingresos');
        }

        $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
        if (!$gymOwnerId || $ownerId !== $gymOwnerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede consultar los reportes de ingresos');
        }

        $summary = $this->repo()->getTotalRevenueByGymId($gymId);
        $gymName = $this->repo()->getGymName($gymId) ?? 'Gimnasio';

        return (new GymRevenueSummaryResponse(
            gymId: $gymId,
            gymName: $gymName,
            totalRevenue: $summary['total_revenue'],
            totalTransactions: $summary['total_transactions']
        ))->toArray();
    }

    public function getAllByGym(int $authId, int $gymId, int $page, int $pageSize): array
    {
        if ($authId <= 0 || $gymId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if (!$ownerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede consultar el listado de transacciones');
        }

        $gymOwnerId = $this->repo()->getGymOwnerId($gymId);
        if (!$gymOwnerId || $ownerId !== $gymOwnerId) {
            throw new ForbiddenException('Solo el propietario de la sede puede consultar el listado de transacciones');
        }

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByGymId($gymId, $offset, $pageSize);
        $items = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => PaymentListItem::fromArray($row)->toArray(), $items);
        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function getMyPayments(int $authId, int $page, int $pageSize): array
    {
        if ($authId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $userId = $this->repo()->getUserDetailsByAuthId($authId);
        if (!$userId) {
            throw new NotFoundException('Perfil de usuario no encontrado');
        }

        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByUserId($userId, $offset, $pageSize);
        $items = $res['data'];
        $total = $res['total'];

        $formatted = array_map(fn($row) => PaymentListItem::fromArray($row)->toArray(), $items);
        $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        if ($total > 0 && $page > $totalPages) {
            throw new BadRequestException('Página fuera de rango');
        }

        return [
            'data' => $formatted,
            'paginate' => (new Paginate($page, $pageSize, $total, $totalPages))->toArray(),
        ];
    }

    public function createCheckoutSession(int $authId, array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $planId = (int)($req['plan_id'] ?? 0);
        $type = trim((string)($req['type'] ?? ''));

        if ($authId <= 0 || $gymId <= 0 || $planId <= 0 || !in_array($type, ['subscription', 'daypass'], true)) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $profile = $this->repo()->getUserProfileByAuthId($authId);
        if (!$profile) {
            throw new NotFoundException('Perfil de usuario no encontrado');
        }

        $gym = $this->repo()->getGymById($gymId);
        if (!$gym) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        $plan = $this->repo()->getPlanById($planId);
        if (!$plan || empty($plan['active']) || (int)$plan['gym_id'] !== $gymId) {
            throw new BadRequestException('Plan no disponible o inactivo');
        }

        $pType = mb_strtolower(trim((string)$plan['plan_type']));
        $duration = (int)($plan['duration'] ?? 1);
        $isDayPassPlan = in_array($pType, ['visita', 'daypass', 'dia'], true) || $duration <= 1;

        if ($type === 'daypass' && !$isDayPassPlan) {
            throw new BadRequestException('El plan seleccionado no es de tipo pase de día');
        }
        if ($type === 'subscription' && $isDayPassPlan) {
            throw new BadRequestException('El plan seleccionado es de tipo pase de día, debe usarse daypass');
        }

        $paymentData = [
            'gym_id' => $gymId,
            'payment_method_id' => PaymentMethod::CARD,
            'payment_status_id' => PaymentStatus::PENDING,
            'amount' => (float)$plan['price'],
            'subscription_id' => null,
            'day_pass_id' => null,
            'cash_register_id' => null,
        ];

        $paymentId = $this->repo()->create($paymentData);

        $sessionId = null;
        $checkoutUrl = null;

        try {
            $stripe = new \Stripe\StripeClient($this->stripeSecretKey ?: 'sk_test_mock_fitbase_development_key');
            $successUrl = "{$this->frontendUrl}/payment/success?session_id={CHECKOUT_SESSION_ID}&payment_id={$paymentId}";
            $cancelUrl = "{$this->frontendUrl}/payment/cancel?payment_id={$paymentId}";

            $fullName = trim((string)($profile['full_name'] ?? (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))));

            $session = $stripe->checkout->sessions->create([
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'mode'        => 'payment',
                'line_items'  => [[
                    'price_data' => [
                        'currency'     => 'mxn',
                        'product_data' => [
                            'name'        => (string)$plan['name'],
                            'description' => "{$plan['name']} en {$gym['name']}",
                        ],
                        'unit_amount'  => (int) round(((float)$plan['price']) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => (string)($profile['email'] ?? ''),
                'metadata' => [
                    'payment_id' => (string)$paymentId,
                    'type'       => $type,
                    'gym_id'     => (string)$gymId,
                    'user_id'    => (string)$profile['id'],
                    'auth_id'    => (string)$authId,
                    'full_name'  => $fullName,
                    'phone'      => (string)($profile['phone'] ?? ''),
                    'email'      => (string)($profile['email'] ?? ''),
                    'plan_id'    => (string)$planId,
                    'visit_date' => (string)($req['visit_date'] ?? ''),
                    'auto_renew' => !empty($req['auto_renew']) ? 'true' : 'false',
                ],
            ]);

            $sessionId = $session->id;
            $checkoutUrl = $session->url;
        } catch (\Throwable $e) {
            throw new AppException('Error al iniciar sesión en Stripe: ' . $e->getMessage(), 500);
        }

        return (new CheckoutSessionResponse(
            sessionId: $sessionId,
            checkoutUrl: $checkoutUrl,
            paymentId: $paymentId,
            amount: (float)$plan['price']
        ))->toArray();
    }

    public function createGuestCheckoutSession(array $req): array
    {
        $gymId = (int)($req['gym_id'] ?? 0);
        $planId = (int)($req['plan_id'] ?? 0);

        if ($gymId <= 0 || $planId <= 0) {
            throw new BadRequestException('Parámetros inválidos');
        }

        $gym = $this->repo()->getGymById($gymId);
        if (!$gym) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        $plan = $this->repo()->getPlanById($planId);
        if (!$plan || empty($plan['active']) || (int)$plan['gym_id'] !== $gymId) {
            throw new BadRequestException('Plan no disponible o inactivo');
        }

        $pType = mb_strtolower(trim((string)$plan['plan_type']));
        $duration = (int)($plan['duration'] ?? 1);
        $isDayPassPlan = in_array($pType, ['visita', 'daypass', 'dia'], true) || $duration <= 1;

        if (!$isDayPassPlan) {
            throw new BadRequestException('El plan seleccionado no es de tipo pase de día');
        }

        $paymentData = [
            'gym_id' => $gymId,
            'payment_method_id' => PaymentMethod::CARD,
            'payment_status_id' => PaymentStatus::PENDING,
            'amount' => (float)$plan['price'],
            'subscription_id' => null,
            'day_pass_id' => null,
            'cash_register_id' => null,
        ];

        $paymentId = $this->repo()->create($paymentData);

        $sessionId = null;
        $checkoutUrl = null;

        try {
            $stripe = new \Stripe\StripeClient($this->stripeSecretKey ?: 'sk_test_mock_fitbase_development_key');
            $successUrl = "{$this->frontendUrl}/payment/success?session_id={CHECKOUT_SESSION_ID}&payment_id={$paymentId}";
            $cancelUrl = "{$this->frontendUrl}/payment/cancel?payment_id={$paymentId}";

            $session = $stripe->checkout->sessions->create([
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'mode'        => 'payment',
                'line_items'  => [[
                    'price_data' => [
                        'currency'     => 'mxn',
                        'product_data' => [
                            'name'        => (string)$plan['name'],
                            'description' => "{$plan['name']} en {$gym['name']} (Pase Diario)",
                        ],
                        'unit_amount'  => (int) round(((float)$plan['price']) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => (string)($req['email'] ?? ''),
                'metadata' => [
                    'payment_id' => (string)$paymentId,
                    'type'       => 'daypass',
                    'gym_id'     => (string)$gymId,
                    'user_id'    => '0',
                    'auth_id'    => '0',
                    'full_name'  => (string)($req['full_name'] ?? 'Invitado'),
                    'phone'      => (string)($req['phone'] ?? ''),
                    'email'      => (string)($req['email'] ?? ''),
                    'plan_id'    => (string)$planId,
                    'visit_date' => (string)($req['visit_date'] ?? ''),
                    'auto_renew' => 'false',
                ],
            ]);

            $sessionId = $session->id;
            $checkoutUrl = $session->url;
        } catch (\Throwable $e) {
            throw new AppException('Error al iniciar sesión en Stripe: ' . $e->getMessage(), 500);
        }

        return (new CheckoutSessionResponse(
            sessionId: $sessionId,
            checkoutUrl: $checkoutUrl,
            paymentId: $paymentId,
            amount: (float)$plan['price']
        ))->toArray();
    }

    public function handleStripeWebhook(string $payload, string $signature): void
    {
        if (!empty($this->stripeWebhookSecret) && !empty($signature)) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->stripeWebhookSecret);
                $type = $event->type;
                $data = $event->data->object ? $event->data->object->toArray() : [];
                $metadata = $data['metadata'] ?? [];
            } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
                throw new UnauthorizedException('Firma de webhook inválida');
            }
        } else {
            $event = json_decode($payload, true);
            if (!is_array($event) || !isset($event['type'])) {
                throw new BadRequestException('Evento de webhook inválido');
            }
            $type = $event['type'];
            $data = $event['data']['object'] ?? [];
            $metadata = $data['metadata'] ?? [];
        }

        $paymentId = (int)($metadata['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            return;
        }

        if ($type === 'checkout.session.completed') {
            $this->repo()->beginTransaction();
            try {
                $payment = $this->repo()->getPaymentById($paymentId);
                if (!$payment || (int)$payment['payment_status_id'] === PaymentStatus::PAID) {
                    $this->repo()->commit();
                    return;
                }

                $paymentType = $metadata['type'] ?? 'subscription';
                $planId = (int)($metadata['plan_id'] ?? 0);
                $gymId = (int)($metadata['gym_id'] ?? 0);
                $userId = (int)($metadata['user_id'] ?? 0);
                $autoRenew = ($metadata['auto_renew'] ?? 'false') === 'true';
                $visitDateStr = (string)($metadata['visit_date'] ?? '');

                $plan = $this->repo()->getPlanById($planId);
                $subId = null;
                $dayPassId = null;

                if ($paymentType === 'subscription') {
                    $duration = (int)($plan['duration'] ?? 30);
                    $now = new DateTimeImmutable('today');
                    $endDate = $now->modify("+{$duration} days");

                    $subId = $this->repo()->createSubscription([
                        'user_id' => $userId,
                        'plan_id' => $planId,
                        'status_id' => StatusSubscription::ACTIVE,
                        'payment_method_id' => PaymentMethod::CARD,
                        'start_date' => $now->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'auto_renew' => $autoRenew,
                    ]);
                } else {
                    $fullName = $metadata['full_name'] ?? 'Cliente';
                    $phone = $metadata['phone'] ?? '';
                    $email = $metadata['email'] ?? '';
                    $vDate = !empty($visitDateStr) ? $visitDateStr : (new DateTimeImmutable('today'))->format('Y-m-d');

                    $dayPassId = $this->repo()->createDayPass([
                        'user_id' => $userId > 0 ? $userId : null,
                        'gym_id' => $gymId,
                        'plan_id' => $planId,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'email' => $email,
                        'visit_date' => $vDate,
                    ]);
                }

                $this->repo()->update($paymentId, [
                    'payment_status_id' => PaymentStatus::PAID,
                    'payment_method_id' => PaymentMethod::CARD,
                    'subscription_id' => $subId,
                    'day_pass_id' => $dayPassId,
                ]);

                $this->repo()->commit();

                if ($dayPassId !== null && $dayPassId > 0) {
                    try {
                        $dp = $this->repo()->getDayPassDetailsById($dayPassId);
                        if ($dp && !empty($dp['email'])) {
                            $mailer = new MailerService();
                            $name = trim((string)($dp['full_name'] ?? 'Visitante'));
                            $amountTotal = isset($data['amount_total']) ? ((float)$data['amount_total'] / 100.0) : (float)($plan['price'] ?? 0.0);
                            $slug = (string)($dp['slug'] ?? '');
                            $html = $mailer->renderTemplate('day_pass_payment_confirmed.html', [
                                'Name'              => $name !== '' ? $name : 'Visitante',
                                'GymName'           => $dp['gym_name'] ?? '',
                                'PlanName'          => $dp['plan_name'] ?? '',
                                'Amount'            => $amountTotal,
                                'PaymentMethodName' => 'Tarjeta de Crédito / Débito (Stripe)',
                                'TicketCode'        => $slug,
                                'QRCodeURL'         => "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}",
                                'VisitDate'         => $dp['visit_date'] ?? '',
                            ]);
                            $mailer->send($dp['email'], 'Pago Confirmado: Tu Pase de un Día en ' . ($dp['gym_name'] ?? 'Fitbase'), $html);
                        }
                    } catch (\Throwable $e) {
                        error_log('[PaymentService] Error enviando correo Stripe day pass: ' . $e->getMessage());
                    }
                }

                if ($subId !== null && $subId > 0) {
                    try {
                        $sub = $this->repo()->getSubscriptionDetailsById($subId);
                        if ($sub && !empty($sub['email'])) {
                            $mailer = new MailerService();
                            $name = trim((string)($sub['full_name'] ?? 'Socio'));
                            $amountTotal = isset($data['amount_total']) ? ((float)$data['amount_total'] / 100.0) : (float)($sub['plan_price'] ?? 0.0);
                            $slug = (string)($sub['slug'] ?? '');
                            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={$slug}";

                            $html = $mailer->renderTemplate('subscription_created.html', [
                                'Name'       => $name !== '' ? $name : 'Socio',
                                'GymName'    => $sub['gym_name'] ?? '',
                                'PlanName'   => $sub['plan_name'] ?? '',
                                'PlanPrice'  => $amountTotal,
                                'StartDate'  => substr((string)($sub['start_date'] ?? ''), 0, 10),
                                'EndDate'    => substr((string)($sub['end_date'] ?? ''), 0, 10),
                                'TicketCode' => $slug,
                                'QRCodeURL'  => $qrUrl,
                                'AutoRenew'  => !empty($sub['auto_renew']),
                                'IsRenewal'  => false,
                            ]);
                            $mailer->send($sub['email'], 'Tu Membresía y Credencial Digital — ' . ($sub['gym_name'] ?? 'Fitbase'), $html);
                        }
                    } catch (\Throwable $e) {
                        error_log('[PaymentService] Error enviando correo Stripe subscription: ' . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                if ($this->repo()->inTransaction()) {
                    $this->repo()->rollBack();
                }
                throw $e;
            }
        } elseif ($type === 'checkout.session.expired') {
            $this->repo()->update($paymentId, [
                'payment_status_id' => PaymentStatus::CANCELED,
                'payment_method_id' => PaymentMethod::CARD,
            ]);
        }
    }

    public function getSessionStatus(?int $authId, string $sessionId): array
    {
        $sessionId = trim($sessionId);
        if (empty($sessionId)) {
            throw new BadRequestException('ID de sesión requerido');
        }

        try {
            $stripe = new \Stripe\StripeClient($this->stripeSecretKey ?: 'sk_test_mock_fitbase_development_key');
            $sess = $stripe->checkout->sessions->retrieve($sessionId, []);

            $metadata = $sess->metadata ? $sess->metadata->toArray() : [];
            $paymentId = (int)($metadata['payment_id'] ?? 0);
            $status = (string)($sess->status ?? 'pending');
            $paymentStatus = (string)($sess->payment_status ?? 'unpaid');
            $amountTotal = ((float)($sess->amount_total ?? 0)) / 100.0;
            $currency = (string)($sess->currency ?? 'mxn');

            return (new StripeSessionStatusResponse(
                sessionId: $sessionId,
                paymentStatus: $paymentStatus,
                status: $status,
                paymentId: $paymentId,
                amountTotal: $amountTotal,
                currency: $currency
            ))->toArray();
        } catch (\Throwable $e) {
            throw new BadRequestException('Error al consultar sesión de Stripe: ' . $e->getMessage());
        }
    }
}

