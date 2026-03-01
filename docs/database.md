---
# Database Access Guide

## Overview
The project uses a PostgreSQL instance defined in `hub-service/docker-compose.yml`. The database runs inside Docker under the service name `postgres` and is exposed to the host on port `5432` (or whichever port you map in compose). This guide explains how to connect to that database from the terminal using `psql` and how to perform common inspection tasks.

## Credentials & Connection Info
- **Service name (inside Docker network):** `postgres`
- **Host (from macOS/Linux host):** `127.0.0.1`
- **Default port:** `5432`
- **Database:** `hr_service`
- **User:** `hub`
- **Password:** `secret`

> These values come from `hub-service/docker-compose.yml` (`POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`). If you change them in compose, update your connections accordingly.

## Connecting via Terminal (`psql`)
Run the following from the project root to open an interactive `psql` session inside the Postgres container:

```bash
docker compose exec postgres psql -U hub -d hr_service
```

- `postgres` is the Docker service name.
- `-U hub` authenticates as the `hub` role.
- `-d hr_service` selects the `hr_service` database upon login.

If you mapped Postgres to a different port or changed credentials, adjust the command accordingly.

### One-off Queries
You can execute a single SQL statement without entering the interactive shell:

```bash
docker compose exec postgres psql -U hub -d hr_service -c "SELECT COUNT(*) FROM employees;"
```

## Useful `psql` Commands
Once inside the interactive shell:

| Command | Description |
| --- | --- |
| `\dt` | List tables in the current schema |
| `\d employees` | Show table structure for `employees` |
| `SELECT * FROM employees LIMIT 10;` | Preview data |
| `\du` | List roles |
| `\q` | Exit `psql` |

## Troubleshooting
- **Different port or credentials:** Run `docker compose port postgres 5432` to confirm the published port. If you’ve changed `POSTGRES_USER`/`POSTGRES_PASSWORD`, make sure `.env` files and `psql` commands match.
- **Conflicting local Postgres:** If another Postgres server is already listening on port `5432`, either stop it or remap the Docker port (e.g., `"5433:5432"`) and connect using that port.
- **TablePlus or other GUI tools:** They can connect using the host/port exposure (e.g., `127.0.0.1:5432` with user `hub`, password `secret`). Ensure they target the container’s port rather than a local Postgres installation.

## Reference
- Docker Compose service definition: `hub-service/docker-compose.yml`
- HR service environment example: `hr-service/.env.example`
