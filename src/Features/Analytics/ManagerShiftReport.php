<?php

declare(strict_types=1);

namespace App\Features\Analytics;

class ManagerShiftReport
{
    public function __construct(
        public int $shiftId,
        public int $gymId,
        public string $gymName,
        public int $managerAuthId,
        public string $managerName,
        public string $managerEmail,
        public string $openTime,
        public ?string $closeTime,
        public float $openCash,
        public ?float $closeCash,
        public float $totalCollected,
        public ?float $difference,
        public string $status
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            shiftId: (int)($data['shift_id'] ?? 0),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            managerAuthId: (int)($data['manager_auth_id'] ?? 0),
            managerName: (string)($data['manager_name'] ?? ''),
            managerEmail: (string)($data['manager_email'] ?? ''),
            openTime: (string)($data['open_time'] ?? ''),
            closeTime: isset($data['close_time']) && $data['close_time'] !== null ? (string)$data['close_time'] : null,
            openCash: (float)($data['open_cash'] ?? 0.0),
            closeCash: isset($data['close_cash']) && $data['close_cash'] !== null ? (float)$data['close_cash'] : null,
            totalCollected: (float)($data['total_collected'] ?? 0.0),
            difference: isset($data['difference']) && $data['difference'] !== null ? (float)$data['difference'] : null,
            status: (string)($data['status'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'shift_id' => $this->shiftId,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'manager_auth_id' => $this->managerAuthId,
            'manager_name' => $this->managerName,
            'manager_email' => $this->managerEmail,
            'open_time' => $this->openTime,
            'close_time' => $this->closeTime,
            'open_cash' => $this->openCash,
            'close_cash' => $this->closeCash,
            'total_collected' => $this->totalCollected,
            'difference' => $this->difference,
            'status' => $this->status,
        ];
    }
}

