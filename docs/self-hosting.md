# Self-hosting Poruko (alpha)

Compose-first install using a single published image:

`ghcr.io/fkupper/poruko`

The same image runs as `api`, `queue`, `scheduler`, and `web` (nginx SPA) with different commands. Images are built on git tags matching `v*` (see `.github/workflows/release-images.yml`).

## Requirements

- Docker Engine + Docker Compose v2
- PostgreSQL 16+ (bundled optional, or your own)
- Redis 7+ (bundled optional, or your own)
- A reverse proxy with TLS in front of Poruko for real deployments (Caddy, Traefik, nginx, etc.)

## Quick start (Compose)

1. Clone this repository (or copy `docker-compose.yml` and `.env.example`).
2. `cp .env.example .env` and edit values.
3. Set `APP_KEY` (32-byte base64 key):

   ```bash
   docker run --rm --entrypoint php ghcr.io/fkupper/poruko:latest \
     -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
   ```

4. Choose a database path:

   | Mode | Settings |
   |------|----------|
   | Existing Postgres + Redis | Leave `COMPOSE_PROFILES` empty. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `REDIS_PORT`, and optional `REDIS_PASSWORD`. |
   | Bundled Postgres + Redis | `COMPOSE_PROFILES=bundle`, `DB_HOST=db`, `REDIS_HOST=redis`, and a strong `DB_PASSWORD`. |

5. Set `APP_URL` to the public URL users will open (include scheme, e.g. `https://poruko.example.com`).
6. Start:

   ```bash
   docker compose up -d
   ```

7. Open the UI on port `PORUKO_HTTP_PORT` (default `8080`). Register the first account.

With `LOCK_REGISTRATION_AFTER_FIRST_USER=true` (default), open registration stops after the first user.

### Services

| Service | Role |
|---------|------|
| `web` | nginx SPA + reverse proxy for `/api` and `/up` |
| `api` | Laravel HTTP (`artisan serve` — alpha-grade) |
| `queue` | `queue:work` |
| `scheduler` | `schedule:work` (settlements + recurring materialization) |
| `db` / `redis` | Only with `COMPOSE_PROFILES=bundle` |

The API entrypoint waits for `DB_*`, optionally runs `php artisan migrate --force` when `RUN_MIGRATIONS=true` (api only), seeds default Spatie roles/permissions (`PermissionsSeeder`), then starts the process. The web role skips DB wait.

### Environment reference

| Variable | Notes |
|----------|--------|
| `PORUKO_VERSION` | Image tag (`latest` or semver without `v`) |
| `PORUKO_HTTP_PORT` | Host port mapped to web `:80` |
| `COMPOSE_PROFILES` | `bundle` enables local Postgres + Redis |
| `APP_KEY` | Required |
| `APP_URL` | Public base URL |
| `APP_ENV` / `APP_DEBUG` | Use `production` / `false` |
| `TRUSTED_PROXIES` | `*` (default), comma-separated IPs/CIDRs, or empty to disable |
| `DB_*` | Full connection; single source of truth for app and bundled `db` |
| `REDIS_*` | Host/port/password for queue, cache, session |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | Prefer `redis` |
| `RUN_MIGRATIONS` | `true` on api (default); workers force `false` |
| `LOCK_REGISTRATION_AFTER_FIRST_USER` | Homelab-friendly default |
| `ENFORCE_2FA` | Optional org policy |

### Upgrades

```bash
# In .env: PORUKO_VERSION=0.2.0  (or latest)
docker compose pull
docker compose up -d
```

Migrations run automatically on api start when `RUN_MIGRATIONS=true`.

### TLS / reverse proxy

Point your proxy at the `web` container (or host port). nginx already forwards `X-Forwarded-Proto` / `X-Forwarded-For` to the API. Set `TRUSTED_PROXIES` to `*` (default) or to your proxy’s address/CIDR so Laravel honors those headers.

Health checks:

- `GET /up` (Laravel)
- `GET /api/health` (JSON)

### Backups

- **External Postgres:** use your existing backup tooling.
- **Bundled Postgres:** back up the Docker volume `poruko-db-data` (or `pg_dump` from the `db` container).

### Building images locally

```bash
docker compose build
PORUKO_VERSION=latest docker compose up -d
```

## Kubernetes (thin manifests)

See [deploy/k8s/README.md](../deploy/k8s/README.md). Bring your own Postgres and Redis; fill Secrets with the same `DB_*` / `REDIS_*` variables.

## Alpha limitations

- API serves via `php artisan serve` (not php-fpm / FrankenPHP). Fine for small homelabs; not for heavy HA.
- Single-replica assumptions for queue/scheduler.
- No bundled TLS — terminate TLS at your ingress/proxy.
- Image packages on GHCR may need to be set **public** after the first release push (GitHub Packages settings).

CI runs on pull requests and pushes to `master` (PHPUnit, PHPStan, PHP CS Fixer, Vitest, ESLint, and the web production build). See `.github/workflows/ci.yml`.
