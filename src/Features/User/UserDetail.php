<?php

declare(strict_types=1);

namespace App\Features\User;

class UserDetail
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $slug = null,
        public ?string $avatarUrl = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            slug: $data['slug'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}

