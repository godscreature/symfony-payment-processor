# Technical Specification: Async Order & Payment Processing Service

## 1. Goal & Domain
Build a clean, production-grade REST API microservice for processing customer orders and payments. The project must demonstrate high reliability, data consistency under concurrent requests, asynchronous background processing, and strict code quality standards using pragmatic, idiomatic Symfony 7.4 LTS architecture.

## 2. Tech Stack & Infrastructure
- **Language & Framework:** PHP 8.3+, Symfony 7.4 LTS (`symfony/skeleton`, strict types `declare(strict_types=1);` everywhere).
- **Database:** PostgreSQL 16 + Doctrine ORM (migrations, proper indexes).
- **Message Broker:** RabbitMQ + Symfony Messenger (AMQP transport).
- **Cache & Locking:** Redis (caching, rate limiting, idempotency keys).
- **API Documentation:** OpenAPI 3.0 + Swagger UI via `nelmio/api-doc-bundle` (available at `/api/doc`, auto-generated from PHP attributes and Request/Response DTOs).
- **Containerization:** Docker + Docker Compose (`php-fpm`, `nginx`, `postgres`, `rabbitmq`, `redis`, `worker` consumer container).
- **Code Quality & CI:**
  - PHPStan (Level 8)
  - PHP-CS-Fixer (PSR-12 / Symfony ruleset)
  - PHPUnit (Unit + Integration tests)
  - `Makefile` for one-command setup and checks (`make up`, `make migrate`, `make test`, `make stan`, `make cs-fix`)
  - GitHub Actions workflow (`.github/workflows/ci.yml`) running PHPStan, CS-Fixer, and PHPUnit on every push/PR.

## 3. Core Business Flows & Functional Requirements

### A. Create Order (`POST /api/v1/orders`)
- **Input:** `customerId` (UUID), `items` (array of `productId`, `quantity`, `unitPrice`), `currency` (`UAH`, `USD`, `EUR`).
- **Validation:** Use Symfony `#[MapRequestPayload]` with strict `readonly` Request DTOs.
- **Rate Limiting:** Protect endpoint using `symfony/rate-limiter` backed by Redis (e.g., max 20 requests per minute per `customerId`/IP).
- **Database & Concurrency:**
  - Save `Order` and `OrderItem` entities inside a single database transaction.
  - Add Optimistic Locking (`#[ORM\Version]`) to the `Order` entity to prevent concurrent modification conflicts.
  - Add proper PostgreSQL indexes on `customer_id`, `status`, and `created_at`.
- **Output:** `201 Created` with Order Response DTO (`id`, `status: NEW`, `totalAmount`, `createdAt`).

### B. Process Payment with Idempotency (`POST /api/v1/orders/{id}/pay`)
- **Header:** Required `Idempotency-Key` (UUID).
- **Idempotency Mechanism (Redis):**
  - Before processing, check Redis for `idempotency:{key}`.
  - If key exists and completed -> immediately return the cached JSON response (`200 OK`) without charging again.
  - If key is currently in progress -> return `409 Conflict`.
- **Pessimistic Locking (PostgreSQL):**
  - Lock the `Order` row using `LockMode::PESSIMISTIC_WRITE` (`SELECT ... FOR UPDATE`) inside a transaction to prevent double spending from parallel requests.
  - Verify order status is `NEW` (throw domain exception if already `PAID` or `CANCELLED`).
- **Payment Gateway Selection (Strategy + Adapter Pattern):**
  - Implement `PaymentGatewayInterface` with `supports(Currency $currency): bool` and `charge(Order $order): PaymentResultDto`.
  - Create two simulated gateways: `StripeGateway` (for USD/EUR) and `MonobankGateway` (for UAH) wrapped as Adapters.
  - Use Symfony `#[TaggedIterator]` to inject strategies into `PaymentProcessor`.
- **Async Post-Processing (RabbitMQ):**
  - Upon successful charge, update Order status to `PAID`, commit transaction, save result in Redis idempotency cache, and dispatch `OrderPaidMessage` to RabbitMQ (`orders_high_priority` queue).

### C. Background Workers & Resilience (Symfony Messenger + RabbitMQ)
- Configure two separate queues (Bulkhead pattern):
  1. `orders_high_priority`: sends simulated payment receipt notifications.
  2. `reports_low_priority`: generates simulated PDF/CSV financial summary for an order (`POST /api/v1/orders/{id}/report`).
- Configure **Retry Strategy** with Exponential Backoff (`max_retries: 3`, `delay: 1000`, `multiplier: 2`) and a `failed` transport (Dead Letter Queue) for failed messages.

### D. Get Order Details with Caching (`GET /api/v1/orders/{id}`)
- Implement **Decorator Pattern** over `OrderRepositoryInterface` (`CachedOrderRepository`) using Redis cache (`#[AsDecorator]`).
- Invalidate cache automatically when order status changes.
- Implement a custom **Symfony Security Voter** (`OrderVoter`) checking ownership (`ORDER_VIEW`, `ORDER_PAY`) via simulated API Token / user header (`X-User-Id`).

## 4. Architecture & Code Structure
Use the standard, clean Symfony 7 layered directory structure (avoiding over-engineering while strictly following SOLID principles and Design Patterns):

```text
src/
├── Controller/            # Thin HTTP controllers using #[MapRequestPayload]
├── Dto/                   # Strict readonly Request & Response DTOs
├── Entity/                # Doctrine entities (Order, OrderItem) with #[ORM\Version]
├── Enum/                  # PHP 8.1+ backed enums (OrderStatus, Currency)
├── EventListener/         # Global JSON Exception Listener
├── Exception/             # Domain & business logic exceptions
├── Message/               # Async message DTOs for Symfony Messenger
├── MessageHandler/        # RabbitMQ queue consumers (high & low priority)
├── Payment/
│   ├── Gateway/           # StripeGateway, MonobankGateway (Adapters)
│   ├── PaymentGatewayInterface.php
│   └── PaymentProcessor.php # Strategy context using #[TaggedIterator]
├── Repository/
│   ├── OrderRepositoryInterface.php
│   ├── OrderRepository.php
│   └── CachedOrderRepository.php # Decorator using #[AsDecorator] + Redis
├── Security/              # OrderVoter for granular access checks
└── Service/
    ├── IdempotencyService.php # Redis-backed idempotency handler
    └── OrderService.php       # Transactional order & payment orchestration
```

## 5. Documentation (`README.md`)
Write a clear, comprehensive `README.md` in English containing:
1. Project overview and pragmatic architectural decisions (explaining *why* standard Symfony layered architecture was chosen over DDD for this scope, and how Pessimistic/Optimistic locking, Idempotency, and RabbitMQ retry strategies ensure reliability).
2. Architecture & payment flow diagram (using Mermaid).
3. Step-by-step setup instructions via `Makefile` (`make up`, `make migrate`, `make test`, `make stan`).
4. Ready-to-use `curl` examples for all endpoints (including testing idempotency with duplicate `Idempotency-Key` headers).
