# 001. Store the idempotent response only after the DB commit

Date: 2026-11-24 · Status: Accepted

## Context

`POST /api/v1/orders/{id}/pay` charges the order and marks it PAID inside a DB transaction, and stores the
response in Redis under the Idempotency-Key so that a retry gets the same answer without a second charge.
`TASK.md` lists "save result in Redis" before "commit". If the response is stored first and the commit then
fails, every retry replays a success for an order that is not PAID in the database.

## Decision

[src/Service/IdempotencyService.php](../../src/Service/IdempotencyService.php) runs the operation, and
only when it returns (the transaction in `OrderService::pay()` is committed) does it overwrite the
`IN_PROGRESS` marker with `COMPLETED` and the response. If the operation throws, the marker is deleted,
so the client may retry with the same key.

## Consequences

- Redis never claims a payment the database does not have.
- If the process dies between commit and the Redis write, the key expires after 30 s; a retry then finds
  the order PAID and gets 409 instead of the replayed 200. Rare, and it never charges twice.
- The gateway charge itself is outside the DB transaction; see the outbox item in
  [docs/tech_debt/README.md](../tech_debt/README.md).
