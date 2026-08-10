# Poruko

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
