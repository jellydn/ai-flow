#!/usr/bin/env bash
# shellcheck source=scripts/hooks/env.sh
# shellcheck source=scripts/hooks/ensure-composer.sh
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/ensure-composer.sh"
ensure_backend_vendor
cd "$ROOT/backend"
./vendor/bin/pint --test
