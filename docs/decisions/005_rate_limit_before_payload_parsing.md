# 005. The rate limit counts invalid requests too

Date: 2026-11-23 · Status: Accepted

## Context

`#[MapRequestPayload]` parses and validates the body before the controller runs. A limit consumed inside the
controller would count only valid requests, so a client could flood the endpoint with invalid bodies and
never hit the limit.

## Decision

Controllers declare `#[RateLimit('<limiter>')]` ([src/Attribute/RateLimit.php](../../src/Attribute/RateLimit.php)).
[src/EventListener/RateLimitListener.php](../../src/EventListener/RateLimitListener.php) consumes the limit on
`kernel.controller`, before arguments are resolved, and adds `X-RateLimit-Limit` / `X-RateLimit-Remaining`
to every response of a limited route. The limiter key is the client IP; behind a load balancer set
`TRUSTED_PROXIES`.

## Consequences

- Invalid, unauthenticated and successful requests all count.
- New endpoints get a limit with one attribute and one entry in `config/packages/rate_limiter.yaml`.
