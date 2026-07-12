#!/usr/bin/env bash
# shellcheck source=scripts/hooks/env.sh
# shellcheck source=scripts/hooks/ensure-composer.sh
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/ensure-composer.sh"
cd "$ROOT/backend"
run_composer validate --no-interaction
