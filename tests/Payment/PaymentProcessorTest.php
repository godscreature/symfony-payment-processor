<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Entity\Order;
use App\Enum\Currency;
use App\Enum\PaymentGateway;
use App\Payment\Gateway\MonobankGateway;
use App\Payment\Gateway\StripeGateway;
use App\Payment\PaymentGatewayInterface;
use App\Payment\PaymentProcessor;
use App\Payment\PaymentResultDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PaymentProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{Currency, PaymentGateway, non-empty-string}>
     */
    public static function currencies(): iterable
    {
        yield 'UAH goes to Monobank' => [Currency::UAH, PaymentGateway::MONOBANK, 'mono_'];
        yield 'USD goes to Stripe' => [Currency::USD, PaymentGateway::STRIPE, 'ch_'];
        yield 'EUR goes to Stripe' => [Currency::EUR, PaymentGateway::STRIPE, 'ch_'];
    }

    /**
     * @param non-empty-string $transactionPrefix
     */
    #[DataProvider('currencies')]
    public function testChargesThroughGatewayForCurrency(
        Currency $currency,
        PaymentGateway $expectedGateway,
        string $transactionPrefix,
    ): void {
        $processor = new PaymentProcessor([new MonobankGateway(), new StripeGateway()]);
        $order = $this->order($currency);

        $result = $processor->charge($order);

        self::assertSame($expectedGateway, $result->gateway);
        self::assertStringStartsWith($transactionPrefix, $result->transactionId);
        self::assertSame($order->getTotalAmount(), $result->amount);
        self::assertSame($currency, $result->currency);
    }

    public function testUsesFirstGatewayThatSupportsCurrency(): void
    {
        $unsupported = $this->createMock(PaymentGatewayInterface::class);
        $unsupported->method('supports')->willReturn(false);
        $unsupported->expects(self::never())->method('charge');

        $order = $this->order(Currency::USD);
        $expected = new PaymentResultDto(PaymentGateway::STRIPE, 'tx_1', $order->getTotalAmount(), Currency::USD);

        $supported = $this->createMock(PaymentGatewayInterface::class);
        $supported->method('supports')->willReturn(true);
        $supported->expects(self::once())->method('charge')->with($order)->willReturn($expected);

        $later = $this->createMock(PaymentGatewayInterface::class);
        $later->expects(self::never())->method('supports');

        $processor = new PaymentProcessor([$unsupported, $supported, $later]);

        self::assertSame($expected, $processor->charge($order));
    }

    public function testFailsWhenNoGatewaySupportsCurrency(): void
    {
        $processor = new PaymentProcessor([new MonobankGateway()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No payment gateway supports currency EUR.');

        $processor->charge($this->order(Currency::EUR));
    }

    private function order(Currency $currency): Order
    {
        $order = new Order(Uuid::v7(), $currency);
        $order->addItem(Uuid::v7(), 2, 1500);

        return $order;
    }
}
