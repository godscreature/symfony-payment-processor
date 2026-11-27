<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Entity\Order;
use OpenApi\Attributes as OA;

/**
 * One order in a list: no line items, so a page is loaded with a single query.
 * Full details: GET /api/v1/orders/{id}.
 */
final readonly class OrderSummaryResponseDto
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(example: 'NEW', enum: ['NEW', 'PAID', 'CANCELLED'])]
        public string $status,
        #[OA\Property(example: 'UAH', enum: ['UAH', 'USD', 'EUR'])]
        public string $currency,
        #[OA\Property(description: 'Order total in minor units (kopecks / cents).', example: 3998)]
        public int $totalAmount,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $paidAt,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
            currency: $order->getCurrency()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            paidAt: $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
