<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Payment providers. The value is stored in orders.payment_gateway and returned by the API.
 */
enum PaymentGateway: string
{
    case MONOBANK = 'monobank';
    case STRIPE = 'stripe';
}
