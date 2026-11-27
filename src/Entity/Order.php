<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\OrderStatus;
use App\Enum\PaymentGateway;
use App\Exception\OrderNotPayableException;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_orders_status', columns: ['status'])]
#[ORM\Index(name: 'idx_orders_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $customerId;

    #[ORM\Column(length: 16, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::NEW;

    #[ORM\Column(length: 3, enumType: Currency::class)]
    private Currency $currency;

    /** Total in minor units (kopecks / cents). */
    #[ORM\Column(type: Types::BIGINT)]
    private int $totalAmount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    /** Optimistic lock: Doctrine bumps it on every update and rejects stale writes. */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(length: 32, nullable: true, enumType: PaymentGateway::class)]
    private ?PaymentGateway $paymentGateway = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $paymentTransactionId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(Uuid $customerId, Currency $currency)
    {
        $this->id = Uuid::v7();
        $this->customerId = $customerId;
        $this->currency = $currency;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function addItem(Uuid $productId, int $quantity, int $unitPrice): OrderItem
    {
        $item = new OrderItem($this, $productId, $quantity, $unitPrice);
        $this->items->add($item);
        $this->totalAmount += $item->getLineTotal();

        return $item;
    }

    /**
     * @throws OrderNotPayableException when the order is not NEW (already paid or cancelled)
     */
    public function markPaid(PaymentGateway $gateway, string $transactionId): void
    {
        if ($this->status !== OrderStatus::NEW) {
            throw OrderNotPayableException::forStatus($this->id, $this->status);
        }

        $this->status = OrderStatus::PAID;
        $this->paymentGateway = $gateway;
        $this->paymentTransactionId = $transactionId;
        $this->paidAt = new \DateTimeImmutable();
    }

    public function isOwnedBy(Uuid $customerId): bool
    {
        return $this->customerId->equals($customerId);
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCustomerId(): Uuid
    {
        return $this->customerId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getPaymentGateway(): ?PaymentGateway
    {
        return $this->paymentGateway;
    }

    public function getPaymentTransactionId(): ?string
    {
        return $this->paymentTransactionId;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }
}
