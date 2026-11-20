<?php

declare(strict_types=1);

namespace App\Features\Analytics;

class GymAttendanceReport
{
    public function __construct(
        public int $gymId,
        public string $gymName,
        public int $totalCheckIns,
        public int $peakHour,
        public int $peakHourCount,
        public int $membershipsCount,
        public int $dayPassesCount,
        public int $guestsCount,
        public array $hourlyDistribution = []
    ) {}

    public static function fromArray(array $data): self
    {
        $distribution = [];
        if (!empty($data['hourly_distribution'])) {
            $raw = is_string($data['hourly_distribution']) ? json_decode($data['hourly_distribution'], true) : $data['hourly_distribution'];
            if (is_array($raw)) {
                $distribution = $raw;
            }
        }

        return new self(
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            totalCheckIns: (int)($data['total_check_ins'] ?? 0),
            peakHour: (int)($data['peak_hour'] ?? 0),
            peakHourCount: (int)($data['peak_hour_count'] ?? 0),
            membershipsCount: (int)($data['memberships_count'] ?? 0),
            dayPassesCount: (int)($data['day_passes_count'] ?? 0),
            guestsCount: (int)($data['guests_count'] ?? 0),
            hourlyDistribution: $distribution
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'total_check_ins' => $this->totalCheckIns,
            'peak_hour' => $this->peakHour,
            'peak_hour_count' => $this->peakHourCount,
            'memberships_count' => $this->membershipsCount,
            'day_passes_count' => $this->dayPassesCount,
            'guests_count' => $this->guestsCount,
            'hourly_distribution' => $this->hourlyDistribution,
        ];
    }
}

