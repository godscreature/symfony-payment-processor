<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Entity\OrderItem;
use OpenApi\Attributes as OA;

final readonly class OrderItemResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $productId,
        public int $quantity,
        #[OA\Property(description: 'Price per unit in minor units (kopecks / cents).')]
        public int $unitPrice,
        #[OA\Property(description: 'quantity × unitPrice, in minor units.')]
        public int $lineTotal,
    ) {
    }

    public static function fromEntity(OrderItem $item): self
    {
        return new self(
            id: $item->getId()->toRfc4122(),
            productId: $item->getProductId()->toRfc4122(),
            quantity: $item->getQuantity(),
            unitPrice: $item->getUnitPrice(),
            lineTotal: $item->getLineTotal(),
        );
    }
}
