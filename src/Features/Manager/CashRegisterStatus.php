<?php

declare(strict_types=1);

namespace App\Features\Manager;

use JsonSerializable;

class CashRegisterStatus implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $authId,
        public string $openTime,
        public float $openCash,
        public ?string $closeTime = null,
        public ?float $closeCash = null,
        public bool $isOpen = true
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
            isOpen: (bool) ($data['is_open'] ?? ($data['close_time'] === null))
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'gym_id'     => $this->gymId,
            'auth_id'    => $this->authId,
            'open_time'  => $this->openTime,
            'close_time' => $this->closeTime,
            'open_cash'  => $this->openCash,
            'close_cash' => $this->closeCash,
            'is_open'    => $this->isOpen,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

