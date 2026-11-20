<?php

declare(strict_types=1);

namespace App\Features\Analytics;

class ClientMembershipHistoryReport
{
    public function __construct(
        public int $userId,
        public string $fullName,
        public string $email,
        public string $phone,
        public float $totalSpent,
        public int $totalCheckIns,
        public array $subscriptions = [],
        public array $payments = [],
        public array $recentCheckIns = []
    ) {}

    public static function fromArray(array $data): self
    {
        $decodeList = function ($val): array {
            if (empty($val)) return [];
            $raw = is_string($val) ? json_decode($val, true) : $val;
            return is_array($raw) ? $raw : [];
        };

        return new self(
            userId: (int)($data['user_id'] ?? 0),
            fullName: (string)($data['full_name'] ?? ''),
            email: (string)($data['email'] ?? ''),
            phone: (string)($data['phone'] ?? ''),
            totalSpent: (float)($data['total_spent'] ?? 0.0),
            totalCheckIns: (int)($data['total_check_ins'] ?? 0),
            subscriptions: $decodeList($data['subscriptions'] ?? []),
            payments: $decodeList($data['payments'] ?? []),
            recentCheckIns: $decodeList($data['recent_check_ins'] ?? [])
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'total_spent' => $this->totalSpent,
            'total_check_ins' => $this->totalCheckIns,
            'subscriptions' => $this->subscriptions,
            'payments' => $this->payments,
            'recent_check_ins' => $this->recentCheckIns,
        ];
    }
}

