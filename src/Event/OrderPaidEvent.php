<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\PaymentGateway;
use Symfony\Component\Uid\Uuid;

/**
 * Dispatched after a payment is committed. Extension point for Step 4:
 * a listener will publish OrderPaidMessage to RabbitMQ (orders_high_priority).
 */
final readonly class OrderPaidEvent
{
    public function __construct(
        public Uuid $orderId,
        public PaymentGateway $paymentGateway,
        public string $transactionId,
    ) {
    }
}
