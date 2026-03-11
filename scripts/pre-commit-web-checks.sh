#!/usr/bin/env bash

set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

staged_files="$(git diff --cached --name-only --diff-filter=ACMR)"

has_web_changes=false

while IFS= read -r file; do
  if [[ "$file" == web/* ]]; then
    has_web_changes=true
    break
  fi
done <<< "$staged_files"

if [[ "$has_web_changes" != true ]]; then
  echo "pre-commit: no staged web files, skipping web lint/tests."
  exit 0
fi

echo "pre-commit: staged web files detected, running web lint/tests..."
npm --prefix web run lint
npm --prefix web run test
