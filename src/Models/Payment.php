<?php

declare(strict_types=1);

namespace App\Models;

class Payment
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $paymentMethodId,
        public int $paymentStatusId,
        public float $amount,
        public string $paymentDate,
        public ?int $subscriptionId = null,
        public ?int $dayPassId = null,
        public ?int $cashRegisterId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Gym $gym = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?PaymentStatus $paymentStatus = null,
        public ?Subscription $subscription = null,
        public ?DayPass $dayPass = null,
        public ?CashRegister $cashRegister = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            paymentMethodId: (int) ($data['payment_method_id'] ?? 0),
            paymentStatusId: (int) ($data['payment_status_id'] ?? 0),
            amount: (float) ($data['amount'] ?? 0.0),
            paymentDate: (string) ($data['payment_date'] ?? ''),
            subscriptionId: isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            dayPassId: isset($data['day_pass_id']) ? (int) $data['day_pass_id'] : null,
            cashRegisterId: isset($data['cash_register_id']) ? (int) $data['cash_register_id'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            gym: isset($data['gym']) && is_array($data['gym']) ? Gym::fromArray($data['gym']) : null,
            paymentMethod: isset($data['payment_method']) && is_array($data['payment_method']) ? PaymentMethod::fromArray($data['payment_method']) : null,
            paymentStatus: isset($data['payment_status']) && is_array($data['payment_status']) ? PaymentStatus::fromArray($data['payment_status']) : null,
            subscription: isset($data['subscription']) && is_array($data['subscription']) ? Subscription::fromArray($data['subscription']) : null,
            dayPass: isset($data['day_pass']) && is_array($data['day_pass']) ? DayPass::fromArray($data['day_pass']) : null,
            cashRegister: isset($data['cash_register']) && is_array($data['cash_register']) ? CashRegister::fromArray($data['cash_register']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'gym_id'             => $this->gymId,
            'subscription_id'    => $this->subscriptionId,
            'day_pass_id'        => $this->dayPassId,
            'cash_register_id'   => $this->cashRegisterId,
            'payment_method_id'  => $this->paymentMethodId,
            'payment_status_id'  => $this->paymentStatusId,
            'amount'             => $this->amount,
            'payment_date'       => $this->paymentDate,
            'created_at'         => $this->createdAt,
            'updated_at'         => $this->updatedAt,
            'gym'                => $this->gym?->toArray(),
            'payment_method'     => $this->paymentMethod?->toArray(),
            'payment_status'     => $this->paymentStatus?->toArray(),
            'subscription'       => $this->subscription?->toArray(),
            'day_pass'           => $this->dayPass?->toArray(),
            'cash_register'      => $this->cashRegister?->toArray(),
        ];
    }
}

