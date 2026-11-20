<?php

declare(strict_types=1);

namespace App\Features\Manager;

class ManagerDetail
{
    public function __construct(
        public int $id,
        public int $authId,
        public string $firstName,
        public string $lastName,
        public string $phone,
        public ?string $slug = null,
        public ?string $avatarUrl = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            authId: (int) ($data['auth_id'] ?? 0),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            slug: $data['slug'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'auth_id'    => $this->authId,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'phone'      => $this->phone,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}

