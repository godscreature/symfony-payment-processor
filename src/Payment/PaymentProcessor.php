<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Order;
use App\Exception\PaymentFailedException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Strategy context: picks the gateway that supports the order's currency.
 * A new provider only needs a new PaymentGatewayInterface implementation; this class does not change.
 */
final readonly class PaymentProcessor
{
    /**
     * @param iterable<PaymentGatewayInterface> $gateways
     */
    public function __construct(
        #[AutowireIterator(PaymentGatewayInterface::TAG)]
        private iterable $gateways,
    ) {
    }

    /**
     * @throws PaymentFailedException
     */
    public function charge(Order $order): PaymentResultDto
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($order->getCurrency())) {
                return $gateway->charge($order);
            }
        }

        throw new \LogicException(\sprintf('No payment gateway supports currency %s.', $order->getCurrency()->value));
    }
}
