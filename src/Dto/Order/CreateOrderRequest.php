<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Enum\Currency;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderRequest
{
    public const int MAX_ITEMS = 100;

    /**
     * @param list<OrderItemRequest> $items
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[OA\Property(format: 'uuid', example: '0190f7d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f')]
        public string $customerId,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [Currency::class, 'values'])]
        #[OA\Property(example: 'UAH', enum: ['UAH', 'USD', 'EUR'])]
        public string $currency,

        #[Assert\Count(min: 1, max: self::MAX_ITEMS)]
        #[Assert\Valid]
        public array $items,
    ) {
    }
}
