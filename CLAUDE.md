# SPS — Order & Payment Processing Service

Symfony 7.4 LTS REST API on PHP 8.5: orders, payments, idempotency, async processing.
The full specification is in [TASK.md](TASK.md).

## Knowledge map

This file holds only rules. Each topic below has its own index; open it when the task touches the topic.

| Topic | Index |
|---|---|
| Claude hooks: what each one does, how to add one | [.claude/hooks/README.md](.claude/hooks/README.md) |
| Architecture decisions: why the code is built this way | [docs/decisions/README.md](docs/decisions/README.md) |
| Known tech debt and weak spots | [docs/tech_debt/README.md](docs/tech_debt/README.md) |
| Plans: what is done and what is next | [docs/plans/README.md](docs/plans/README.md) |

Documentation rules:
- At most two levels: this map → a topic index → (optionally) one file per item.
- An index lists items one line each; details go in item files or in the code itself.
- Folder and file names in `docs/` are snake_case. Topic indexes are named `README.md`.
- Links show the path from the repo root as text: `[docs/plans/README.md](docs/plans/README.md)`.
  In nested files the target stays relative to that file: `[TASK.md](../../TASK.md)`.

## Environment

- Everything runs in Docker. Never use PHP or Composer on the host (wrong version, missing extensions).
  Run commands through `make` or `docker compose exec -T php <cmd>` (`-T` when there is no TTY).
- Services: `php`, `nginx` (http://localhost:8080), `postgres` 16 (`app`/`app`, databases `app` and `app_test`),
  `rabbitmq` (5672, UI 15672), `redis` (dev uses DB 0, tests use DB 1).
- Swagger UI: http://localhost:8080/api/doc. Spec: `/api/doc.json`.

## Commands

| Command | What it does |
|---|---|
| `make up` / `make init` | Start containers / first-time setup (build, up, install, migrate) |
| `make sh` | Interactive bash in the PHP container |
| `make console c="debug:router"` | One Symfony console command |
| `make migration` | Generate a migration from entity changes |
| `make migrate` / `make db-test` | Apply migrations to dev / test database |
| `make test` | PHPUnit (creates and migrates the test DB first) |
| `make stan` | PHPStan level 8 |
| `make cs-fix` | PHP-CS-Fixer: format the code |
| `make cs-check` | PHP-CS-Fixer check + PHP_CodeSniffer (line length, assignment in condition) |
| `make phpcs` | PHP_CodeSniffer only |
| `make check` | cs-check + stan + test |

After `make migration`: give the migration a `getDescription()`, delete the auto-generated comments,
then run `make migrate` and `make db-test`.

## Safety rules

Enforced by [.claude/settings.json](.claude/settings.json) and the hooks in [.claude/hooks/README.md](.claude/hooks/README.md):

- `git commit` runs only after the user approves it.
- Never `git push`, deploy, or reach servers (`ssh`, `scp`, `sftp`, `rsync`, `docker push/login`,
  `kubectl`, `helm`). These are blocked; ask the user to do them.
- Do not work around a block (other tools, scripts, aliases). If a blocked step is needed, stop and say so.

## Definition of done

1. `make cs-fix`, then `make check` passes (CS-Fixer, CodeSniffer, PHPStan, PHPUnit).
2. New behaviour is covered by tests.
3. Any endpoint change is reflected in its OpenAPI attributes (responses, headers, examples).
4. The topic index is updated when you add or change a hook, a decision, tech debt or a plan.

## Code conventions

- `declare(strict_types=1);` in every file (enforced by CS-Fixer).
- Typed class constants: `private const string URL = ...`. No tool checks this.
- Lines are at most 120 characters (enforced by PHP_CodeSniffer, [phpcs.xml.dist](phpcs.xml.dist)).
  Wrap long OpenAPI descriptions with string concatenation: `'first part '.'second part'`.
- No Yoda conditions (`$x === null`, never `null === $x`); enforced by CS-Fixer `yoda_style`.
- Never assign inside a condition; assign on its own line. PHP_CodeSniffer checks `if`, `elseif`
  and `do-while`; `while ($row = $stmt->fetch())` is the one accepted exception.
- `self::`, not `static::`, in final classes (enforced by CS-Fixer).
- Named arguments follow the constructor's parameter order (PhpStorm flags the rest),
  e.g. `new OA\Header(ref: ..., header: ...)`.
- Money is an `int` in minor units (kopecks / cents).
- DTOs are `final readonly`, end with `Dto` (`OrderResponseDto`), and are grouped by feature:
  `src/Dto/Order/`, `src/Dto/Error/`. Swagger shows them without the suffix: add each new DTO to
  `models.names` in `config/packages/nelmio_api_doc.yaml`, nested DTOs before the ones containing them.
- Domain exceptions live in `src/Exception/` and declare their status with `#[WithHttpStatus]`.
  `JsonExceptionListener` renders every error as `application/problem+json`;
  use `App\Http\ProblemResponse` for errors returned outside exceptions.
- Use `#[AutowireIterator]`, not the deprecated `#[TaggedIterator]`.

## Architecture notes

The reasons behind these rules are in [docs/decisions/README.md](docs/decisions/README.md).

- `OrderRepositoryInterface` resolves to `CachedOrderRepository` (decorator, Redis).
  `findById()` may return a cached copy detached from Doctrine: read only.
  To change an order, load it with `findForUpdate()` inside a transaction
  (`SELECT ... FOR UPDATE` that also refreshes an already loaded entity).
- Order cache is invalidated by `OrderCacheInvalidator` (Doctrine postUpdate) and again after commit
  in `OrderService::pay()`, to close the window where a parallel read re-caches the old row.
- `IdempotencyService` scopes each key to `userId:orderId` and stores the response only after commit.
- Rate limiting: `#[RateLimit('<name>')]` on a controller, limiters in `config/packages/rate_limiter.yaml`,
  keyed by client IP. Behind a load balancer set `TRUSTED_PROXIES`.
- Auth: `X-User-Id` header (`HeaderUserAuthenticator`), firewall `api` on `^/api/v1`, stateless and lazy.
  Protect an endpoint with `#[IsGranted('IS_AUTHENTICATED')]` plus `OrderVoter` (`ORDER_VIEW`, `ORDER_PAY`).
- `OrderPaidEvent` is dispatched after a committed payment: the hook for RabbitMQ messaging.
- swagger-php 7 deprecates `OpenApi\Attributes`, but nelmio/api-doc-bundle 5.x reads only those.
  Keep using them; do not migrate to `OpenApi\Spec` until nelmio supports it.

## Tests

- Integration tests extend `tests/Controller/ApiTestCase`. `dama/doctrine-test-bundle` rolls back
  the database after each test; Redis is not rolled back, so reset limiters in `setUp()` and use
  random UUIDs for idempotency keys.
- Assert HTTP JSON with string literals (`'NEW'`, `'UAH'`): they pin the public contract.
  Use enums (`OrderStatus::NEW`) when asserting on entities and in unit tests.
- PHPUnit fails on deprecations and warnings.
