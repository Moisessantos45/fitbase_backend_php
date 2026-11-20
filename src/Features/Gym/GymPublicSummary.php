<?php

declare(strict_types=1);

namespace App\Features\Gym;

class GymPublicSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public string $phone,
        public string $email,
        public float $latitude,
        public float $longitude,
        public bool $isOpen = false,
        public ?string $backgroundSlug = null,
        public ?string $backgroundUrl = null,
        public ?float $distanceMeters = null
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
            isOpen: (bool) ($data['is_open'] ?? false),
            backgroundSlug: $data['background_slug'] ?? null,
            backgroundUrl: $data['background_url'] ?? null,
            distanceMeters: isset($data['distance_meters']) ? (float) $data['distance_meters'] : null
        );
    }

    public function toArray(): array
    {
        $res = [
            'id'             => $this->id,
            'name'           => $this->name,
            'address'        => $this->address,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'is_open'        => $this->isOpen,
            'background_url' => $this->backgroundUrl,
        ];

        if ($this->distanceMeters !== null) {
            $res['distance_meters'] = $this->distanceMeters;
        }

        return $res;
    }
}

