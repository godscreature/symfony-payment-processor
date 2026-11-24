<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Order\CreateOrderRequest;
use App\Entity\Order;
use App\Enum\Currency;
use App\Repository\OrderRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepositoryInterface $orders,
    ) {
    }

    /**
     * Creates the order with all its items in one transaction: either everything is saved or nothing.
     */
    public function create(CreateOrderRequest $request): Order
    {
        return $this->entityManager->wrapInTransaction(function () use ($request): Order {
            $order = new Order(Uuid::fromString($request->customerId), Currency::from($request->currency));

            foreach ($request->items as $item) {
                // addItem() also adds the line total to the order total.
                $order->addItem(Uuid::fromString($item->productId), $item->quantity, $item->unitPrice);
            }

            $this->orders->add($order);

            return $order;
        });
    }
}
