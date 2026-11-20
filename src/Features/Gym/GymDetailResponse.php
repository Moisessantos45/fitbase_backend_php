<?php

declare(strict_types=1);

namespace App\Features\Gym;

class GymDetailResponse
{
    /**
     * @param array<array{id: int, gym_id: int, day_of_week: int, open_minutes: int, close_minutes: int, is_closed: bool}> $schedules
     * @param array<array{id: int, slug: string, url: string}> $photos
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public string $phone,
        public string $email,
        public float $latitude,
        public float $longitude,
        public ?string $backgroundSlug = null,
        public ?string $backgroundUrl = null,
        public bool $isOpen = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public array $schedules = [],
        public array $photos = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            latitude: (float) ($data['latitude'] ?? 0.0),
            longitude: (float) ($data['longitude'] ?? 0.0),
            backgroundSlug: $data['background_slug'] ?? null,
            backgroundUrl: $data['background_url'] ?? null,
            isOpen: (bool) ($data['is_open'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            schedules: $data['schedules'] ?? [],
            photos: $data['photos'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'address'         => $this->address,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'background_url'  => $this->backgroundUrl,
            'is_open'         => $this->isOpen,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
            'schedules'       => $this->schedules,
            'photos'          => $this->photos,
        ];
    }
}

