<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class CashRegister implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $authId,
        public string $openTime,
        public float $openCash,
        public ?string $closeTime = null,
        public ?float $closeCash = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            authId: (int) ($data['auth_id'] ?? 0),
            openTime: (string) ($data['open_time'] ?? ''),
            openCash: (float) ($data['open_cash'] ?? 0.0),
            closeTime: $data['close_time'] ?? null,
            closeCash: isset($data['close_cash']) ? (float) $data['close_cash'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'gym_id'     => $this->gymId,
            'auth_id'    => $this->authId,
            'open_time'  => $this->openTime,
            'open_cash'  => $this->openCash,
            'close_time' => $this->closeTime,
            'close_cash' => $this->closeCash,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

