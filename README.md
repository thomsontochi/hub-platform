# Hub Platform

Event-driven HR platform composed of two Laravel 12 services:

- **HubService** (`hub-service/`) – consumes HR events and drives the admin experience.
- **HR Service** (`hr-service/`) – publishes employee events and checklist updates.

Both services share a Dockerised infrastructure (Postgres, Redis, RabbitMQ, Soketi, Nginx, PHP 8.4) for local development.

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

## Messaging & Queue Workers

- HR Service publishes employee events to RabbitMQ via a queued job. See [`docs/messaging.md`](docs/messaging.md) for routing keys, binding instructions, and the queue worker command (`docker compose -f hub-service/docker-compose.yml exec hr-app php artisan queue:work --queue=events --tries=5`).

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

- Align HR Service `.env` with any additional integration settings once new services are introduced.
- Add automated integration tests (Pest) that exercise event publishing and Redis caching once business logic lands.
- Document the event schema and WebSocket channels as features are implemented.

Happy hacking! :rocket: from developia
