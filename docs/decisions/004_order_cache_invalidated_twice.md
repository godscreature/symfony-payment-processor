# 004. Drop the order cache on update and again after commit

Date: 2026-11-24 · Status: Accepted

## Context

[src/Repository/CachedOrderRepository.php](../../src/Repository/CachedOrderRepository.php) caches orders in
Redis. The cache must be dropped when an order changes. Doctrine's `postUpdate` event fires inside the
transaction, before commit. A parallel GET between that moment and the commit misses the cache, reads the
old committed row and caches it again, so the stale status would live until the TTL (5 minutes).

## Decision

- [src/EventListener/OrderCacheInvalidator.php](../../src/EventListener/OrderCacheInvalidator.php) drops the
  cache on `postUpdate` and `postRemove`: automatic, covers every change path.
- [src/Service/OrderService.php](../../src/Service/OrderService.php) `pay()` drops it once more after the
  transaction commits, which closes the window above.

## Consequences

- No stale status after payment.
- A new code path that changes orders gets the automatic invalidation, but should also invalidate after
  commit if stale reads matter there. A generic "after commit" hook would remove this duplication.
