# Domain Overview

## Core Services

- **HR Service**
  - Responsibilities: Employee CRUD, country-specific data persistence, event publishing.
  - Integrations: PostgreSQL (employee storage), RabbitMQ (event publishing).
  - Outputs: EmployeeCreated, EmployeeUpdated, EmployeeDeleted events.
- **Hub Service**
  - Responsibilities: Event consumption, caching, checklist validation, server-driven UI APIs, WebSocket broadcasts.
  - Integrations: RabbitMQ (event ingestion), Redis (caching), Soketi (broadcasting), PostgreSQL (if persistence required).

## Entities & DTOs

- **Employee**
  - Common fields: id, name, last_name, salary, country.
  - USA fields: ssn, address.
  - Germany fields: goal, tax_id.
- **EmployeeEvent**
  - event_type: EmployeeCreated | EmployeeUpdated | EmployeeDeleted
  - event_id: UUID
  - timestamp: ISO 8601
  - country: string (ISO code)
  - data: Employee payload + changed_fields array.
- **ChecklistStatus**
  - employee_id
  - country
  - completeness_percentage
  - satisfied_rules: array of rule identifiers
  - missing_rules: array of rule identifiers + messages.
- **ChecklistSummary**
  - country
  - total_employees
  - completed_count
  - completion_rate
  - items: array<ChecklistStatus>.
- **UIConfig**
  - Steps: ordered navigation definitions (label, icon, path).
  - Columns: definitions per country for employee list (field, label, formatter).
  - Widgets: dashboard modules (identifier, datasource, channels).

## Events & Queues

- Exchange: `employee.events` (topic)
- Routing keys: `employee.{country}.{action}` where action ∈ {created, updated, deleted}
- Queues:
  - `hub.employee.events` → bound with `employee.*.*`
- Dead letter queue TBD for poison messages.

## APIs (Hub Service)

1. `GET /api/checklists`
   - Query: `country` (required)
   - Response: `ChecklistSummary`
   - Cache key: `checklist:{country}`
2. `GET /api/steps`
   - Query: `country`
   - Response: Steps navigation array
   - Cache key: `steps:{country}` (long TTL)
3. `GET /api/employees`
   - Query: `country`, `page`, `per_page`
   - Response: paginated dataset + column definitions
   - Cache key: `employees:{country}:{page}:{per_page}`
4. `GET /api/schema/{step}`
   - Query: `country`
   - Response: widget configuration for specified step
   - Cache key: `schema:{country}:{step}`

## Broadcasting Channels

- Country checklist updates: `hub.country.{country}.checklist`
- Employee-specific updates: `hub.country.{country}.employees.{employee_id}`
- Dashboard widget updates: `hub.country.{country}.widgets.{widget}`

## Caching Strategy

- Redis keys follow `namespace:{country}[:selector]` pattern.
- Invalidation triggered by event handlers:
  - Created/Updated: clear `employees` list caches + `checklist` for country.
  - Deleted: same as above plus remove employee-specific caches.
- TTL defaults: 5 minutes for checklist, 2 minutes for employee list, 15 minutes for UI config.

## Validation Rules

- USA
  - `ssn` required (format ###-##-####)
  - `salary` numeric > 0
  - `address` non-empty
- Germany
  - `salary` numeric > 0
  - `goal` non-empty
  - `tax_id` pattern `DE[0-9]{9}`

## Outstanding Decisions

- Determine if Hub persists employees or relies solely on cache + HR API fallback.
- Decide on dead-letter queue strategy and retry policy.
- Define logging format & correlation IDs.
