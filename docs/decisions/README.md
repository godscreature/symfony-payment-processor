# Architecture decisions

Short records of decisions that are not obvious from the code: context, decision, consequences.
Add a new file with the next number when a decision is made; never rewrite an accepted one, supersede it.

| # | Decision | Status |
|---|---|---|
| 001 | [docs/decisions/001_idempotency_saved_after_commit.md](001_idempotency_saved_after_commit.md): store the idempotent response only after the DB commit | Accepted |
| 002 | [docs/decisions/002_idempotency_key_scoped_to_user_and_order.md](002_idempotency_key_scoped_to_user_and_order.md): an Idempotency-Key belongs to one user and one order | Accepted |
| 003 | [docs/decisions/003_refresh_row_under_pessimistic_lock.md](003_refresh_row_under_pessimistic_lock.md): `findForUpdate()` re-reads the row it locks | Accepted |
| 004 | [docs/decisions/004_order_cache_invalidated_twice.md](004_order_cache_invalidated_twice.md): drop the order cache on update and again after commit | Accepted |
| 005 | [docs/decisions/005_rate_limit_before_payload_parsing.md](005_rate_limit_before_payload_parsing.md): the rate limit counts invalid requests too | Accepted |
| 006 | [docs/decisions/006_keep_classic_swagger_php_attributes.md](006_keep_classic_swagger_php_attributes.md): keep `OpenApi\Attributes` despite the deprecation | Accepted |

## Template

```markdown
# NNN. Title

Date: YYYY-MM-DD · Status: Accepted | Superseded by NNN

## Context
What problem, what constraint.

## Decision
What we do.

## Consequences
What gets better, what gets worse, what to watch.
```
