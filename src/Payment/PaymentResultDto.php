<?php

declare(strict_types=1);

namespace App\Payment;

use App\Enum\Currency;
use App\Enum\PaymentGateway;

/**
 * Successful charge, already translated from the provider's own response format.
 */
final readonly class PaymentResultDto
{
    public function __construct(
        public PaymentGateway $gateway,
        public string $transactionId,
        /** Charged amount in minor units (kopecks / cents). */
        public int $amount,
        public Currency $currency,
    ) {
    }
}
