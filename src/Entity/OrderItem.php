<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $productId;

    #[ORM\Column]
    private int $quantity;

    /** Price per unit in minor units (kopecks / cents). */
    #[ORM\Column]
    private int $unitPrice;

    public function __construct(Order $order, Uuid $productId, int $quantity, int $unitPrice)
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }
        if ($unitPrice <= 0) {
            throw new \InvalidArgumentException('Unit price must be positive.');
        }

        $this->id = Uuid::v7();
        $this->order = $order;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public function getLineTotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProductId(): Uuid
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }
}
