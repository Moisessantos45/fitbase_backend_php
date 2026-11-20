<?php

declare(strict_types=1);

namespace App\Features\DayPass;

class DayPassTicketResponse
{
    public function __construct(
        public int $id,
        public string $slug,
        public int $gymId,
        public string $gymName,
        public int $planId,
        public string $planName,
        public float $planPrice,
        public bool $isPaid,
        public string $fullName,
        public string $phone,
        public string $email,
        public string $visitDate,
        public bool $isUsed,
        public string $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            slug: (string)($data['slug'] ?? ''),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            planId: (int)($data['plan_id'] ?? 0),
            planName: (string)($data['plan_name'] ?? ''),
            planPrice: (float)($data['plan_price'] ?? 0.0),
            isPaid: (bool)($data['is_paid'] ?? false),
            fullName: (string)($data['full_name'] ?? ''),
            phone: (string)($data['phone'] ?? ''),
            email: (string)($data['email'] ?? ''),
            visitDate: (string)($data['visit_date'] ?? ''),
            isUsed: (bool)($data['is_used'] ?? false),
            createdAt: (string)($data['created_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'plan_id' => $this->planId,
            'plan_name' => $this->planName,
            'plan_price' => $this->planPrice,
            'is_paid' => $this->isPaid,
            'full_name' => $this->fullName,
            'phone' => $this->phone,
            'email' => $this->email,
            'visit_date' => $this->visitDate,
            'is_used' => $this->isUsed,
            'created_at' => $this->createdAt,
        ];
    }
}

