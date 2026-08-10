#!/usr/bin/env bash
set -euo pipefail

# Web role: serve the SPA via nginx (no DB wait / migrations).
if [[ "${PORUKO_ROLE:-}" == "web" ]] || [[ "${1:-}" == "nginx" ]]; then
  exec "$@"
fi

wait_for_db() {
  local host="${DB_HOST:-}"
  local port="${DB_PORT:-5432}"
  local database="${DB_DATABASE:-}"
  local username="${DB_USERNAME:-}"

  if [[ -z "$host" || -z "$database" || -z "$username" ]]; then
    echo "entrypoint: DB_HOST, DB_DATABASE, and DB_USERNAME are required" >&2
    exit 1
  fi

  echo "entrypoint: waiting for Postgres at ${host}:${port}/${database}..."
  local attempt=0
  local max_attempts="${DB_WAIT_MAX_ATTEMPTS:-60}"

  until php -r '
    $host = getenv("DB_HOST");
    $port = getenv("DB_PORT") ?: "5432";
    $db = getenv("DB_DATABASE");
    $user = getenv("DB_USERNAME");
    $pass = getenv("DB_PASSWORD");
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $port, $db);
    try {
      new PDO($dsn, $user, $pass !== false ? $pass : null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
      ]);
      exit(0);
    } catch (Throwable $e) {
      exit(1);
    }
  '; do
    attempt=$((attempt + 1))
    if [[ "$attempt" -ge "$max_attempts" ]]; then
      echo "entrypoint: timed out waiting for Postgres after ${max_attempts} attempts" >&2
      exit 1
    fi
    sleep 2
  done

  echo "entrypoint: Postgres is reachable"
}

cache_runtime() {
  local app_env="${APP_ENV:-production}"

  if [[ "${SKIP_CONFIG_CACHE:-false}" == "true" ]]; then
    echo "entrypoint: SKIP_CONFIG_CACHE=true; skipping config/route/view cache"
    return 0
  fi

  if [[ "$app_env" == "local" || "$app_env" == "testing" ]]; then
    echo "entrypoint: APP_ENV=${app_env}; skipping config/route/view cache"
    return 0
  fi

  if [[ -z "${APP_KEY:-}" ]]; then
    echo "entrypoint: APP_KEY is empty; skipping config/route/view cache" >&2
    return 0
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache || true
}

wait_for_db
cache_runtime

if [[ "${RUN_MIGRATIONS:-false}" == "true" ]]; then
  echo "entrypoint: running migrations..."
  php artisan migrate --force
fi

exec "$@"
