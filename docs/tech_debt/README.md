# Tech debt

Known weak spots, worst first. Remove a row when it is fixed; add a file in this folder only if an item
needs more than a few lines.

| Item | Impact | Next step |
|---|---|---|
| The gateway charge happens inside the DB transaction but is not part of it: if the commit fails after a successful charge, the money is taken and the order stays NEW | High: lost payment state | Outbox or a reconciliation job that compares gateway transactions with PAID orders |
| `POST /api/v1/orders` needs no authentication and takes `customerId` from the body, so anyone can create orders for another customer | High: security | Require `X-User-Id` and check it equals `customerId` |
| `CachedOrderRepository` caches Doctrine entities; the cached copy is detached, so changing it silently saves nothing | Medium: easy misuse | Cache a read model (DTO) instead of the entity |
| Post-commit cache invalidation exists only in `OrderService::pay()` ([docs/decisions/004_order_cache_invalidated_twice.md](../decisions/004_order_cache_invalidated_twice.md)) | Medium: stale reads on future write paths | A generic after-commit hook for invalidation and messages |
| `GET /api/v1/orders` filters by `customer_id` and sorts by `created_at`, but there are only single-column indexes (as TASK.md asks), so Postgres sorts each customer's rows | Medium: at scale | Composite index `(customer_id, created_at)`; it would also make `idx_orders_customer_id` redundant |
| `TRUSTED_PROXIES` is empty by default; behind a load balancer every client would share one rate limit | Medium: on deploy | Set it in the production environment |
| Host ports 8080, 5672, 15672 clash with the local `spd` project, so sps cannot start while spd runs | Low: local dev only | Move sps host ports through `HTTP_PORT`, `RABBITMQ_PORT`, `RABBITMQ_UI_PORT` |
| swagger-php `OpenApi\Attributes` are deprecated ([docs/decisions/006_keep_classic_swagger_php_attributes.md](../decisions/006_keep_classic_swagger_php_attributes.md)) | Low: until swagger-php 8 | Migrate with Rector once nelmio supports `OpenApi\Spec` |
| DoctrineBundle triggers a `Type::hasType` deprecation in the dev log | Low: vendor code | Update DoctrineBundle when a fix is released |
