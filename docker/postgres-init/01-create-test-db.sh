#!/bin/bash
set -e
# Create test DB (same user as main DB; init scripts run with POSTGRES_USER which has CREATEDB)
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
  CREATE DATABASE poruko_test;
EOSQL
