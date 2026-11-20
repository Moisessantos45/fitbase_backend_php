<?php

declare(strict_types=1);

namespace App\Features\Analytics;

class DashboardSummaryReport
{
    public function __construct(
        public int $gymId,
        public string $gymName,
        public array $kpis,
        public array $recentPayments = [],
        public array $recentShifts = []
    ) {}

    public static function fromArray(array $data): self
    {
        $kpis = [];
        if (!empty($data['kpis'])) {
            $raw = is_string($data['kpis']) ? json_decode($data['kpis'], true) : $data['kpis'];
            if (is_array($raw)) $kpis = $raw;
        }

        $recentPayments = [];
        if (!empty($data['recent_payments'])) {
            $raw = is_string($data['recent_payments']) ? json_decode($data['recent_payments'], true) : $data['recent_payments'];
            if (is_array($raw)) $recentPayments = $raw;
        }

        $recentShifts = [];
        if (!empty($data['recent_shifts'])) {
            $raw = is_string($data['recent_shifts']) ? json_decode($data['recent_shifts'], true) : $data['recent_shifts'];
            if (is_array($raw)) $recentShifts = $raw;
        }

        return new self(
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            kpis: $kpis,
            recentPayments: $recentPayments,
            recentShifts: $recentShifts
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'kpis' => $this->kpis,
            'recent_payments' => $this->recentPayments,
            'recent_shifts' => $this->recentShifts,
        ];
    }
}

