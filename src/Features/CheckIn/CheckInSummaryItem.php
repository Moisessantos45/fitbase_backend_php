<?php

declare(strict_types=1);

namespace App\Features\CheckIn;

class CheckInSummaryItem
{
    public function __construct(
        public int $id,
        public int $gymId,
        public string $gymName,
        public int $accessTypeId,
        public string $accessTypeName,
        public ?int $userId,
        public string $userFullName,
        public ?int $dayPassId,
        public ?int $subscriptionId,
        public ?int $registeredById,
        public string $registeredByName,
        public string $checkedInAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            accessTypeId: (int)($data['access_type_id'] ?? 0),
            accessTypeName: (string)($data['access_type_name'] ?? ''),
            userId: isset($data['user_id']) && $data['user_id'] !== null ? (int)$data['user_id'] : null,
            userFullName: (string)($data['user_full_name'] ?? ''),
            dayPassId: isset($data['day_pass_id']) && $data['day_pass_id'] !== null ? (int)$data['day_pass_id'] : null,
            subscriptionId: isset($data['subscription_id']) && $data['subscription_id'] !== null ? (int)$data['subscription_id'] : null,
            registeredById: isset($data['registered_by_id']) && $data['registered_by_id'] !== null ? (int)$data['registered_by_id'] : null,
            registeredByName: (string)($data['registered_by_name'] ?? ''),
            checkedInAt: (string)($data['checked_in_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'access_type_id' => $this->accessTypeId,
            'access_type_name' => $this->accessTypeName,
            'user_id' => $this->userId,
            'user_full_name' => $this->userFullName,
            'day_pass_id' => $this->dayPassId,
            'subscription_id' => $this->subscriptionId,
            'registered_by_id' => $this->registeredById,
            'registered_by_name' => $this->registeredByName,
            'checked_in_at' => $this->checkedInAt,
        ];
    }
}

