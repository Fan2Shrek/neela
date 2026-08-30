# Neela

**Neela** is a **dependency monitoring** dashboard for your software projects. It answers a simple question:

> Among all my projects, which ones have dependencies that can be updated?

Neela **does not automate updates**. It doesn't create branches, commits, or Pull Requests. It connects to your repositories, analyzes their dependency manifests, and centralizes the results in a single interface. It's an observer, not a bot like Dependabot or Renovate.

The project is open source and designed to be **self-hostable via Docker**.

## Screenshot

<img width="934" height="561" alt="image" src="https://github.com/user-attachments/assets/80bcd195-1bd6-4e58-bbbd-00666a5e8eb4" />

## Example

```text
NEELA — Dependency Monitor

127 projects analyzed

🔴 8 projects need significant attention
🟠 23 projects have updates available
🟢 96 projects are up to date
```

Then, project by project:

```text
site-client-a

symfony/console
  installed: 6.4.18
  compatible: 6.4.21
  type: PATCH

laravel/framework
  installed: 11.35.0
  compatible: 11.42.0
  type: MINOR
```

## Features

- **Project import** from GitHub via SSH link (token authentication, configurable from the Settings page)
- **Automatic manifest discovery** in a repo: `composer.json`/`composer.lock`, `package.json`/`package-lock.json`
- **Dependency resolution** by comparing the locked version (lockfile) against the declared constraint and the versions available on the registry (Packagist, npm), with **patch / minor / major** classification
- **Technology detection** (Symfony, Laravel, React, Vue for now) with support/end-of-life status via [endoflife.date](https://endoflife.date)
- **Global dashboard**, per-project, per-manifest, per-package, per-vendor, and per-technology views, plus scan history
- **Asynchronous scans** (Symfony Messenger) so the interface is never blocked
- Interface available in French and English

### What Neela doesn't do (not yet, or not by design)

- No vulnerability detection (coming soon)
- No automatic discovery of repos from a GitHub account/organization — import is done project by project
- No scheduled periodic scans — a scan is triggered on import or manually via "Rescan"
- No dependency managers other than Composer and npm for now (Cargo, PyPI... planned)
- No modification of your repositories: read-only, always

## Stack

- **Backend**: Symfony 8.1, PHP 8.5, Doctrine ORM, PostgreSQL, Symfony Messenger
- **Frontend**: Twig, Stimulus, Symfony UX — no SPA, intentionally simple
- **Runtime**: [FrankenPHP](https://frankenphp.dev) + Caddy (automatic HTTPS, HTTP/3)
- **Deployment**: Docker / Docker Compose

## Self-hosting

The official image is published on Docker Hub: [`sruuua/neela`](https://hub.docker.com/r/sruuua/neela).

A minimal deployment requires three services: the application, a worker that consumes scans in the background, and a PostgreSQL database. No additional infrastructure (external queue, cache...) is required: by default, the message queue reuses the database.

```yaml
# compose.yml
services:
  app:
    image: sruuua/neela:latest
    pull_policy: always
    environment:
      SERVER_NAME: ${SERVER_NAME:-:80}
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://${POSTGRES_USER:-neela}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-neela}?serverVersion=16&charset=utf8
      MESSENGER_TRANSPORT_DSN: doctrine://default
    ports:
      - "8081:80"
    depends_on:
      database:
        condition: service_healthy

  worker:
    image: sruuua/neela:latest
    restart: unless-stopped
    environment:
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://${POSTGRES_USER:-neela}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-neela}?serverVersion=16&charset=utf8
      MESSENGER_TRANSPORT_DSN: doctrine://default
    command: php -d memory_limit=512M bin/console messenger:consume --all --time-limit=3600 --memory-limit=256M
    depends_on:
      database:
        condition: service_healthy

  database:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-neela}
      POSTGRES_USER: ${POSTGRES_USER:-neela}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-neela}", "-U", "${POSTGRES_USER:-neela}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    volumes:
      - database_data:/var/lib/postgresql/data

volumes:
  database_data:
```

Then, alongside this `compose.yml`, an `.env` file:

```dotenv
POSTGRES_PASSWORD=change-me
APP_SECRET=generate-a-random-secret
```

`APP_SECRET` can be generated with `openssl rand -hex 32`.

Startup:

```console
docker compose up -d
```

Database migrations are applied automatically when the `app` container starts. Neela is then accessible at `http://localhost:8081`.

> [!TIP]
> If you have a domain name pointing to your server, set `SERVER_NAME=your-domain.example.com`, expose ports 80/443 on the `app` service, and add two volumes (`caddy_data:/data`, `caddy_config:/config`) so certificates persist across restarts: Caddy will then automatically provision HTTPS via Let's Encrypt.

Once started, go to **Settings** to set your GitHub personal access token (required for private repositories and to avoid GitHub API rate limits), then add a project from **Projects → New project**.

## Local development

This repository is based on [Symfony Docker](https://github.com/dunglas/symfony-docker) and provides a complete development environment (Dev Container included):

1. [Install Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. `docker compose build --pull --no-cache`
3. `docker compose up --wait`
4. Open `https://localhost` (accept the self-signed TLS certificate)
5. `docker compose down --remove-orphans` to stop

Additional docs inherited from the template: [available options](docs/options.md), [extra services](docs/extra-services.md), [Xdebug](docs/xdebug.md), [TLS certificates](docs/tls.md), [MySQL instead of PostgreSQL](docs/mysql.md), [troubleshooting](docs/troubleshooting.md), [AI coding agents](docs/agents.md).

## License

MIT.
