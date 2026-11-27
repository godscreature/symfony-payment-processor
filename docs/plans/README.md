# Plans

Work against [TASK.md](../../TASK.md), in order. Update the status when a step starts or ends;
remove finished steps once nobody needs the history (git keeps it).

Last updated: 2026-11-27

| Step | Scope | Status |
|---|---|---|
| 1 | Docker, Symfony 7.4 skeleton, packages, Makefile, quality tools | Done (2026-11-23) |
| 2 | `POST /api/v1/orders`: entities, migration, DTOs, transactional service, Redis rate limit, JSON errors | Done (2026-11-24) |
| 3 | `POST /api/v1/orders/{id}/pay` and `GET /api/v1/orders/{id}`: X-User-Id auth, OrderVoter, Redis idempotency, pessimistic lock, payment gateways, cache decorator | Done (2026-11-27) |
| — | Claude setup: CLAUDE.md, settings, safety hook, knowledge map | Done (2026-11-27) |
| — | `GET /api/v1/orders` (not in TASK.md): the current user's orders, status filter, pages of up to 100, summaries without line items | Done (2026-11-27) |
| 4 | Messenger + RabbitMQ: `OrderPaidMessage` to `orders_high_priority` (from `OrderPaidEvent`), `POST /api/v1/orders/{id}/report` to `reports_low_priority`, retry 3 × 1000 ms × 2, `failed` transport, `worker` container | Planned |
| 5 | `README.md`: overview, decisions, Mermaid payment flow, setup via `make`, curl examples incl. idempotency | Planned |
| 6 | GitHub Actions: cs-check, stan, test on every push and PR | Planned |

Open questions:
- Fix the high-impact items in [docs/tech_debt/README.md](../tech_debt/README.md) before or after Step 4?
