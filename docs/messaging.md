---
# RabbitMQ Messaging Guide

## Overview
The HR service publishes employee lifecycle events to RabbitMQ so downstream consumers (e.g., the Hub service) can react asynchronously. Every create, update, or delete dispatches a `PublishEmployeeEvent` job that emits a message to the `employee.events` topic exchange. Routing keys follow the pattern `employee.{country}.{action}` (e.g., `employee.usa.created`).

## Services & Configuration
- Exchange: `employee.events` (topic, durable)
- Queue (consumer-defined): none by default; downstream services bind queues to the exchange as needed
- Routing keys: `employee.{country}.{created|updated|deleted}`
- Publisher job: `App\Jobs\PublishEmployeeEvent`
- Connection settings: `config/rabbitmq.php`

## Running the Queue Worker
Publishing is queued. A worker must process the `events` queue to actually send messages to RabbitMQ. Run this from the repository root:

```bash
docker compose -f hub-service/docker-compose.yml exec hr-app php artisan queue:work --queue=events --tries=5
```

Leave this running while you interact with the API. Press `Ctrl+C` when finished.

### Hub Service Consumer

The Hub service declares its own queue (`hub.employee.events`) and consumes events with:

```bash
docker compose -f hub-service/docker-compose.yml exec hub-app php artisan events:consume-employee
```

This command runs `App\Messaging\RabbitMq\RabbitMqConsumer` which:

1. Declares the `hub.employee.events` queue (durable) and binds it to the `employee.events` exchange with routing keys defined in `config/rabbitmq.php` (default `employee.*.*`).
2. Streams messages to `App\Domain\Employees\Handlers\ProjectingEmployeeEventHandler`, which keeps Redis snapshots up to date via `CacheEmployeeCache`.
3. ACK/NACKs messages and logs failures for observability.

Snapshots are stored at `employees:snapshots:{id}` with per-country indexes `employees:snapshots:index:{country}` for fast checklist lookups.

### Hub Service Consumer

The Hub service declares its own queue (`hub.employee.events`) and consumes events with:

```bash
docker compose -f hub-service/docker-compose.yml exec hub-app php artisan events:consume-employee
```

This command runs `App\Messaging\RabbitMq\RabbitMqConsumer` which:

1. Declares the `hub.employee.events` queue (durable) and binds it to the `employee.events` exchange with routing keys defined in `config/rabbitmq.php` (default `employee.*.*`).
2. Streams messages to `App\Domain\Employees\Handlers\ProjectingEmployeeEventHandler`, which keeps Redis snapshots up to date via `CacheEmployeeCache`.
3. ACK/NACKs messages and logs failures for observability.

Snapshots are stored at `employees:snapshots:{id}` with per-country indexes `employees:snapshots:index:{country}` for fast checklist lookups.

### End-to-End Verification Workflow

Follow this checklist whenever you need to rebuild the cache projection or confirm the Phase 3 flow end to end:

1. **Boot the stack**
   ```bash
   docker compose -f hub-service/docker-compose.yml up -d
   ```
2. **Start the HR publisher worker** (terminal A)
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hr-app \
     php artisan queue:work --queue=events --tries=5
   ```
3. **Start the Hub consumer** (terminal B)
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app \
     php artisan events:consume-employee
   ```
4. **Trigger an employee mutation** (e.g., create via HTTP)
   ```bash
   curl -X POST http://localhost:8083/api/v1/employees \
     -H 'Content-Type: application/json' \
     -d '{
           "first_name": "Ada",
           "last_name": "Lovelace",
           "salary": 120000,
           "country": "usa",
           "attributes": {"ssn": "123-45-6789"}
         }'
   ```
5. **Inspect the cached snapshot**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app \
     php artisan tinker --execute="cache()->store('redis')->get('employees:snapshots:1')"
   ```
6. **Run automated tests when needed**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app ./vendor/bin/pest
   docker compose -f hub-service/docker-compose.yml exec hr-app ./vendor/bin/pest
   ```

If you need to recreate snapshots from scratch, clear the Redis keys (`employees:snapshots:*`) and replay events by re-running the publisher + consumer with fresh API calls.

## Observing Messages via Management UI
1. Open http://localhost:15672 (user `hub`, password `secret`).
2. Create a temporary queue:
   - Navigate to **Queues & Streams → Add a new queue**
   - Name: `temp.observer` (optional: enable auto delete)
3. Bind the queue to the exchange:
   - Go to **Exchanges → employee.events**
   - Under "Add binding", choose **To queue**, select `temp.observer`, set routing key `employee.*.*`, click **Bind**
4. Trigger any CRUD request against `http://localhost:8083/api/v1/employees`.
5. Return to **Queues → temp.observer**, click the queue name, then **Get messages** (Ack mode: Ack message). Each payload includes `event_type`, `changed_fields`, and a serialized employee snapshot.

## CLI Alternative
After the queue is bound you can read messages without the web UI:

```bash
docker compose -f hub-service/docker-compose.yml exec rabbitmq \
  rabbitmqadmin get queue=temp.observer requeue=false
```

## Troubleshooting
- **No messages / exchange missing**: ensure the queue worker is running; the exchange is declared lazily when the first message is published.
- **Duplicate employees blocked**: events only fire after validation passes. USA uses `attributes.ssn` and Germany uses `attributes.tax_id` as unique keys.
- **Connection errors**: confirm RabbitMQ container is healthy (`docker compose ps`). Adjust credentials/host via `config/rabbitmq.php` if needed.
