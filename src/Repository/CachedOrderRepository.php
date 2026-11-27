<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Decorator: every service asking for OrderRepositoryInterface gets this class,
 * which serves findById() from Redis and delegates everything else to the Doctrine repository.
 * OrderCacheInvalidator drops the entry when the order changes.
 */
#[AsDecorator(decorates: OrderRepositoryInterface::class)]
final readonly class CachedOrderRepository implements OrderRepositoryInterface
{
    private const int TTL = 300;

    public function __construct(
        #[AutowireDecorated]
        private OrderRepositoryInterface $inner,
        private TagAwareCacheInterface $ordersCache,
    ) {
    }

    public static function cacheTag(Uuid $id): string
    {
        return 'order-'.$id->toRfc4122();
    }

    public static function cacheKey(Uuid $id): string
    {
        return 'order.'.$id->toRfc4122();
    }

    public function add(Order $order): void
    {
        $this->inner->add($order);
    }

    public function findById(Uuid $id): ?Order
    {
        $load = function (ItemInterface $item, bool &$save) use ($id): ?Order {
            $order = $this->inner->findById($id);
            if ($order === null) {
                // Do not cache misses.
                $save = false;

                return null;
            }

            // Load the items now: the cached copy has no database connection to load them later.
            $order->getItems()->toArray();

            $item->expiresAfter(self::TTL);
            $item->tag(self::cacheTag($id));

            return $order;
        };

        $order = $this->ordersCache->get(self::cacheKey($id), $load);

        return $order instanceof Order ? $order : null;
    }

    public function findForUpdate(Uuid $id): ?Order
    {
        // A locked read must see the database, never the cache.
        return $this->inner->findForUpdate($id);
    }

    public function findByCustomer(Uuid $customerId, ?OrderStatus $status, int $limit, int $offset): array
    {
        // Lists are not cached: every new or paid order would have to drop all of a customer's pages.
        return $this->inner->findByCustomer($customerId, $status, $limit, $offset);
    }

    public function countByCustomer(Uuid $customerId, ?OrderStatus $status): int
    {
        return $this->inner->countByCustomer($customerId, $status);
    }
}
