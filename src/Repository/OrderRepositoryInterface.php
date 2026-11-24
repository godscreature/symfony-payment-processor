<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Symfony\Component\Uid\Uuid;

interface OrderRepositoryInterface
{
    /**
     * Schedules the order for insert. The caller owns the transaction and the flush.
     */
    public function add(Order $order): void;

    public function findById(Uuid $id): ?Order;
}
