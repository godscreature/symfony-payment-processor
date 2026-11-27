<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function add(Order $order): void
    {
        $this->getEntityManager()->persist($order);
    }

    public function findById(Uuid $id): ?Order
    {
        return $this->find($id);
    }

    public function findForUpdate(Uuid $id): ?Order
    {
        $order = $this->createQueryBuilder('o')
            ->where('o.id = :id')
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            // If Doctrine already holds this order in memory (loaded before the lock),
            // overwrite it with the locked row: otherwise we could act on a stale status.
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $order instanceof Order ? $order : null;
    }

    public function findByCustomer(Uuid $customerId, ?OrderStatus $status, int $limit, int $offset): array
    {
        /** @var list<Order> $orders */
        $orders = $this->customerOrders($customerId, $status)
            // created_at has second precision; the time-ordered UUID v7 id breaks ties.
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $orders;
    }

    public function countByCustomer(Uuid $customerId, ?OrderStatus $status): int
    {
        return (int) $this->customerOrders($customerId, $status)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function customerOrders(Uuid $customerId, ?OrderStatus $status): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.customerId = :customerId')
            ->setParameter('customerId', $customerId, UuidType::NAME);

        if ($status !== null) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }
}
