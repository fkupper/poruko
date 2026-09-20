#!/usr/bin/env bash
# Dev tasks via docker compose (dev overlay). Run from repo root: bash scripts/dev.sh <command> [args]

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

# Always start bundled db + redis in dev (see profiles on docker-compose.yml).
export COMPOSE_PROFILES="${COMPOSE_PROFILES:-bundle}"

if docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker-compose)
else
  echo "Error: Neither 'docker compose' nor 'docker-compose' was found." >&2
  exit 1
fi

compose() {
  "${COMPOSE_CMD[@]}" -f docker-compose.yml -f docker-compose.dev.yml "$@"
}

test_db_setup() {
  compose exec db psql -U poruko -d poruko -tAc "SELECT 1 FROM pg_database WHERE datname = 'poruko_test'" | grep -q 1 \
    || compose exec db psql -U poruko -d poruko -c "CREATE DATABASE poruko_test;"
}

usage() {
  cat <<'EOF'
Usage: bash scripts/dev.sh <command> [args]

Docker lifecycle:
  up                 build, install PHP deps into ./api, then up -d
  down               docker compose down
  logs [service]     follow logs (default service: api)
  restart [service]  restart container (default: api)
  api-sh             shell into api container

API:
  artisan <args>...  php artisan in api container
  tinker             php artisan tinker
  seed-dev [reset]   db:seed:dev (optional: reset = migrate:fresh first)
  seed-dev-pending [reset]   db:seed:dev --pending

Tests / quality:
  test-db-setup      ensure poruko_test database exists
  test-api [phpunit args]...  run API tests (after test-db-setup)
                                 or: TEST_FILTER='Pat' bash scripts/dev.sh test-api
  phpstan
  php-cs-fixer-fix
  php-cs-fixer-dry
EOF
}

cmd="${1:-}"
shift || true

ensure_api_vendor() {
  # ./api is bind-mounted, but vendor lives in the poruko-api-vendor volume
  # (see docker-compose.dev.yml). Populate that volume before api/queue start.
  # Bypass entrypoint: --no-deps means db is not up, and composer does not need it.
  echo "Ensuring PHP dependencies are installed..."
  compose run --rm --no-deps --entrypoint composer api install --prefer-dist --no-interaction
}

case "$cmd" in
  up)
    compose build
    ensure_api_vendor
    compose up -d
    ;;
  down)
    compose down
    ;;
  logs)
    compose logs -f "${1:-api}"
    ;;
  restart)
    compose restart "${1:-api}"
    ;;
  api-sh)
    compose exec api bash
    ;;
  artisan)
    compose exec api php artisan "$@"
    ;;
  tinker)
    compose exec api php artisan tinker
    ;;
  seed-dev)
    if [[ "${1:-}" == "reset" ]]; then
      compose exec api php artisan migrate:fresh
    fi
    compose exec api php artisan db:seed:dev
    ;;
  seed-dev-pending)
    if [[ "${1:-}" == "reset" ]]; then
      compose exec api php artisan migrate:fresh
    fi
    compose exec api php artisan db:seed:dev --pending
    ;;
  test-db-setup)
    test_db_setup
    ;;
  test-api)
    test_db_setup
    if [[ -n "${TEST_FILTER:-}" ]]; then
      compose exec api php artisan test --filter="$TEST_FILTER" "$@"
    else
      compose exec api php artisan test "$@"
    fi
    ;;
  phpstan)
    compose exec api bash -c "cd /var/www/html && vendor/bin/phpstan analyse -c tools/Phpstan/phpstan.neon --memory-limit=512M"
    ;;
  php-cs-fixer-fix)
    compose exec api bash -c "cd /var/www/html && vendor/bin/php-cs-fixer fix -v --config=tools/PhpCsFixer/.php-cs-fixer.dist.php"
    ;;
  php-cs-fixer-dry)
    compose exec api bash -c "cd /var/www/html && vendor/bin/php-cs-fixer fix -v --dry-run --diff --config=tools/PhpCsFixer/.php-cs-fixer.dist.php"
    ;;
  ""|-h|--help)
    usage
    if [[ "$cmd" == "-h" || "$cmd" == "--help" ]]; then
      exit 0
    fi
    exit 1
    ;;
  *)
    echo "Unknown command: $cmd" >&2
    usage >&2
    exit 1
    ;;
esac
