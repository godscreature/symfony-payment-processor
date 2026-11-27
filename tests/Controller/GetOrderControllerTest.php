<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\CachedOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class GetOrderControllerTest extends ApiTestCase
{
    public function testOwnerGetsOrder(): void
    {
        $orderId = $this->createOrder();

        $this->get($orderId, self::OWNER_ID);

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseData();
        self::assertSame($orderId->toRfc4122(), $data['id']);
        self::assertSame('NEW', $data['status']);
        self::assertSame(self::ORDER_TOTAL, $data['totalAmount']);
        self::assertCount(1, $data['items']);
        self::assertNull($data['paymentTransactionId']);
    }

    public function testOtherCustomerGets403(): void
    {
        $this->get($this->createOrder(), self::OTHER_USER_ID);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRequiresUserHeader(): void
    {
        $this->get($this->createOrder(), null);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsInvalidUserHeader(): void
    {
        $this->get($this->createOrder(), 'not-a-uuid');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('X-User-Id must be a valid UUID.', $this->responseData()['detail']);
    }

    public function testUnknownOrderGets404(): void
    {
        $this->get(Uuid::v7(), self::OWNER_ID);

        self::assertResponseStatusCodeSame(404);
    }

    public function testServesOrderFromCacheUntilPaymentChangesIt(): void
    {
        $orderId = $this->createOrder();

        $this->get($orderId, self::OWNER_ID);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->ordersCache()->hasItem(CachedOrderRepository::cacheKey($orderId)));

        // Change the row behind Doctrine's back: nothing invalidates the cache.
        $this->changeTotalInDatabase($orderId, 1);

        $this->get($orderId, self::OWNER_ID);
        self::assertSame(
            self::ORDER_TOTAL,
            $this->responseData()['totalAmount'],
            'The second read must come from the cache.',
        );

        // Paying changes the status through Doctrine, which drops the cached copy.
        $this->request('POST', '/api/v1/orders/'.$orderId->toRfc4122().'/pay', [
            'X-User-Id' => self::OWNER_ID,
            'Idempotency-Key' => Uuid::v4()->toRfc4122(),
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse($this->ordersCache()->hasItem(CachedOrderRepository::cacheKey($orderId)));

        $this->get($orderId, self::OWNER_ID);
        $data = $this->responseData();
        self::assertSame('PAID', $data['status']);
        self::assertSame(1, $data['totalAmount'], 'After invalidation the order is read from the database again.');
    }

    private function get(Uuid $orderId, ?string $userId): void
    {
        $headers = $userId === null ? [] : ['X-User-Id' => $userId];
        $this->request('GET', '/api/v1/orders/'.$orderId->toRfc4122(), $headers);
    }

    private function ordersCache(): \Psr\Cache\CacheItemPoolInterface
    {
        return self::getContainer()->get('orders.cache');
    }

    private function changeTotalInDatabase(Uuid $orderId, int $total): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            'UPDATE orders SET total_amount = :total WHERE id = :id',
            ['total' => $total, 'id' => $orderId->toRfc4122()],
        );
    }
}
