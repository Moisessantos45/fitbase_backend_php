<?php

declare(strict_types=1);

namespace App\Features\Plan;

class EnrollmentResponse
{
    public function __construct(
        public int $gymId,
        public string $gymName,
        public bool $hasActiveSubscription,
        public ?array $activeSubscription,
        public array $plans = []
    ) {}

    public static function fromArray(array $data): self
    {
        $activeSub = null;
        if (!empty($data['active_subscription'])) {
            $activeSub = is_string($data['active_subscription'])
                ? json_decode($data['active_subscription'], true)
                : $data['active_subscription'];
        }

        $plans = [];
        if (!empty($data['plans'])) {
            $rawPlans = is_string($data['plans'])
                ? json_decode($data['plans'], true)
                : $data['plans'];
            if (is_array($rawPlans)) {
                $plans = $rawPlans;
            }
        }

        return new self(
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            hasActiveSubscription: (bool)($data['has_active_subscription'] ?? false),
            activeSubscription: $activeSub,
            plans: $plans
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'has_active_subscription' => $this->hasActiveSubscription,
            'active_subscription' => $this->activeSubscription,
            'plans' => $this->plans,
        ];
    }
}

