<?php

declare(strict_types=1);

namespace App\Features\Subscription;

class SubscriptionDetailResponse
{
    public function __construct(
        public int $id,
        public string $slug,
        public int $userId,
        public string $userFullName,
        public string $userEmail,
        public string $userPhone,
        public int $gymId,
        public string $gymName,
        public int $planId,
        public string $planName,
        public float $planPrice,
        public int $statusId,
        public string $statusName,
        public string $startDate,
        public string $endDate,
        public int $remainingDays,
        public int $paymentMethodId,
        public string $paymentMethodName,
        public bool $autoRenew,
        public string $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            slug: (string)($data['slug'] ?? ''),
            userId: (int)($data['user_id'] ?? 0),
            userFullName: (string)($data['user_full_name'] ?? ''),
            userEmail: (string)($data['user_email'] ?? ''),
            userPhone: (string)($data['user_phone'] ?? ''),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            planId: (int)($data['plan_id'] ?? 0),
            planName: (string)($data['plan_name'] ?? ''),
            planPrice: (float)($data['plan_price'] ?? 0.0),
            statusId: (int)($data['status_id'] ?? 0),
            statusName: (string)($data['status_name'] ?? ''),
            startDate: (string)($data['start_date'] ?? ''),
            endDate: (string)($data['end_date'] ?? ''),
            remainingDays: (int)($data['remaining_days'] ?? 0),
            paymentMethodId: (int)($data['payment_method_id'] ?? 0),
            paymentMethodName: (string)($data['payment_method_name'] ?? ''),
            autoRenew: (bool)($data['auto_renew'] ?? false),
            createdAt: (string)($data['created_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'user_id' => $this->userId,
            'user_full_name' => $this->userFullName,
            'user_email' => $this->userEmail,
            'user_phone' => $this->userPhone,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'plan_id' => $this->planId,
            'plan_name' => $this->planName,
            'plan_price' => $this->planPrice,
            'status_id' => $this->statusId,
            'status_name' => $this->statusName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'remaining_days' => $this->remainingDays,
            'payment_method_id' => $this->paymentMethodId,
            'payment_method_name' => $this->paymentMethodName,
            'auto_renew' => $this->autoRenew,
            'created_at' => $this->createdAt,
        ];
    }
}

