<?php

declare(strict_types=1);

namespace App\Dto\Error;

use OpenApi\Attributes as OA;

/**
 * Error body in the RFC 9457 "problem details" shape, used for every API error.
 */
final readonly class ErrorResponseDto
{
    /**
     * @param list<ViolationResponseDto> $violations
     */
    public function __construct(
        #[OA\Property(example: 422)]
        public int $status,
        #[OA\Property(example: 'Validation Failed')]
        public string $title,
        #[OA\Property(example: 'The request contains invalid data.')]
        public string $detail,
        public array $violations = [],
    ) {
    }
}
