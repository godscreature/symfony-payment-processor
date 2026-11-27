<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\Currency;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class ListOrdersControllerTest extends ApiTestCase
{
    public function testListsOnlyOwnOrdersNewestFirst(): void
    {
        $first = $this->createOrder(Currency::UAH);
        $second = $this->createOrder(Currency::USD);
        $third = $this->createOrder(Currency::EUR);
        $this->createOrder(Currency::UAH, self::OTHER_USER_ID);

        $this->list();

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseData();
        self::assertSame(3, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(20, $data['limit']);
        self::assertSame([$third->toRfc4122(), $second->toRfc4122(), $first->toRfc4122()], $this->ids($data));

        $item = $this->items($data)[0];
        self::assertSame('EUR', $item['currency']);
        self::assertSame('NEW', $item['status']);
        self::assertSame(self::ORDER_TOTAL, $item['totalAmount']);
        self::assertArrayNotHasKey('items', $item, 'Summaries do not carry line items.');
    }

    public function testFiltersByStatus(): void
    {
        $paid = $this->createOrder();
        $this->createOrder();
        $this->markPaid($paid);

        $this->list('?status=PAID');

        $data = $this->responseData();
        self::assertSame(1, $data['total']);
        self::assertSame([$paid->toRfc4122()], $this->ids($data));
        self::assertSame('PAID', $this->items($data)[0]['status']);
    }

    public function testPaginates(): void
    {
        $oldest = $this->createOrder();
        $this->createOrder();
        $this->createOrder();

        $this->list('?page=2&limit=2');

        $data = $this->responseData();
        self::assertSame(3, $data['total']);
        self::assertSame(2, $data['page']);
        self::assertSame(2, $data['limit']);
        self::assertSame([$oldest->toRfc4122()], $this->ids($data));
    }

    public function testCustomerWithoutOrdersGetsEmptyPage(): void
    {
        $this->createOrder();

        $this->list('', self::OTHER_USER_ID);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 20], $this->responseData());
    }

    public function testRequiresUserHeader(): void
    {
        $this->request('GET', '/api/v1/orders');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'unknown status' => ['?status=SHIPPED', 'status'];
        yield 'page zero' => ['?page=0', 'page'];
        yield 'limit above maximum' => ['?limit=101', 'limit'];
        yield 'limit not a number' => ['?limit=abc', 'limit'];
    }

    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidQuery(string $query, string $field): void
    {
        $this->list($query);

        self::assertResponseStatusCodeSame(422);
        $violations = $this->responseData()['violations'];
        self::assertIsArray($violations);
        self::assertContains($field, array_column($violations, 'propertyPath'));
    }

    private function list(string $query = '', string $userId = self::OWNER_ID): void
    {
        $this->request('GET', '/api/v1/orders'.$query, ['X-User-Id' => $userId]);
    }

    private function markPaid(Uuid $orderId): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement(
            "UPDATE orders SET status = 'PAID' WHERE id = :id",
            ['id' => $orderId->toRfc4122()],
        );
        // The order created above is still in Doctrine's memory with status NEW;
        // forget it so the next request reads the row we just changed.
        $entityManager->clear();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $data): array
    {
        self::assertIsArray($data['items']);

        /** @var list<array<string, mixed>> $items */
        $items = $data['items'];

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<mixed>
     */
    private function ids(array $data): array
    {
        return array_column($this->items($data), 'id');
    }
}
