<?php

declare(strict_types=1);

namespace App\Models;

class Subscription
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $planId,
        public int $statusId,
        public string $slug,
        public string $startDate,
        public string $endDate,
        public int $paymentMethodId,
        public bool $autoRenew = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?User $user = null,
        public ?Plan $plan = null,
        public ?StatusSubscription $status = null,
        public ?PaymentMethod $paymentMethod = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            userId: (int) ($data['user_id'] ?? 0),
            planId: (int) ($data['plan_id'] ?? 0),
            statusId: (int) ($data['status_id'] ?? 0),
            slug: (string) ($data['slug'] ?? ''),
            startDate: (string) ($data['start_date'] ?? ''),
            endDate: (string) ($data['end_date'] ?? ''),
            paymentMethodId: (int) ($data['payment_method_id'] ?? 0),
            autoRenew: (bool) ($data['auto_renew'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            user: isset($data['user']) && is_array($data['user']) ? User::fromArray($data['user']) : null,
            plan: isset($data['plan']) && is_array($data['plan']) ? Plan::fromArray($data['plan']) : null,
            status: isset($data['status']) && is_array($data['status']) ? StatusSubscription::fromArray($data['status']) : null,
            paymentMethod: isset($data['payment_method']) && is_array($data['payment_method']) ? PaymentMethod::fromArray($data['payment_method']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->userId,
            'plan_id'           => $this->planId,
            'status_id'         => $this->statusId,
            'slug'              => $this->slug,
            'start_date'        => $this->startDate,
            'end_date'          => $this->endDate,
            'payment_method_id' => $this->paymentMethodId,
            'auto_renew'        => $this->autoRenew,
            'created_at'        => $this->createdAt,
            'updated_at'        => $this->updatedAt,
            'user'              => $this->user?->toArray(),
            'plan'              => $this->plan?->toArray(),
            'status'            => $this->status?->toArray(),
            'payment_method'    => $this->paymentMethod?->toArray(),
        ];
    }
}

