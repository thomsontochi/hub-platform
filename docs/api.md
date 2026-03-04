# API Reference

This document covers both services. URLs assume execution inside the Docker network (substitute `localhost` ports when calling via Nginx proxies).

---

## Hub Service — Server-Driven UI APIs (Phase 5)

- **Base URL**: `http://hub-nginx/api`

### List Steps

- **GET** `/steps`
- **Query Parameters**
  - `country` (required, string – lower-case ISO code used in config)
- **Response 200**
  ```json
  {
    "data": [
      {
        "id": "dashboard",
        "label": "Overview",
        "icon": "ph-gauge",
        "path": "/dashboard",
        "meta": {
          "description": "KPIs and summaries"
        }
      }
    ]
  }
  ```

### List Employees (Server-driven Table)

- **GET** `/employees`
- **Query Parameters**
  - `country` (required, string – lower-case ISO code)
  - `per_page` (optional, int, default 15, max 100)
  - `page` (optional, int)
- **Response 200**
  ```json
  {
    "data": {
      "columns": [
        {
          "field": "name",
          "key": "name",
          "label": "First Name",
          "type": "text"
        },
        {
          "field": "ssn",
          "key": "attributes.ssn",
          "label": "SSN",
          "type": "text",
          "mask": true
        }
      ],
      "employees": [
        {
          "id": 1,
          "name": "John",
          "last_name": "Doe",
          "salary": 75000,
          "country": "USA",
          "attributes": {
            "ssn": "***-**-6789"
          },
          "meta": []
        }
      ]
    },
    "links": {
      "first": "http://hub-nginx/api/employees?page=1",
      "last": "http://hub-nginx/api/employees?page=1",
      "prev": null,
      "next": null
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "path": "http://hub-nginx/api/employees",
      "per_page": 15,
      "to": 1,
      "total": 1
    }
  }
  ```

> Sensitive values (e.g. SSN) are masked using `***-**-1234` format before leaving the service.

### Step Schema (Widgets)

- **GET** `/schema/{step}`
- **URI Parameters**
  - `step` (required, string – step identifier as returned by `/steps`)
- **Query Parameters**
  - `country` (required, string)
- **Response 200**
  ```json
  {
    "data": [
      {
        "id": "employee_count",
        "type": "stat",
        "label": "Active employees",
        "icon": "ph-users-three",
        "data_source": "employees.total",
        "channels": ["checklists.us"],
        "meta": {
          "presentation": "large"
        }
      }
    ]
  }
  ```
- **Response 404** — returned when no widgets are configured for the requested step/country.

> Responses are cached per country/step using `ui.cache.*` TTLs; caches are invalidated on employee events.

---

## Hub Service — Real-Time Checklist Demo (Phase 6)

- **Route**: `GET /demo/checklist`
- **View**: `resources/views/realtime-checklist.blade.php`
- **Purpose**: Connect to Soketi/Pusher-compatible WebSocket server and visualize `checklist.updated` traffic for a selected country.
- **Configuration**: Reads credentials from `config('services.pusher')` (see `.env` `PUSHER_*` keys). Works with any Pusher protocol server; default Docker stack uses Soketi at `http://soketi:6001`.
- **Usage**:
  1. Ensure containers are running: `docker compose -f hub-service/docker-compose.yml up -d`.
  2. Open `http://localhost:8082/demo/checklist`.
  3. Run CLI utilities from the HR service (see below) to broadcast events and observe updates in real time.

---

## HR Service — CLI Utilities (Phase 6)

### Seed Employees

- **Command**: `php artisan hr:employees:seed`
- **Options**:
  - `--refresh`: Truncate the `employees` table before seeding.
- **Behavior**: Creates canonical USA/Germany employee records via `EmployeeService`, dispatching RabbitMQ events for each record.
- **Container usage**: `docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:seed --refresh`
- **Tests**: `@hr-service/tests/Feature/SeedEmployeesCommandTest.php`

### Simulate Employee Events

- **Command**: `php artisan hr:employees:simulate-event`
- **Options**:
  - `--action=` (`created|updated|deleted`)
  - `--country=` (`USA|GERMANY`, defaults to USA)
  - `--employee=` Existing employee ID to hydrate payload
  - `--payload=` Raw JSON payload override
  - `--dry-run`: Print payload without publishing to RabbitMQ
- **Behavior**: Publishes payloads through `PublishEmployeeEvent` queue, enabling WebSocket demo to receive synthetic traffic.
- **Container usage**: `docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:simulate-event --action=updated --country=USA --employee=1`
- **Tests**: `@hr-service/tests/Feature/SimulateEmployeeEventCommandTest.php`

---

## HR Service API Reference

Base URL: `http://hr-nginx/api/v1`

## Authentication

- None (challenge scope). All endpoints return JSON.

## Employees

### List Employees

- **GET** `/employees`
- **Query Parameters**
  - `country` (required, string - ISO code)
  - `per_page` (optional, int, default 15, max 100)
  - `page` (optional, int)
- **Response 200**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "John",
        "last_name": "Doe",
        "salary": 75000.0,
        "country": "USA",
        "attributes": {
          "ssn": "123-45-6789",
          "address": "123 Main St"
        },
        "created_at": "2026-02-27T11:45:00Z",
        "updated_at": "2026-02-27T11:45:00Z"
      }
    ],
    "links": {
      "next": null,
      "prev": null
    },
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
  ```

### Create Employee

- **POST** `/employees`
- **Body**
  ```json
  {
    "name": "John",
    "last_name": "Doe",
    "salary": 75000,
    "country": "USA",
    "attributes": {
      "ssn": "123-45-6789",
      "address": "123 Main St"
    }
  }
  ```
- **Response 201**
  ```json
  {
    "data": {
      "id": 1,
      "name": "John",
      "last_name": "Doe",
      "salary": 75000.0,
      "country": "USA",
      "attributes": {
        "ssn": "123-45-6789",
        "address": "123 Main St"
      },
      "created_at": "2026-02-27T11:45:00Z",
      "updated_at": "2026-02-27T11:45:00Z"
    }
  }
  ```

### Show Employee

- **GET** `/employees/{id}`
- **Response 200** – same `data` structure as create.

### Update Employee

- **PATCH** `/employees/{id}`
- **Body** (partial updates allowed)
  ```json
  {
    "salary": 82000,
    "attributes": {
      "address": "500 Market St"
    }
  }
  ```
- **Response 200** – updated `data` structure.

### Delete Employee

- **DELETE** `/employees/{id}`
- **Response 204** (no body)

## Event Payloads

All CRUD operations dispatch a message to RabbitMQ exchange `employee.events` using topic routing keys: `employee.{country}.{action}` where `{country}` is lower-case ISO code and `{action}` is `created`, `updated`, or `deleted`.

### Sample `EmployeeCreated`
```json
{
  "event_type": "EmployeeCreated",
  "event_id": "a5f06c63-737a-4d18-a1d2-8ccf7ce3f1f7",
  "timestamp": "2026-02-27T11:46:22Z",
  "country": "USA",
  "data": {
    "employee_id": 1,
    "changed_fields": [
      "name",
      "last_name",
      "salary",
      "country",
      "attributes.ssn",
      "attributes.address"
    ],
    "employee": {
      "id": 1,
      "name": "John",
      "last_name": "Doe",
      "salary": 75000.0,
      "country": "USA",
      "attributes": {
        "ssn": "123-45-6789",
        "address": "123 Main St"
      },
      "created_at": "2026-02-27T11:45:00Z",
      "updated_at": "2026-02-27T11:45:00Z"
    }
  }
}
```

### Sample `EmployeeUpdated`
```json
{
  "event_type": "EmployeeUpdated",
  "event_id": "f4c6b76e-4707-46e5-8540-46b3c246efef",
  "timestamp": "2026-02-27T11:55:12Z",
  "country": "GERMANY",
  "data": {
    "employee_id": 9,
    "changed_fields": ["salary", "attributes.goal"],
    "employee": {
      "id": 9,
      "name": "Hans",
      "last_name": "Mueller",
      "salary": 68000.0,
      "country": "GERMANY",
      "attributes": {
        "goal": "Increase team productivity by 20%",
        "tax_id": "DE123456789"
      },
      "created_at": "2026-02-27T11:40:00Z",
      "updated_at": "2026-02-27T11:55:12Z"
    }
  }
}
```

### Sample `EmployeeDeleted`
```json
{
  "event_type": "EmployeeDeleted",
  "event_id": "51ded7ec-2c35-4241-8974-889f8c7c0fa3",
  "timestamp": "2026-02-27T12:01:04Z",
  "country": "USA",
  "data": {
    "employee_id": 1,
    "changed_fields": [],
    "employee": {
      "id": 1,
      "name": "John",
      "last_name": "Doe",
      "salary": 75000.0,
      "country": "USA",
      "attributes": {
        "ssn": "123-45-6789",
        "address": "123 Main St"
      },
      "created_at": "2026-02-27T11:45:00Z",
      "updated_at": "2026-02-27T11:45:00Z"
    }
  }
}
```

> Hub Service will bind to `employee.*.*` and react based on `country`/`event_type`.

---

## RabbitMQ Management & Message Flow

- **Management UI**: `http://localhost:15672` (login: `hub` / `secret` as defined in `.env`).
- **Exchange**: `employee.events` (topic)
- **Queues**:
  - `hub.employee.events` — bound to routing key pattern `employee.*.*`.
- **Producers**: HR Service via `PublishEmployeeEvent` job or CLI simulation command.
- **Consumers**: Hub Service `ProjectingEmployeeEventHandler` (`php artisan events:consume-employee`).
- **Typical flow**:
  1. HR Service emits `EmployeeUpdated` payload to RabbitMQ.
  2. Hub Service consumes from `hub.employee.events`, updates Redis caches, rebroadcasts `checklist.updated` via Soketi.
  3. `/demo/checklist` page receives event and updates panels.

For manual testing, use:

```bash
# Seed fixtures and create traffic
docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:seed --refresh

# Simulate a live update for USA employee #1
docker compose -f hub-service/docker-compose.yml exec hr-app php artisan hr:employees:simulate-event --action=updated --country=USA --employee=1
```

---
