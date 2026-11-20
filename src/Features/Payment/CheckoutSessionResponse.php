<?php

declare(strict_types=1);

namespace App\Features\Payment;

class CheckoutSessionResponse
{
    public function __construct(
        public string $sessionId,
        public string $checkoutUrl,
        public int $paymentId,
        public float $amount
    ) {}

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'checkout_url' => $this->checkoutUrl,
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
        ];
    }
}

