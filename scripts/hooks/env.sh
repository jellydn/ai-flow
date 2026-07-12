# Shared PATH for prek local hooks (non-login shells omit mise/Homebrew).
# shellcheck shell=bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export ROOT

path_prepend() {
  local dir="$1"
  if [[ -d "$dir" ]]; then
    export PATH="$dir:$PATH"
  fi
}

path_prepend "${HOME}/.local/share/mise/shims"
path_prepend "${HOME}/.local/bin"
path_prepend "/opt/homebrew/bin"
path_prepend "/usr/local/bin"

if command -v mise >/dev/null 2>&1; then
  # Activate mise env for this repo (PHP/composer from .mise.toml or global tools).
  eval "$(mise activate bash --shims 2>/dev/null || true)"
fi

require_cmd() {
  local name="$1"
  local hint="${2:-}"
  if ! command -v "$name" >/dev/null 2>&1; then
    echo "prek hook: '$name' not found on PATH." >&2
    [[ -n "$hint" ]] && echo "$hint" >&2
    exit 127
  fi
}
