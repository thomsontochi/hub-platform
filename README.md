# Hub Platform

Event-driven HR platform composed of two Laravel 12 services:

- **HubService** (`hub-service/`) – consumes HR events, maintains cached projections, broadcasts checklist updates, and exposes server-driven UI APIs.
- **HR Service** (`hr-service/`) – manages employee aggregates and publishes country-aware domain events into RabbitMQ.

Supporting infrastructure (Dockerised): PostgreSQL, Redis, RabbitMQ (management UI enabled), Soketi (Pusher-compatible WebSockets), Nginx front proxies, and PHP 8.4 FPM pools.

### Technology Stack

- **Backend**: Laravel 12, PHP 8.4, Pest test suites, Laravel Pint for formatting, Larastan (PHPStan) for static analysis.
- **Messaging**: RabbitMQ topic exchange (`employee.events`) with per-country routing keys.
- **Caching**: Redis stores employee snapshots and checklist projections using cache-aside patterns with targeted invalidation.
- **Real-time**: Soketi broadcasts `checklist.updated` payloads to browser clients over WebSockets.

---

## Prerequisites

- Docker Desktop 4.29+ (or compatible engine)
- Composer 2.7+
- Node 20+ / pnpm (if you plan to run front-end assets locally)

---

## Quick start

```bash
# Clone the repository
git clone git@github.com:<org>/hub-platform.git
cd hub-platform

# Copy environment files (already committed as .env example templates)
cp hub-service/.env.example hub-service/.env
cp hr-service/.env.example hr-service/.env

# Build and boot the stack (first run will take a few minutes)
cd hub-service
docker compose up -d --build

# Generate app keys inside the containers
docker compose exec hub-app php artisan key:generate
docker compose exec hr-app php artisan key:generate

# Visit the services
open http://localhost:8082   # HubService
open http://localhost:8083   # HR Service
```

## Architecture

- Full domain notes live in [`docs/domain.md`](docs/domain.md).
- Phase progress and API payloads are tracked in [`docs/api.md`](docs/api.md) — Phase 5 introduces the server-driven UI endpoints powering navigation, schema, and employee listings.
- Test strategy updates (Phases 5 & 6) are outlined in [`docs/testing.md`](docs/testing.md), covering API resources, Artisan tooling, and manual WebSocket demos.
- The diagram below captures the core event flow and runtime dependencies.

```mermaid
flowchart LR
    subgraph HR[HR Service]
        HRAPI[REST API]
        HRDB[(PostgreSQL)]
    end

    subgraph MQ[RabbitMQ]
        EX[(employee.events exchange)]
        Q[(hub.employee.events queue)]
    end

    subgraph HUB[Hub Service]
        Consumer[Event Consumer]
        Checklist[Checklist Engine]
        Cache[(Redis Cache)]
        API[Server-driven UI APIs]
        Socket[Broadcaster (Soketi)]
    end

    HRAPI -- CRUD -> HRDB
    HRAPI -- Publish events --> EX
    EX -- routed events --> Q
    Q -- consume --> Consumer
    Consumer --> Checklist
    Checklist --> Cache
    Cache --> API
    Checklist -. invalidation .-> Cache
    Consumer --> Socket
    API --> Clients[Admin Clients]
    Socket --> Clients
```

Default Docker network hosts:

| Service      | Hostname   | Port |
|--------------|------------|------|
| Hub Nginx    | hub-nginx  | 80   |
| HR Nginx     | hr-nginx   | 80   |
| Postgres     | postgres   | 5432 |
| Redis        | redis      | 6379 |
| RabbitMQ     | rabbitmq   | 5672 / 15672 (mgmt) |
| Soketi       | soketi     | 6001 / 9601 |

---

## API & Event Schema

- REST endpoint catalogue (Phase 5 server-driven UI APIs) – [`docs/api.md`](docs/api.md) covers `/api/checklists`, `/api/steps`, `/api/employees`, and `/api/schema/{step}` response formats, validation envelopes, and pagination rules.
- Domain and rule rationale – [`docs/domain.md`](docs/domain.md) breaks down USA vs. Germany checklist requirements and projection logic.
- Event stream contracts – [`docs/messaging.md`](docs/messaging.md) captures `EmployeeCreated`, `EmployeeUpdated`, and `EmployeeDeleted` payload shapes plus routing key conventions (`employee.<country>.<action>`).

## Caching Strategy

- Checklist projections are cached per country (`checklist:<country>`), while employee snapshots sit in a namespaced cache repository keyed by ID for quick diffing.
- Event handlers trigger cache invalidation selectively: creations/updates overwrite snapshots and recompute affected projections; deletions evict employees and recompute completion tallies.
- Redis is orchestrated through Laravel cache repositories; make the store configurable via `CACHE_EVENTS_STORE`/`CACHE_CHECKLISTS_STORE` to support alternate backends in production.

Further architectural notes (including cache diagrams) live in [`docs/domain.md`](docs/domain.md#checklist-projection-workflow) and [`docs/messaging.md`](docs/messaging.md#hub-service-consumer).

## Real-Time Checklist Demo

1. Boot the stack (`docker compose up -d` from `hub-service/`).
2. Keep the hub consumer online: `docker compose -f hub-service/docker-compose.yml exec hub-app php artisan events:consume-employee`.
3. Publish seed data: `docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:seed --refresh`.
4. Visit `http://localhost:8082/demo/checklist`, choose a country, and watch `checklist.updated` events stream in; the event log panel mirrors the inbound payloads.

Troubleshooting steps for reconnecting Soketi and RabbitMQ consumers are captured in [`docs/testing.md`](docs/testing.md#troubleshooting-the-real-time-checklist-demo).

---

## Messaging & Queue Workers

- HR Service publishes employee events to RabbitMQ via a queued job. See [`docs/messaging.md`](docs/messaging.md) for routing keys, binding instructions, and the queue worker command (`docker compose -f hub-service/docker-compose.yml exec hr-app php artisan queue:work --queue=events --tries=5`).
- Hub Service declares `hub.employee.events` (topic queue) and streams messages into its Redis-backed projection via `php artisan events:consume-employee`. Queue topology and consumer flow are documented in [`docs/messaging.md`](docs/messaging.md#hub-service-consumer).
- Bounded retries + DLQ: transient handler failures are retried (default 3 attempts) via `employee.events.retry`; exhausted messages are parked in `hub.employee.events.dlq` for manual replay (`php artisan events:replay-dead-letter --limit=25`).

---

## Troubleshooting

### 1. Host port 8080 already in use

By default the hub proxy attempted to bind to `localhost:8080`. We now map HubService to **8082** and HR Service to **8083**. If you encounter `bind: address already in use` errors on other ports:

1. Check what is using the port: `lsof -nP -iTCP:<port>`
2. Either stop the conflicting process **or** adjust the mapping in `docker-compose.yml` (e.g. change `"8082:80"` to an available port).

### 2. Composer requires PHP >= 8.4

Laravel 12.53 ships Composer metadata requiring PHP 8.4. The shared PHP base image has been bumped to `php:8.4-fpm`. If you see `Your Composer dependencies require a PHP version ">= 8.4.0"` inside the containers:

```bash
docker compose down
docker compose build --no-cache hub-app hr-app
docker compose up -d
```

After rebuilding, confirm the runtime (from `hub-service/`): `docker compose exec hub-app php -v`.

### 3. Build stalls on `docker/dockerfile:1.7`

Occasionally Docker Hub times out when downloading the builder image referenced by `# syntax=docker/dockerfile:1.7`. If a build fails with `DeadlineExceeded` while resolving that tag:

```bash
docker pull docker/dockerfile:1.7
docker compose build --no-cache hub-app hr-app
```

### 4. Missing app key errors

Both Laravel apps will throw `Illuminate\Encryption\MissingAppKeyException` until keys are generated. Run the commands shown in the quick start section after each container rebuild.

---

## Next steps

- CI pipeline (GitHub Actions) runs Composer install, SQLite migrations, Pint, Pest, and static analysis for both services—see `.github/workflows/ci.yml`.
- Static analysis: run `composer analyse` inside each service (Larastan `level=5`) before pushing larger changes.
- Align HR Service `.env` with additional integration settings once new services are introduced.
- Add end-to-end tests simulating RabbitMQ → checklist projection flows when additional business rules land.
- Build a thin UI shell (Vue/React) consuming the server-driven UI APIs for a full demo experience.
- Extend Phase 6 demo with richer dashboards (charts, filters) once real frontend is introduced.

Happy hacking! :rocket: from developia
