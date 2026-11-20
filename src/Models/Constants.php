<?php

declare(strict_types=1);

namespace App\Models;

class Constants
{
    public const int ROLE_ADMIN_ID       = 1;
    public const int ROLE_OWNER_ID       = 2;
    public const int ROLE_GYM_MANAGER_ID = 3;
    public const int ROLE_USER_ID        = 4;

    public const int STATUS_SUBSCRIPTION_ACTIVE_ID   = 1;
    public const int STATUS_SUBSCRIPTION_INACTIVE_ID = 2;
    public const int STATUS_SUBSCRIPTION_PENDING_ID  = 3;
    public const int STATUS_SUBSCRIPTION_EXPIRED_ID  = 4;

    public const int ACCESS_TYPE_MEMBERSHIP_ID = 1;
    public const int ACCESS_TYPE_DAYPASS_ID    = 2;
    public const int ACCESS_TYPE_GUEST_ID      = 3;

    public const int PAYMENT_METHOD_CASH_ID          = 1;
    public const int PAYMENT_METHOD_CREDIT_CARD_ID   = 2;
    public const int PAYMENT_METHOD_DEBIT_CARD_ID    = 3;
    public const int PAYMENT_METHOD_BANK_TRANSFER_ID = 4;
    public const int PAYMENT_METHOD_PAYPAL_ID        = 5;
    public const int PAYMENT_METHOD_CRYPTO_ID        = 6;

    public const int PAYMENT_STATUS_PENDING_ID  = 1;
    public const int PAYMENT_STATUS_PAID_ID     = 2;
    public const int PAYMENT_STATUS_CANCELED_ID = 3;
    public const int PAYMENT_STATUS_REFUNDED_ID = 4;
}

