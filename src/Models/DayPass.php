<?php

declare(strict_types=1);

namespace App\Models;

class DayPass
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $planId,
        public string $slug,
        public string $visitDate,
        public string $fullName,
        public string $phone,
        public string $email,
        public ?int $userId = null,
        public bool $isUsed = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Gym $gym = null,
        public ?Plan $plan = null,
        public ?User $user = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            planId: (int) ($data['plan_id'] ?? 0),
            slug: (string) ($data['slug'] ?? ''),
            visitDate: (string) ($data['visit_date'] ?? ''),
            fullName: (string) ($data['full_name'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            isUsed: (bool) ($data['is_used'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            gym: isset($data['gym']) && is_array($data['gym']) ? Gym::fromArray($data['gym']) : null,
            plan: isset($data['plan']) && is_array($data['plan']) ? Plan::fromArray($data['plan']) : null,
            user: isset($data['user']) && is_array($data['user']) ? User::fromArray($data['user']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'gym_id'     => $this->gymId,
            'plan_id'    => $this->planId,
            'slug'       => $this->slug,
            'visit_date' => $this->visitDate,
            'full_name'  => $this->fullName,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'is_used'    => $this->isUsed,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'gym'        => $this->gym?->toArray(),
            'plan'       => $this->plan?->toArray(),
            'user'       => $this->user?->toArray(),
        ];
    }
}

