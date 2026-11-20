<?php

declare(strict_types=1);

namespace App\Features\Manager;

class ManagerItems
{
    public function __construct(
        public int $id,
        public int $gymId,
        public string $gymName,
        public int $authId,
        public int $createdByAuthId,
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $email,
        public ?string $slug = null,
        public ?string $avatarUrl = null,
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            gymName: (string) ($data['gym_name'] ?? ''),
            authId: (int) ($data['auth_id'] ?? 0),
            createdByAuthId: (int) ($data['created_by_auth_id'] ?? 0),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            slug: $data['slug'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            createdAt: $data['created_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'gym_id'             => $this->gymId,
            'gym_name'           => $this->gymName,
            'auth_id'            => $this->authId,
            'created_by_auth_id' => $this->createdByAuthId,
            'first_name'         => $this->firstName,
            'last_name'          => $this->lastName,
            'phone'              => $this->phone,
            'email'              => $this->email,
            'avatar_url'         => $this->avatarUrl,
            'created_at'         => $this->createdAt,
        ];
    }
}

