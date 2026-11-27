<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Component\Uid\Uuid;

interface OrderRepositoryInterface
{
    /**
     * Schedules the order for insert. The caller owns the transaction and the flush.
     */
    public function add(Order $order): void;

    /**
     * For reading only: the result may come from the cache, detached from Doctrine,
     * so changes to it are not saved. Load with findForUpdate() to change an order.
     */
    public function findById(Uuid $id): ?Order;

    /**
     * Loads the order and locks its row (SELECT ... FOR UPDATE) until the transaction ends.
     * Must run inside a transaction. Never cached.
     */
    public function findForUpdate(Uuid $id): ?Order;

    /**
     * One page of a customer's orders, newest first. Line items are not loaded.
     *
     * @return list<Order>
     */
    public function findByCustomer(Uuid $customerId, ?OrderStatus $status, int $limit, int $offset): array;

    public function countByCustomer(Uuid $customerId, ?OrderStatus $status): int;
}
