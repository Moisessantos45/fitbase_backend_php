<?php

declare(strict_types=1);

namespace App\Models;

class GymPhoto
{
    public function __construct(
        public int $id,
        public int $fileId,
        public int $gymId,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?FileModel $file = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            fileId: (int) ($data['file_id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            file: isset($data['file']) && is_array($data['file']) ? FileModel::fromArray($data['file']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'file_id'    => $this->fileId,
            'gym_id'     => $this->gymId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'file'       => $this->file?->toArray(),
        ];
    }
}

