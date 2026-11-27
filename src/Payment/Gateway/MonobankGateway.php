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
 * Adapter for Monobank acquiring (UAH). Translates an Order into Monobank's invoice request
 * (amount in kopecks, ISO 4217 numeric currency code) and its response into PaymentResultDto.
 * The HTTP call itself is simulated.
 */
final class MonobankGateway implements PaymentGatewayInterface
{
    /** ISO 4217 numeric code Monobank expects in "ccy". */
    private const int UAH_ISO_CODE = 980;

    public function supports(Currency $currency): bool
    {
        return $currency === Currency::UAH;
    }

    public function charge(Order $order): PaymentResultDto
    {
        $response = $this->createInvoice([
            'amount' => $order->getTotalAmount(),
            'ccy' => self::UAH_ISO_CODE,
            'merchantPaymInfo' => ['reference' => $order->getId()->toRfc4122()],
        ]);

        if ($response['status'] !== 'success') {
            throw PaymentFailedException::declined(PaymentGateway::MONOBANK, $response['status']);
        }

        return new PaymentResultDto(
            PaymentGateway::MONOBANK,
            $response['invoiceId'],
            $order->getTotalAmount(),
            $order->getCurrency(),
        );
    }

    /**
     * Simulated Monobank API call.
     *
     * @param array{amount: int, ccy: int, merchantPaymInfo: array{reference: string}} $request
     *
     * @return array{invoiceId: string, status: string}
     */
    private function createInvoice(array $request): array
    {
        return ['invoiceId' => 'mono_'.bin2hex(random_bytes(12)), 'status' => 'success'];
    }
}
