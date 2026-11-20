<?php

declare(strict_types=1);

namespace App\Features\Payment;

class StripeSessionStatusResponse
{
    public function __construct(
        public string $sessionId,
        public string $paymentStatus,
        public string $status,
        public int $paymentId,
        public float $amountTotal,
        public string $currency
    ) {}

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'payment_status' => $this->paymentStatus,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'amount_total' => $this->amountTotal,
            'currency' => $this->currency,
        ];
    }
}

