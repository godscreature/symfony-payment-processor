<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A response stored under an Idempotency-Key, either just produced or replayed from Redis.
 */
final readonly class IdempotentResponse
{
    public function __construct(
        public int $status,
        /** JSON body. */
        public string $body,
        public bool $replayed = false,
    ) {
    }
}
