<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REFUNDING = 'refunding';
    case REFUNDED = 'refunded';
}
