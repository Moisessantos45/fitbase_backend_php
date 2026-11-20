<?php

declare(strict_types=1);

namespace App\Models;

class CheckIn
{
    public function __construct(
        public int $id,
        public int $gymId,
        public int $accessTypeId,
        public ?int $userId = null,
        public ?int $dayPassId = null,
        public ?int $subscriptionId = null,
        public ?int $registeredById = null,
        public ?string $checkedInAt = null,
        public ?Gym $gym = null,
        public ?AccessType $accessType = null,
        public ?User $user = null,
        public ?DayPass $dayPass = null,
        public ?Subscription $subscription = null,
        public ?Auth $registeredBy = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            gymId: (int) ($data['gym_id'] ?? 0),
            accessTypeId: (int) ($data['access_type_id'] ?? 0),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            dayPassId: isset($data['day_pass_id']) ? (int) $data['day_pass_id'] : null,
            subscriptionId: isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            registeredById: isset($data['registered_by_id']) ? (int) $data['registered_by_id'] : null,
            checkedInAt: $data['checked_in_at'] ?? null,
            gym: isset($data['gym']) && is_array($data['gym']) ? Gym::fromArray($data['gym']) : null,
            accessType: isset($data['access_type']) && is_array($data['access_type']) ? AccessType::fromArray($data['access_type']) : null,
            user: isset($data['user']) && is_array($data['user']) ? User::fromArray($data['user']) : null,
            dayPass: isset($data['day_pass']) && is_array($data['day_pass']) ? DayPass::fromArray($data['day_pass']) : null,
            subscription: isset($data['subscription']) && is_array($data['subscription']) ? Subscription::fromArray($data['subscription']) : null,
            registeredBy: isset($data['registered_by']) && is_array($data['registered_by']) ? Auth::fromArray($data['registered_by']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'gym_id'           => $this->gymId,
            'access_type_id'   => $this->accessTypeId,
            'user_id'          => $this->userId,
            'day_pass_id'      => $this->dayPassId,
            'subscription_id'  => $this->subscriptionId,
            'registered_by_id' => $this->registeredById,
            'checked_in_at'    => $this->checkedInAt,
            'gym'              => $this->gym?->toArray(),
            'access_type'      => $this->accessType?->toArray(),
            'user'             => $this->user?->toArray(),
            'day_pass'         => $this->dayPass?->toArray(),
            'subscription'     => $this->subscription?->toArray(),
            'registered_by'    => $this->registeredBy?->toPublicArray(),
        ];
    }
}

