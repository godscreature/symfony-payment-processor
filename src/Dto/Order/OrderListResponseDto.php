<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Entity\Order;
use OpenApi\Attributes as OA;

final readonly class OrderListResponseDto
{
    /**
     * @param list<OrderSummaryResponseDto> $items
     */
    public function __construct(
        public array $items,
        #[OA\Property(description: 'Orders matching the filter, across all pages.', example: 42)]
        public int $total,
        #[OA\Property(example: 1)]
        public int $page,
        #[OA\Property(example: 20)]
        public int $limit,
    ) {
    }

    /**
     * @param list<Order> $orders
     */
    public static function fromEntities(array $orders, int $total, ListOrdersQueryDto $query): self
    {
        return new self(
            array_map(OrderSummaryResponseDto::fromEntity(...), $orders),
            $total,
            $query->page,
            $query->limit,
        );
    }
}
