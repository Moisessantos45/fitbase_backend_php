<?php

declare(strict_types=1);

namespace App\Models;

class PlanItem
{
    public function __construct(
        public int $id,
        public int $planId,
        public string $name,
        public string $description,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            planId: (int) ($data['plan_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'plan_id'     => $this->planId,
            'name'        => $this->name,
            'description' => $this->description,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}

