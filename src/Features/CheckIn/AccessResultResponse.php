<?php

declare(strict_types=1);

namespace App\Features\CheckIn;

class AccessResultResponse
{
    public function __construct(
        public bool $allowed,
        public string $message,
        public string $accessType,
        public string $fullName,
        public string $planName,
        public string $checkedInAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            allowed: (bool)($data['allowed'] ?? false),
            message: (string)($data['message'] ?? ''),
            accessType: (string)($data['access_type'] ?? ''),
            fullName: (string)($data['full_name'] ?? ''),
            planName: (string)($data['plan_name'] ?? ''),
            checkedInAt: (string)($data['checked_in_at'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'message' => $this->message,
            'access_type' => $this->accessType,
            'full_name' => $this->fullName,
            'plan_name' => $this->planName,
            'checked_in_at' => $this->checkedInAt,
        ];
    }
}

