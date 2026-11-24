<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Enum\Currency;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OrderTest extends TestCase
{
    public function testNewOrderStartsEmptyWithStatusNew(): void
    {
        $order = new Order(Uuid::v7(), Currency::USD);

        self::assertSame(OrderStatus::NEW, $order->getStatus());
        self::assertSame(0, $order->getTotalAmount());
        self::assertSame(1, $order->getVersion());
        self::assertCount(0, $order->getItems());
    }

    public function testAddItemAccumulatesTotalInMinorUnits(): void
    {
        $order = new Order(Uuid::v7(), Currency::UAH);

        $order->addItem(Uuid::v7(), 3, 1050);
        $order->addItem(Uuid::v7(), 1, 99);

        self::assertSame(3 * 1050 + 99, $order->getTotalAmount());
        self::assertCount(2, $order->getItems());
    }

    public function testRejectsNonPositiveQuantity(): void
    {
        $order = new Order(Uuid::v7(), Currency::EUR);

        $this->expectException(\InvalidArgumentException::class);
        $order->addItem(Uuid::v7(), 0, 100);
    }
}
