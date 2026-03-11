set shell := ["bash", "-cu"]

# Docker lifecycle
up:
    docker compose up -d --build

down:
    docker compose down

logs service="api":
    docker compose logs -f {{service}}

restart service="api":
    docker compose restart {{service}}

# API helpers (containers expected to be running)
api-sh:
    docker compose exec api bash

artisan *args:
    docker compose exec api php artisan {{args}}

tinker:
    docker compose exec api php artisan tinker

# Web helpers (host dev/build, not Docker)
web-dev:
    cd web && npm run dev

web-build:
    cd web && npm run build

web-test:
    cd web && npm test

# Tests
test-api:
    docker compose exec api php artisan test

test-web:
    just web-test

test-all:
    just test-api
    just test-web

