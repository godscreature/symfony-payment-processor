<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Repository\CachedOrderRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Drops the cached copy of an order whenever Doctrine updates or deletes it,
 * so a status change is never hidden by the cache.
 */
#[AsEntityListener(event: Events::postUpdate, method: 'onChange', entity: Order::class)]
#[AsEntityListener(event: Events::postRemove, method: 'onChange', entity: Order::class)]
final readonly class OrderCacheInvalidator
{
    public function __construct(
        private TagAwareCacheInterface $ordersCache,
    ) {
    }

    public function onChange(Order $order): void
    {
        $this->invalidate($order->getId());
    }

    public function invalidate(Uuid $orderId): void
    {
        $this->ordersCache->invalidateTags([CachedOrderRepository::cacheTag($orderId)]);
    }
}
