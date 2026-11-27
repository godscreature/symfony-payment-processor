# 003. `findForUpdate()` re-reads the row it locks

Date: 2026-11-24 · Status: Accepted

## Context

Payment locks the order row with `SELECT ... FOR UPDATE` and checks that the status is NEW.
Before that, the controller has already loaded the order to check access. When Doctrine already holds an
entity in memory, a plain `find(..., PESSIMISTIC_WRITE)` locks the row but keeps the in-memory data.
If a parallel request paid the order in between, we would see a stale NEW and charge twice.

## Decision

[src/Repository/OrderRepository.php](../../src/Repository/OrderRepository.php) `findForUpdate()` runs a
DQL query with `setLockMode(PESSIMISTIC_WRITE)` and the `Query::HINT_REFRESH` hint, so the entity is
overwritten with the locked row. The cached decorator never serves this method from Redis.

## Consequences

- The status check always sees the committed state at the moment of locking.
- Verified with parallel requests: 5 payments of one order with different keys gave one 200 and four 409.
