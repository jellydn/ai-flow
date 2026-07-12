#!/usr/bin/env bash
# Usage: npm-in-backend.sh <npm-script-name>
# shellcheck source=scripts/hooks/env.sh
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
script="${1:?npm script name required}"
cd "$ROOT/backend"
if [[ ! -d node_modules ]]; then
  echo "prek hook: backend/node_modules missing. Run: cd backend && npm ci" >&2
  exit 127
fi
npm run "$script"
