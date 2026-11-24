<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Entity\Order;
use App\Entity\OrderItem;
use OpenApi\Attributes as OA;

final readonly class OrderResponse
{
    /**
     * @param list<OrderItemResponse> $items
     */
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $customerId,
        #[OA\Property(example: 'NEW', enum: ['NEW', 'PAID', 'CANCELLED'])]
        public string $status,
        #[OA\Property(example: 'UAH', enum: ['UAH', 'USD', 'EUR'])]
        public string $currency,
        #[OA\Property(description: 'Order total in minor units (kopecks / cents).', example: 3998)]
        public int $totalAmount,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        public array $items,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId()->toRfc4122(),
            customerId: $order->getCustomerId()->toRfc4122(),
            status: $order->getStatus()->value,
            currency: $order->getCurrency()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            items: array_values(array_map(
                static fn (OrderItem $item): OrderItemResponse => OrderItemResponse::fromEntity($item),
                $order->getItems()->toArray(),
            )),
        );
    }
}
