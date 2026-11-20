<?php

declare(strict_types=1);

namespace App\Models;

class Role
{
    public const int ADMIN = 1;
    public const int OWNER = 2;
    public const int GYM_MANAGER = 3;
    public const int USER = 4;

    public function __construct(
        public int $id,
        public string $name,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

