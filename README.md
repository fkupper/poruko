# Poruko

Shared-space expense ledger for households and small groups.

## Self-hosting (alpha)

Poruko ships as a single Docker image on GHCR (`ghcr.io/fkupper/poruko`). You need Docker Compose, a Postgres database, and Redis.

```bash
# From a clone of this repo (or copy docker-compose.yml + .env.example)
cp .env.example .env

# Generate an app key (requires Docker):
docker run --rm --entrypoint php ghcr.io/fkupper/poruko:latest -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
# Put the result in APP_KEY=...

# Edit .env: APP_URL, DB_*, REDIS_*, DB_PASSWORD, APP_KEY
docker compose up -d
```

Open `http://localhost:8080` (or your `PORUKO_HTTP_PORT`). Register the first user; with `LOCK_REGISTRATION_AFTER_FIRST_USER=true`, further public registration is blocked.

**Existing Postgres / Redis:** leave `COMPOSE_PROFILES` empty and set full `DB_*` / `REDIS_*` to your instances.

**All-in-one:** set `COMPOSE_PROFILES=bundle`, `DB_HOST=db`, `REDIS_HOST=redis` (see `.env.example`).

Full guide: [docs/self-hosting.md](docs/self-hosting.md). Kubernetes: [deploy/k8s](deploy/k8s).

## Development

**Requirements:** Docker and Docker Compose. Create `api/.env` from `api/.env.example` and set `APP_KEY` (after the stack is up: `npm run artisan -- key:generate`). The dev stack also loads `api/.dev.env` (tracked) for Docker-specific settings such as database and Redis hosts.

**Start** (from the repository root):

```bash
npm run up
```

The first run may take a while while PHP dependencies are installed into the `poruko-api-vendor` Docker volume and the `web` service installs frontend dependencies inside the container.

**URLs**

- App (Vite): [http://localhost:5173](http://localhost:5173) — `/api` is proxied to the API service.
- API: [http://localhost:8000](http://localhost:8000)
- Apispec: [http://localhost:8000/docs](http://localhost:8000/docs)

**Database**

After the stack is running, apply migrations (and optionally seed dev data):

```bash
npm run seed:dev:reset
```

Default user credentials:
- Email: bob@example.com
- Password: password
- Email: clara@example.com
- Password: password

This will seed the database with some data simulation a settled month and some pending transactions.

**Stop**

```bash
npm run down
```

**Logs** (optional): `npm run logs` (defaults to the `api` service; pass a service name to follow another container).
