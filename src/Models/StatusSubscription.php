<?php

declare(strict_types=1);

namespace App\Models;

class StatusSubscription
{
    public const int ACTIVE = 1;
    public const int INACTIVE = 2;
    public const int PENDING = 3;
    public const int EXPIRED = 4;

    public function __construct(
        public int $id,
        public string $name,
        public ?string $description = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            description: $data['description'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
