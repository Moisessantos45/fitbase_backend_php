<?php

declare(strict_types=1);

namespace App\Features\Payment;

class PaymentReceiptResponse
{
    public function __construct(
        public int $id,
        public int $gymId,
        public string $gymName,
        public int $paymentMethodId,
        public string $paymentMethodName,
        public int $paymentStatusId,
        public string $paymentStatusName,
        public float $amount,
        public ?int $subscriptionId,
        public ?int $dayPassId,
        public ?int $cashRegisterId,
        public string $paymentDate,
        public string $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            paymentMethodId: (int)($data['payment_method_id'] ?? 0),
            paymentMethodName: (string)($data['payment_method_name'] ?? ''),
            paymentStatusId: (int)($data['payment_status_id'] ?? 0),
            paymentStatusName: (string)($data['payment_status_name'] ?? ''),
            amount: (float)($data['amount'] ?? 0.0),
            subscriptionId: isset($data['subscription_id']) && $data['subscription_id'] !== null ? (int)$data['subscription_id'] : null,
            dayPassId: isset($data['day_pass_id']) && $data['day_pass_id'] !== null ? (int)$data['day_pass_id'] : null,
            cashRegisterId: isset($data['cash_register_id']) && $data['cash_register_id'] !== null ? (int)$data['cash_register_id'] : null,
            paymentDate: (string)($data['payment_date'] ?? ''),
            createdAt: (string)($data['created_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        $arr = [
            'id' => $this->id,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'payment_method_id' => $this->paymentMethodId,
            'payment_method_name' => $this->paymentMethodName,
            'payment_status_id' => $this->paymentStatusId,
            'payment_status_name' => $this->paymentStatusName,
            'amount' => $this->amount,
            'payment_date' => $this->paymentDate,
            'created_at' => $this->createdAt,
        ];
        if ($this->subscriptionId !== null) $arr['subscription_id'] = $this->subscriptionId;
        if ($this->dayPassId !== null) $arr['day_pass_id'] = $this->dayPassId;
        if ($this->cashRegisterId !== null) $arr['cash_register_id'] = $this->cashRegisterId;

        return $arr;
    }
}

