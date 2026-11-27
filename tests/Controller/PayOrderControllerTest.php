<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Enum\Currency;
use App\Enum\OrderStatus;
use App\Enum\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Uid\Uuid;

final class PayOrderControllerTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{Currency, string, non-empty-string}>
     */
    public static function currencies(): iterable
    {
        yield 'UAH via Monobank' => [Currency::UAH, 'monobank', 'mono_'];
        yield 'USD via Stripe' => [Currency::USD, 'stripe', 'ch_'];
        yield 'EUR via Stripe' => [Currency::EUR, 'stripe', 'ch_'];
    }

    /**
     * @param non-empty-string $transactionPrefix
     */
    #[DataProvider('currencies')]
    public function testPaysOrder(Currency $currency, string $gateway, string $transactionPrefix): void
    {
        $orderId = $this->createOrder($currency);

        $this->pay($orderId, $this->newKey());

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Idempotent-Replayed', 'false');
        self::assertResponseHeaderSame('X-RateLimit-Limit', '10');

        $data = $this->responseData();
        self::assertSame('PAID', $data['status']);
        self::assertSame($gateway, $data['paymentGateway']);
        self::assertStringStartsWith($transactionPrefix, (string) $data['paymentTransactionId']);
        self::assertIsString($data['paidAt']);

        $order = $this->findOrder($orderId);
        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentGateway::from($gateway), $order->getPaymentGateway());
        self::assertSame(2, $order->getVersion());
    }

    public function testRepeatedRequestWithSameKeyReplaysResultWithoutChargingAgain(): void
    {
        $orderId = $this->createOrder();
        $key = $this->newKey();

        $this->pay($orderId, $key);
        self::assertResponseStatusCodeSame(200);
        $firstBody = $this->client->getResponse()->getContent();

        $this->pay($orderId, $key);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Idempotent-Replayed', 'true');
        // Same transaction id: the stored response, not a second charge.
        self::assertSame($firstBody, $this->client->getResponse()->getContent());
        // The order was updated once.
        self::assertSame(2, $this->findOrder($orderId)->getVersion());
    }

    public function testRejectsPayingAlreadyPaidOrder(): void
    {
        $orderId = $this->createOrder();
        $this->pay($orderId, $this->newKey());
        self::assertResponseStatusCodeSame(200);

        $secondKey = $this->newKey();
        $this->pay($orderId, $secondKey);

        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('its status is PAID', (string) $this->responseData()['detail']);
        // A failed request does not occupy its key.
        self::assertFalse($this->redis()->get('idempotency:'.$secondKey));
    }

    public function testRequestWhileSameKeyIsInProgressGets409(): void
    {
        $orderId = $this->createOrder();
        $key = $this->newKey();
        // Simulate a first request that is still running.
        $this->redis()->setex('idempotency:'.$key, 30, json_encode([
            'state' => 'IN_PROGRESS',
            'scope' => self::OWNER_ID.':'.$orderId->toRfc4122(),
            'token' => 'other-request',
        ], \JSON_THROW_ON_ERROR));

        $this->pay($orderId, $key);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(OrderStatus::NEW, $this->findOrder($orderId)->getStatus());
    }

    public function testRejectsKeyReusedForAnotherOrder(): void
    {
        $firstOrder = $this->createOrder();
        $secondOrder = $this->createOrder();
        $key = $this->newKey();

        $this->pay($firstOrder, $key);
        self::assertResponseStatusCodeSame(200);

        $this->pay($secondOrder, $key);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(OrderStatus::NEW, $this->findOrder($secondOrder)->getStatus());
    }

    public function testForeignUserCannotPay(): void
    {
        $orderId = $this->createOrder();

        $this->pay($orderId, $this->newKey(), userId: self::OTHER_USER_ID);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(OrderStatus::NEW, $this->findOrder($orderId)->getStatus());
    }

    public function testRequiresUserHeader(): void
    {
        $orderId = $this->createOrder();

        $this->request('POST', $this->payUrl($orderId), ['Idempotency-Key' => $this->newKey()]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidIdempotencyKeys(): iterable
    {
        yield 'missing' => [[]];
        yield 'not a UUID' => [['Idempotency-Key' => 'abc']];
    }

    /**
     * @param array<string, string> $keyHeader
     */
    #[DataProvider('invalidIdempotencyKeys')]
    public function testRequiresValidIdempotencyKey(array $keyHeader): void
    {
        $orderId = $this->createOrder();

        $this->request('POST', $this->payUrl($orderId), ['X-User-Id' => self::OWNER_ID] + $keyHeader);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(OrderStatus::NEW, $this->findOrder($orderId)->getStatus());
    }

    public function testUnknownOrderGets404(): void
    {
        $this->pay(Uuid::v7(), $this->newKey());

        self::assertResponseStatusCodeSame(404);
    }

    private function pay(Uuid $orderId, string $key, string $userId = self::OWNER_ID): void
    {
        $this->request('POST', $this->payUrl($orderId), ['X-User-Id' => $userId, 'Idempotency-Key' => $key]);
    }

    private function payUrl(Uuid $orderId): string
    {
        return '/api/v1/orders/'.$orderId->toRfc4122().'/pay';
    }

    private function newKey(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    private function findOrder(Uuid $orderId): Order
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $order = $entityManager->find(Order::class, $orderId);
        self::assertNotNull($order);

        return $order;
    }

    private function redis(): \Redis
    {
        $redis = RedisAdapter::createConnection((string) $_SERVER['REDIS_URL']);
        self::assertInstanceOf(\Redis::class, $redis);

        return $redis;
    }
}
