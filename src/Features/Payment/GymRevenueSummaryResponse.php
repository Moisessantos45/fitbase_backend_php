<?php

declare(strict_types=1);

namespace App\Features\Payment;

class GymRevenueSummaryResponse
{
    public function __construct(
        public int $gymId,
        public string $gymName,
        public float $totalRevenue,
        public int $totalTransactions
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            totalRevenue: (float)($data['total_revenue'] ?? 0.0),
            totalTransactions: (int)($data['total_transactions'] ?? 0)
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'total_revenue' => $this->totalRevenue,
            'total_transactions' => $this->totalTransactions,
        ];
    }
}

