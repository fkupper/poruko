#!/usr/bin/env bash

set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

staged_files="$(git diff --cached --name-only --diff-filter=ACMR)"

has_api_php=false
while IFS= read -r file; do
  if [[ "$file" == api/* ]] && [[ "$file" == *.php ]]; then
    has_api_php=true
    break
  fi
done <<< "$staged_files"

if [[ "$has_api_php" != true ]]; then
  echo "pre-commit: no staged API PHP files, skipping phpstan/php-cs-fixer."
  exit 0
fi

echo "pre-commit: staged API PHP files detected, running phpstan and php-cs-fixer..."
just phpstan
just php-cs-fixer-dry
