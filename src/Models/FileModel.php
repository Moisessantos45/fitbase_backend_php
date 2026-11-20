<?php

declare(strict_types=1);

namespace App\Models;

class FileModel
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $path,
        public string $mimeType,
        public int $size,
        public int $authorId,
        public ?string $url = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            slug: (string) ($data['slug'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            mimeType: (string) ($data['mime_type'] ?? ''),
            size: (int) ($data['size'] ?? 0),
            authorId: (int) ($data['author_id'] ?? 0),
            url: $data['url'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'slug'       => $this->slug,
            'mime_type'  => $this->mimeType,
            'size'       => $this->size,
            'author_id'  => $this->authorId,
            'url'        => $this->url,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

