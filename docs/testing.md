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
