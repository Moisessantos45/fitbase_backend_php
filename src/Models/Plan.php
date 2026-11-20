<?php

declare(strict_types=1);

namespace App\Models;

class Plan
{
    public function __construct(
        public int $id,
        public int $gymId,
        public string $name,
        public string $description,
        public float $price,
        public float $discount = 0.0,
        public int $duration = 30,
        public string $planType = 'monthly',
        public ?int $maxVisits = null,
        public bool $active = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Gym $gym = null,
        public array $items = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            price: (float) ($data['price'] ?? 0.0),
            discount: (float) ($data['discount'] ?? 0.0),
            duration: (int) ($data['duration'] ?? 30),
            planType: (string) ($data['plan_type'] ?? 'monthly'),
            maxVisits: isset($data['max_visits']) ? (int) $data['max_visits'] : null,
            active: (bool) ($data['active'] ?? true),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            gym: isset($data['gym']) && is_array($data['gym']) ? Gym::fromArray($data['gym']) : null,
            items: $data['items'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'gym_id'      => $this->gymId,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'discount'    => $this->discount,
            'duration'    => $this->duration,
            'plan_type'   => $this->planType,
            'max_visits'  => $this->maxVisits,
            'active'      => $this->active,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
            'gym'         => $this->gym?->toArray(),
            'items'       => $this->items,
        ];
    }
}

