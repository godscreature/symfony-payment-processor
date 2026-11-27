# 002. An Idempotency-Key belongs to one user and one order

Date: 2026-11-24 · Status: Accepted

## Context

`TASK.md` uses a plain Redis key `idempotency:{key}`. A replayed response contains the full order.
If any request with a known key got the stored response, a key reused by mistake (or guessed) for another
order or by another user would return someone else's order data, or a "paid" answer for the wrong order.

## Decision

The stored record carries a scope `userId:orderId`
([src/Controller/PayOrderController.php](../../src/Controller/PayOrderController.php)).
[src/Service/IdempotencyService.php](../../src/Service/IdempotencyService.php) replays the response only when
the scope matches; otherwise it throws `IdempotencyKeyReusedException` (422).

## Consequences

- No data leaks between users through idempotency keys.
- Clients must use a new key per payment attempt, which is the usual contract (Stripe works the same way).
