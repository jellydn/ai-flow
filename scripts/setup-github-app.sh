#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# GitHub App setup helper for ai-flow
#
# Walks through registering a GitHub App and configuring the .env file
# with the correct credentials. No GitHub API calls — just structured
# guidance and secret generation.
#
# Usage:
#   bash scripts/setup-github-app.sh
# ─────────────────────────────────────────────────────────────────────
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

# ── Colors ──────────────────────────────────────────────────────────
BOLD="\033[1m"
GREEN="\033[32m"
YELLOW="\033[33m"
CYAN="\033[36m"
RESET="\033[0m"

say()    { printf "${BOLD}%s${RESET}\n" "$1"; }
info()   { printf "  ${CYAN}→${RESET} %s\n" "$1"; }
done_()  { printf "  ${GREEN}✓${RESET} %s\n" "$1"; }
warn()   { printf "  ${YELLOW}⚠${RESET}  %s\n" "$1"; }

header() {
    echo ""
    printf "${BOLD}${CYAN}━━━ %s ━━━${RESET}\n" "$1"
    echo ""
}

# ────────────────────────────────────────────────────────────────────
# Step 1: Collect inputs
# ────────────────────────────────────────────────────────────────────

header "ai-flow GitHub App Setup"

say "This script helps you register a GitHub App and configure your .env file."
echo "  See doc/github-app-setup.md for the full step-by-step guide."
echo ""

# ── Domain ──────────────────────────────────────────────────────────
read -r -p "Enter your app's domain (e.g. ai-flow-staging.itman.fyi): " DOMAIN
DOMAIN="${DOMAIN:-localhost:8000}"
info "Webhook URL will be: https://${DOMAIN}/api/github/webhooks"

# ── App name ────────────────────────────────────────────────────────
read -r -p "GitHub App name [ai-flow]: " APP_NAME
APP_NAME="${APP_NAME:-ai-flow}"

# ── Generate webhook secret ─────────────────────────────────────────
WEBHOOK_SECRET="$(openssl rand -hex 32)"
echo ""
done_ "Generated webhook secret: ${YELLOW}${WEBHOOK_SECRET}${RESET}"

# ────────────────────────────────────────────────────────────────────
# Step 2: Show GitHub App registration settings
# ────────────────────────────────────────────────────────────────────

header "GitHub App Registration"

echo "Go to: ${BOLD}https://github.com/settings/apps/new${RESET}"
echo ""
echo "Fill in the form with these values:"
echo ""
printf "  %-28s ${GREEN}%s${RESET}\n" "GitHub App name:"         "$APP_NAME"
printf "  %-28s ${GREEN}%s${RESET}\n" "Homepage URL:"           "https://github.com/jellydn/ai-flow"
printf "  %-28s ${GREEN}%s${RESET}\n" "Webhook URL:"            "https://${DOMAIN}/api/github/webhooks"
printf "  %-28s ${YELLOW}%s${RESET}\n" "Webhook secret:"      "$WEBHOOK_SECRET"
printf "  %-28s ${GREEN}%s${RESET}\n" "SSL verification:"      "Enable"
echo ""
echo "Permissions:"
printf "  %-28s ${GREEN}%s${RESET}\n" "Issues:"                "Read & Write"
printf "  %-28s ${GREEN}%s${RESET}\n" "Contents:"              "Read-only"
printf "  %-28s ${GREEN}%s${RESET}\n" "Metadata:"              "Read-only (default)"
echo ""
echo "Subscribe to events:"
printf "  %-28s ${GREEN}%s${RESET}\n" "Issue comment:"         "✅"
echo ""
echo "Where can this GitHub App be installed?"
printf "  ${GREEN}%s${RESET}\n" "Only on this account (recommended for personal use)"
echo ""
read -r -p "Press Enter after creating the app in GitHub..." _

# ────────────────────────────────────────────────────────────────────
# Step 3: App ID & private key
# ────────────────────────────────────────────────────────────────────

header "App ID & Private Key"

say "After creating the app, you'll be on its settings page."
echo ""

read -r -p "Enter the App ID (shown at the top of the page): " APP_ID

echo ""
echo "Next, generate a private key:"
echo "  1. Scroll to ${BOLD}Private keys${RESET}"
echo "  2. Click ${BOLD}Generate a private key${RESET}"
echo "  3. Save the downloaded .pem file"
echo ""

PRIVATE_KEY_FILE=""
read -r -p "Path to the downloaded .pem file (leave blank to enter manually): " PRIVATE_KEY_FILE

if [[ -n "$PRIVATE_KEY_FILE" && -f "$PRIVATE_KEY_FILE" ]]; then
    PRIVATE_KEY="$(cat "$PRIVATE_KEY_FILE")"
    done_ "Read private key from ${PRIVATE_KEY_FILE}"
elif [[ -n "$PRIVATE_KEY_FILE" ]]; then
    warn "File not found: ${PRIVATE_KEY_FILE} — enter the key manually."
    PRIVATE_KEY_FILE=""
fi

if [[ -z "$PRIVATE_KEY_FILE" ]]; then
    echo "Paste the private key (including BEGIN/END lines), then press Ctrl+D:"
    PRIVATE_KEY="$(cat)"
fi

# Validate
if [[ "$PRIVATE_KEY" != *"BEGIN"* || "$PRIVATE_KEY" != *"END"* ]]; then
    warn "Private key doesn't look like a PEM file. Proceeding anyway — you can fix it in .env later."
fi

# Verify the key works
if command -v openssl &>/dev/null; then
    if echo "$PRIVATE_KEY" | openssl rsa -check -noout 2>/dev/null; then
        done_ "Private key is a valid RSA key."
    else
        warn "Could not validate private key with openssl. Check it manually."
    fi
fi

# ────────────────────────────────────────────────────────────────────
# Step 4: Write to .env
# ────────────────────────────────────────────────────────────────────

header "Writing .env"

# Back up existing .env if present
if [[ -f "$ENV_FILE" ]]; then
    cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%s)"
    info "Backed up existing .env to .env.bak.*"
fi

# Touch .env if absent
touch "$ENV_FILE"

# Helper: set or update a key in the .env file
set_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
        # macOS-compatible sed
        sed -i '' "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
    done_ "${key} ✓"
}

set_env "GITHUB_APP_ID"             "$APP_ID"
set_env "GITHUB_APP_PRIVATE_KEY"    "\"$(echo "$PRIVATE_KEY" | sed 's/$/\\n/' | tr -d '\n')\""
set_env "GITHUB_WEBHOOK_SECRET"     "$WEBHOOK_SECRET"
set_env "GITHUB_BOT_COMMENT_LABEL"  "ai-flow"

echo ""

# Fix the private key: multi-line keys need to be quoted properly.
# Replace the escaped newline in GITHUB_APP_PRIVATE_KEY with actual newlines.
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS sed — write the key as a proper multi-line value
    perl -i -pe 's/\\n/\n/g if /^GITHUB_APP_PRIVATE_KEY=/' "$ENV_FILE"
else
    sed -i '/^GITHUB_APP_PRIVATE_KEY=/s/\\n/\n/g' "$ENV_FILE"
fi

done_ ".env file updated."

# ────────────────────────────────────────────────────────────────────
# Step 5: Install the app
# ────────────────────────────────────────────────────────────────────

header "Install the App"

echo "1. Go to: ${BOLD}https://github.com/apps/${APP_NAME}${RESET}"
echo "2. Click ${BOLD}Install${RESET}"
echo "3. Choose the repos where you want ai-flow active"
echo "4. Click ${BOLD}Install${RESET}"
echo ""
echo "The bot only responds on ${BOLD}public${RESET} repos where it's installed."

# ────────────────────────────────────────────────────────────────────
# Done
# ────────────────────────────────────────────────────────────────────

header "All done!"

echo "Next steps:"
echo ""
echo "  1. ${BOLD}Deploy${RESET} the app so the webhook URL is live."
echo "  2. Test with: ${BOLD}@ai-flow review${RESET} on an issue/PR in an installed repo."
echo "  3. Check webhook deliveries at:"
echo "     https://github.com/settings/apps/${APP_NAME}/advanced"
echo ""
echo "  Full guide: ${BOLD}doc/github-app-setup.md${RESET}"
echo ""
