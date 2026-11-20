<?php

declare(strict_types=1);

namespace App\Models;

class GymSchedule
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $dayOfWeek,
        public int $openMinutes,
        public int $closeMinutes,
        public bool $isClosed = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            dayOfWeek: (int) ($data['day_of_week'] ?? 0),
            openMinutes: (int) ($data['open_minutes'] ?? 0),
            closeMinutes: (int) ($data['close_minutes'] ?? 0),
            isClosed: (bool) ($data['is_closed'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'gym_id'        => $this->gymId,
            'day_of_week'   => $this->dayOfWeek,
            'open_minutes'  => $this->openMinutes,
            'close_minutes' => $this->closeMinutes,
            'is_closed'     => $this->isClosed,
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
        ];
    }
}

