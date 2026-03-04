# Testing Baseline

Both Laravel services use Pest for unit, feature, and integration testing.

## Commands

- `make setup-tests` – installs Composer dependencies for both services (useful in CI).
- `make test` – runs the test suites for hub-service and hr-service with code coverage enabled.
- `make test-hub` – runs only the hub-service suite.
- `make test-hr` – runs only the hr-service suite.

## Coverage Expectations

- Minimum coverage target enforced by the Makefile: **80%** via `pest --coverage --min=80`.
- Coverage reports are generated under `coverage/` within each service when supported locally.

## Hub Service — Checklist Engine Coverage (Phase 4)

- **Unit**: `ChecklistEvaluator` scenarios for complete, partial, and empty rule sets (@hub-service/tests/Unit/ChecklistEvaluatorTest.php).
- **Feature**: `/api/checklists` happy path + error envelopes (@hub-service/tests/Feature/ChecklistApiTest.php).
- **Integration**: Event handler + cache projection flows covering create/delete life cycle and broadcast assertions (@hub-service/tests/Feature/ChecklistProjectionIntegrationTest.php).
- Run with `docker compose exec hub-app ./vendor/bin/pest` while containers are up (RabbitMQ/Redis not required; tests use in-memory stores).

## Hub Service — Server-Driven UI APIs (Phase 5)

- **Feature**: `/api/steps` per-country navigation retrieval and validation envelopes (@hub-service/tests/Feature/StepsApiTest.php).
- **Feature**: `/api/employees` pagination, SSN masking, and column metadata responses (@hub-service/tests/Feature/EmployeesApiTest.php).
- **Feature**: `/api/schema/{step}` widget configuration lookups and 404 handling (@hub-service/tests/Feature/SchemaApiTest.php).
- Tests default to the in-memory cache driver; override with `CACHE_UI_STORE` to exercise Redis locally.
- Run via `docker compose -f hub-service/docker-compose.yml exec hub-app ./vendor/bin/pest tests/Feature/*.php` for targeted suites or `make test-hub` for full coverage.

## Phase 6 — Real-Time Demo & Tooling

- **Feature (HR)**: `hr:employees:seed` Artisan command seeding multi-country fixtures via domain service and publishing events (@hr-service/tests/Feature/SeedEmployeesCommandTest.php).
- **Feature (HR)**: `hr:employees:simulate-event` Artisan command covering live event publishing and dry-run behavior (@hr-service/tests/Feature/SimulateEmployeeEventCommandTest.php).
- **Feature (Hub)**: `/demo/checklist` WebSocket smoke test (manual) connects to Soketi/Pusher-compatible endpoint and logs `checklist.updated` broadcasts.
- Commands can be exercised inside containers:
  - `docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:seed --refresh`
  - `docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:simulate-event --action=updated --country=USA --employee=42`
- Full regression sweep: `make test` (runs hub + hr suites) prior to packaging demos.

### Troubleshooting the Real-Time Checklist Demo

If the summary panel never updates or the hub logs report `Failed to connect to localhost port 6001`, walk through this checklist:

1. **Confirm Soketi host overrides are present inside the hub container.**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app sh -lc "grep PUSHER .env"
   ```
   Ensure `PUSHER_HOST=soketi` and `PUSHER_CLIENT_HOST=localhost` are listed (the localhost entry may be commented-out with `#`).

2. **Clear cached configuration and verify the resolved host.**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app php artisan config:clear
   docker compose -f hub-service/docker-compose.yml exec hub-app php artisan config:show broadcasting | grep -i host
   ```
   Expect the Pusher connection host to read `soketi`; anything else indicates the env file is missing or not reloaded.

3. **(Re)start the hub RabbitMQ consumer.** Leave it running in a terminal while you test.
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app php artisan events:consume-employee
   ```

4. **Publish fresh events from the HR service.**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:seed --refresh
   ```

5. **Reload `/demo/checklist`.** Use a hard refresh (⌘⇧R / Ctrl+F5). The status badge should show `Connected` and the summary card should populate after the next `checklist.updated` broadcast.

6. **Inspect logs if broadcasts still fail.**
   ```bash
   docker compose -f hub-service/docker-compose.yml exec hub-app tail -n 100 storage/logs/laravel.log
   ```
   Any remaining `curl error 7` entries point to a Soketi host or port misconfiguration; `Class "Pusher\Pusher" not found` implies Composer dependencies or the PHP sockets extension need to be rebuilt inside `hub-app`.

## CI Guidance

- Add a GitHub Actions workflow that executes:
  ```yaml
  - run: make setup-tests
  - run: make test
  ```
- Cache Composer dependencies per service to speed up pipelines.

## Future Enhancements

- Introduce Larastan for static analysis.
- Add mutation testing once core features stabilize.
