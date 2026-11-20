<?php

declare(strict_types=1);

namespace App\Models;

class GymManager
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $authId,
        public int $createdByAuthId,
        public string $firstName,
        public string $lastName,
        public string $phone,
        public ?int $avatarId = null,
        public ?string $avatarUrl = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Gym $gym = null,
        public ?Auth $auth = null,
        public ?FileModel $avatar = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            authId: (int) ($data['auth_id'] ?? 0),
            createdByAuthId: (int) ($data['created_by_auth_id'] ?? 0),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            avatarId: isset($data['avatar_id']) ? (int) $data['avatar_id'] : null,
            avatarUrl: $data['avatar_url'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            gym: isset($data['gym']) && is_array($data['gym']) ? Gym::fromArray($data['gym']) : null,
            auth: isset($data['auth']) && is_array($data['auth']) ? Auth::fromArray($data['auth']) : null,
            avatar: isset($data['avatar']) && is_array($data['avatar']) ? FileModel::fromArray($data['avatar']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'gym_id'             => $this->gymId,
            'auth_id'            => $this->authId,
            'created_by_auth_id' => $this->createdByAuthId,
            'first_name'         => $this->firstName,
            'last_name'          => $this->lastName,
            'phone'              => $this->phone,
            'avatar_id'          => $this->avatarId,
            'avatar_url'         => $this->avatarUrl,
            'created_at'         => $this->createdAt,
            'updated_at'         => $this->updatedAt,
            'gym'                => $this->gym?->toArray(),
            'auth'               => $this->auth?->toPublicArray(),
            'avatar'             => $this->avatar?->toArray(),
        ];
    }
}

