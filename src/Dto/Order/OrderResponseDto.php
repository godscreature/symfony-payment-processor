<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Entity\Order;
use App\Entity\OrderItem;
use OpenApi\Attributes as OA;

final readonly class OrderResponseDto
{
    /**
     * @param list<OrderItemResponseDto> $items
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
        #[OA\Property(
            description: 'Payment provider; null until paid.',
            example: 'monobank',
            nullable: true,
            enum: ['monobank', 'stripe'],
        )]
        public ?string $paymentGateway,
        #[OA\Property(
            description: 'Provider transaction id; null until paid.',
            example: 'mono_4f1c2a9b7e3d5c8a1b2c3d4e',
            nullable: true,
        )]
        public ?string $paymentTransactionId,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $paidAt,
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
                static fn (OrderItem $item): OrderItemResponseDto => OrderItemResponseDto::fromEntity($item),
                $order->getItems()->toArray(),
            )),
            paymentGateway: $order->getPaymentGateway()?->value,
            paymentTransactionId: $order->getPaymentTransactionId(),
            paidAt: $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
