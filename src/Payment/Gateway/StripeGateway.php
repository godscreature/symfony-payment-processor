<?php

declare(strict_types=1);

namespace App\Payment\Gateway;

use App\Entity\Order;
use App\Enum\Currency;
use App\Enum\PaymentGateway;
use App\Exception\PaymentFailedException;
use App\Payment\PaymentGatewayInterface;
use App\Payment\PaymentResultDto;

/**
 * Adapter for Stripe (USD, EUR). Translates an Order into a Stripe charge request
 * (amount in cents, lowercase currency code) and its response into PaymentResultDto.
 * The HTTP call itself is simulated.
 */
final class StripeGateway implements PaymentGatewayInterface
{
    public function supports(Currency $currency): bool
    {
        return $currency === Currency::USD || $currency === Currency::EUR;
    }

    public function charge(Order $order): PaymentResultDto
    {
        $response = $this->createCharge([
            'amount' => $order->getTotalAmount(),
            'currency' => strtolower($order->getCurrency()->value),
            'metadata' => ['order_id' => $order->getId()->toRfc4122()],
        ]);

        if ($response['status'] !== 'succeeded') {
            throw PaymentFailedException::declined(PaymentGateway::STRIPE, $response['status']);
        }

        return new PaymentResultDto(
            PaymentGateway::STRIPE,
            $response['id'],
            $order->getTotalAmount(),
            $order->getCurrency(),
        );
    }

    /**
     * Simulated Stripe API call.
     *
     * @param array{amount: int, currency: string, metadata: array{order_id: string}} $request
     *
     * @return array{id: string, status: string}
     */
    private function createCharge(array $request): array
    {
        return ['id' => 'ch_'.bin2hex(random_bytes(12)), 'status' => 'succeeded'];
    }
}
