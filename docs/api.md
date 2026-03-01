# HR Service API Reference

Base URL (inside Docker network): `http://hr-nginx/api/v1`

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
