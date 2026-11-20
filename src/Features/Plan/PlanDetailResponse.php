<?php

declare(strict_types=1);

namespace App\Features\Plan;

class PlanDetailResponse
{
    public function __construct(
        public int $id,
        public int $gymId,
        public string $gymName,
        public string $name,
        public string $description,
        public float $price,
        public float $discount,
        public float $finalPrice,
        public int $duration,
        public string $planType,
        public ?int $maxVisits,
        public bool $active,
        public array $items = []
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items'])) {
            $rawItems = is_string($data['items']) ? json_decode($data['items'], true) : $data['items'];
            if (is_array($rawItems)) {
                $items = array_map(function ($it) {
                    return [
                        'id' => (int)($it['id'] ?? 0),
                        'name' => (string)($it['name'] ?? ''),
                        'description' => (string)($it['description'] ?? ''),
                    ];
                }, $rawItems);
            }
        }

        return new self(
            id: (int)($data['id'] ?? 0),
            gymId: (int)($data['gym_id'] ?? 0),
            gymName: (string)($data['gym_name'] ?? ''),
            name: (string)($data['name'] ?? ''),
            description: (string)($data['description'] ?? ''),
            price: (float)($data['price'] ?? 0.0),
            discount: (float)($data['discount'] ?? 0.0),
            finalPrice: (float)($data['final_price'] ?? 0.0),
            duration: (int)($data['duration'] ?? 0),
            planType: (string)($data['plan_type'] ?? ''),
            maxVisits: isset($data['max_visits']) && $data['max_visits'] !== null ? (int)$data['max_visits'] : null,
            active: (bool)($data['active'] ?? true),
            items: $items
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gymId,
            'gym_name' => $this->gymName,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'discount' => $this->discount,
            'final_price' => $this->finalPrice,
            'duration' => $this->duration,
            'plan_type' => $this->planType,
            'max_visits' => $this->maxVisits,
            'active' => $this->active,
            'items' => $this->items,
        ];
    }
}

