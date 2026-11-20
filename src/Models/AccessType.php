<?php

declare(strict_types=1);

namespace App\Models;

class AccessType
{
    public const int MEMBERSHIP = 1;
    public const int DAYPASS = 2;
    public const int GUEST = 3;

    public function __construct(
        public int $id,
        public string $name,
        public bool $active = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            active: (bool) ($data['active'] ?? true),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'active'     => $this->active,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

