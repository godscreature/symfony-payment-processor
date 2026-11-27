<?php

declare(strict_types=1);

namespace App\Dto\Order;

use App\Enum\OrderStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /api/v1/orders.
 */
final readonly class ListOrdersQueryDto
{
    public const int MAX_LIMIT = 100;

    public function __construct(
        #[Assert\Choice(callback: [OrderStatus::class, 'values'])]
        #[OA\Property(description: 'Only orders with this status.', enum: ['NEW', 'PAID', 'CANCELLED'])]
        public ?string $status = null,

        #[Assert\Positive]
        #[OA\Property(description: 'Page number, starting at 1.', example: 1)]
        public int $page = 1,

        #[Assert\Range(min: 1, max: self::MAX_LIMIT)]
        #[OA\Property(description: 'Orders per page, 1–100.', example: 20)]
        public int $limit = 20,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
