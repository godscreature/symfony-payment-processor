<?php

declare(strict_types=1);

namespace App\Dto\Order;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderItemRequestDto
{
    public const int MAX_QUANTITY = 10_000;
    public const int MAX_UNIT_PRICE = 100_000_000;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[OA\Property(format: 'uuid', example: '0190f7d2-7c4e-7a3b-9f1e-2d4c5b6a7e8f')]
        public string $productId,

        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_QUANTITY)]
        #[OA\Property(example: 2)]
        public int $quantity,

        /** Price per unit in minor units (kopecks / cents). */
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_UNIT_PRICE)]
        #[OA\Property(description: 'Price per unit in minor units (kopecks / cents).', example: 1999)]
        public int $unitPrice,
    ) {
    }
}
