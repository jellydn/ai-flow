# shellcheck shell=bash
# Requires env.sh (ROOT, path_prepend, require_cmd php).
ensure_composer_phar() {
  local phar="$ROOT/backend/composer.phar"
  if [[ -f "$phar" ]]; then
    echo "$phar"
    return 0
  fi
  if command -v composer >/dev/null 2>&1; then
    echo "composer"
    return 0
  fi
  require_cmd php "Install PHP (e.g. brew install php)."
  echo "prek hook: downloading composer.phar into backend/ (one-time)…" >&2
  (
    cd "$ROOT/backend"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --quiet --install-dir=. --filename=composer.phar
    rm -f composer-setup.php
  )
  echo "$phar"
}

run_composer() {
  local composer_bin
  composer_bin="$(ensure_composer_phar)"
  if [[ "$composer_bin" == "composer" ]]; then
    composer "$@"
  else
    php "$composer_bin" "$@"
  fi
}

ensure_backend_vendor() {
  if [[ -x "$ROOT/backend/vendor/bin/pint" ]]; then
    return 0
  fi
  echo "prek hook: installing PHP dependencies (backend/vendor)…" >&2
  (cd "$ROOT/backend" && run_composer install --no-interaction --prefer-dist)
}
