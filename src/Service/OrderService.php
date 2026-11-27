<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Order\CreateOrderRequestDto;
use App\Entity\Order;
use App\Enum\Currency;
use App\Enum\OrderStatus;
use App\Event\OrderPaidEvent;
use App\EventListener\OrderCacheInvalidator;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
use App\Exception\PaymentFailedException;
use App\Payment\PaymentProcessor;
use App\Payment\PaymentResultDto;
use App\Repository\OrderRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepositoryInterface $orders,
        private PaymentProcessor $paymentProcessor,
        private OrderCacheInvalidator $orderCache,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Creates the order with all its items in one transaction: either everything is saved or nothing.
     */
    public function create(CreateOrderRequestDto $request): Order
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

    /**
     * Charges the order and marks it PAID.
     *
     * The row stays locked (SELECT ... FOR UPDATE) from the status check until commit,
     * so two parallel payments of one order run one after another: the second sees PAID and fails.
     *
     * @throws OrderNotFoundException
     * @throws OrderNotPayableException when the order is not NEW
     * @throws PaymentFailedException
     */
    public function pay(Uuid $orderId): Order
    {
        /** @var array{Order, PaymentResultDto} $paid */
        $paid = $this->entityManager->wrapInTransaction(function () use ($orderId): array {
            $order = $this->orders->findForUpdate($orderId) ?? throw OrderNotFoundException::withId($orderId);

            // Check before charging, so an already paid order is never charged twice.
            if ($order->getStatus() !== OrderStatus::NEW) {
                throw OrderNotPayableException::forStatus($orderId, $order->getStatus());
            }

            $result = $this->paymentProcessor->charge($order);
            $order->markPaid($result->gateway, $result->transactionId);

            return [$order, $result];
        });
        [$order, $result] = $paid;

        // Doctrine already dropped the cache on update, but before commit: a parallel GET could
        // have re-cached the old row in between. Dropping it again after commit closes that gap.
        $this->orderCache->invalidate($orderId);

        $this->eventDispatcher->dispatch(new OrderPaidEvent($orderId, $result->gateway, $result->transactionId));

        return $order;
    }
}
