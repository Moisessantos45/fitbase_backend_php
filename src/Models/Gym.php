<?php

declare(strict_types=1);

namespace App\Models;

class Gym
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public string $name,
        public string $address,
        public string $phone,
        public string $email,
        public float $latitude,
        public float $longitude,
        public ?int $backgroundId = null,
        public ?string $backgroundUrl = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Owner $owner = null,
        public ?FileModel $background = null,
        public array $schedules = [],
        public array $photos = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            ownerId: (int) ($data['owner_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            latitude: (float) ($data['latitude'] ?? 0.0),
            longitude: (float) ($data['longitude'] ?? 0.0),
            backgroundId: isset($data['background_id']) ? (int) $data['background_id'] : null,
            backgroundUrl: $data['background_url'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            owner: isset($data['owner']) && is_array($data['owner']) ? Owner::fromArray($data['owner']) : null,
            background: isset($data['background']) && is_array($data['background']) ? FileModel::fromArray($data['background']) : null,
            schedules: $data['schedules'] ?? [],
            photos: $data['photos'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'owner_id'       => $this->ownerId,
            'name'           => $this->name,
            'address'        => $this->address,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'background_id'  => $this->backgroundId,
            'background_url' => $this->backgroundUrl,
            'created_at'     => $this->createdAt,
            'updated_at'     => $this->updatedAt,
            'owner'          => $this->owner?->toArray(),
            'background'     => $this->background?->toArray(),
            'schedules'      => $this->schedules,
            'photos'         => $this->photos,
        ];
    }
}

