set shell := ["bash", "-cu"]

compose := "-f docker-compose.yml -f docker-compose.dev.yml"

# Docker lifecycle (dev: includes postgres init for poruko_test)
up:
    docker compose {{compose}} up -d --build

down:
    docker compose {{compose}} down

logs service="api":
    docker compose {{compose}} logs -f {{service}}

restart service="api":
    docker compose {{compose}} restart {{service}}

# API helpers (containers expected to be running)
api-sh:
    docker compose {{compose}} exec api bash

# Seed dev data (user dev@example.com / password, ledger, accounts, transactions). No-op in testing.
# Usage: just seed-dev          — seed only (idempotent for user/ledger/accounts)
#        just seed-dev reset   — migrate:fresh then seed (full DB reset)
seed-dev reset="":
    #!/usr/bin/env bash
    set -e
    if [ -n "{{reset}}" ]; then
      docker compose {{compose}} exec api php artisan migrate:fresh
    fi
    docker compose {{compose}} exec api php artisan db:seed --class=DevSeeder

artisan *args:
    docker compose {{compose}} exec api php artisan {{args}}

tinker:
    docker compose {{compose}} exec api php artisan tinker

# Web helpers (host dev/build, not Docker)
web-dev:
    cd web && npm run dev

web-build:
    cd web && npm run build

web-test:
    cd web && npm test

# Ensure test DB exists (for existing envs where postgres init already ran)
test-db-setup:
    @docker compose {{compose}} exec db psql -U poruko -d poruko -tAc "SELECT 1 FROM pg_database WHERE datname = 'poruko_test'" | grep -q 1 || docker compose {{compose}} exec db psql -U poruko -d poruko -c "CREATE DATABASE poruko_test;"

# Tests (run in Docker; test DB config in api/phpunit.xml uses poruko_test)
test-api: test-db-setup
    @docker compose {{compose}} exec api php artisan test

test-web:
    just web-test

test-all:
    just test-api
    just test-web

