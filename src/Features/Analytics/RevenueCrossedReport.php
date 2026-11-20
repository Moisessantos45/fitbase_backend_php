<?php

declare(strict_types=1);

namespace App\Features\Analytics;

class RevenueCrossedReport
{
    public function __construct(
        public int $gymId,
        public string $gymName,
        public float $totalRevenue,
        public array $byPaymentMethod = [],
        public array $byPlan = []
    ) {}

    public static function fromArray(array $data): self
    {
        $byMethod = [];
        if (!empty($data['by_payment_method'])) {
            $raw = is_string($data['by_payment_method']) ? json_decode($data['by_payment_method'], true) : $data['by_payment_method'];
            if (is_array($raw)) $byMethod = $raw;
        }

        $byPlan = [];
        if (!empty($data['by_plan'])) {
            $raw = is_string($data['by_plan']) ? json_decode($data['by_plan'], true) : $data['by_plan'];
            if (is_array($raw)) $byPlan = $raw;
        }

        return new self(
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            totalRevenue: (float)($data['total_revenue'] ?? 0.0),
            byPaymentMethod: $byMethod,
            byPlan: $byPlan
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'total_revenue' => $this->totalRevenue,
            'by_payment_method' => $this->byPaymentMethod,
            'by_plan' => $this->byPlan,
        ];
    }
}

