<?php

declare(strict_types=1);

namespace App\Attribute;

/**
 * Applies a rate limiter from config/packages/rate_limiter.yaml to a controller.
 * The limit is checked before the request body is parsed, so invalid requests count too.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class RateLimit
{
    public function __construct(
        public string $limiter,
    ) {
    }
}
