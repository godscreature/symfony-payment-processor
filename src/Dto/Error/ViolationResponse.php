<?php

declare(strict_types=1);

namespace App\Dto\Error;

use OpenApi\Attributes as OA;

final readonly class ViolationResponse
{
    public function __construct(
        #[OA\Property(example: 'items[0].quantity')]
        public string $propertyPath,
        #[OA\Property(example: 'This value should be positive.')]
        public string $message,
    ) {
    }
}
