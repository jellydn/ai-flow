default:
    @just --list

# Run all prek hooks on every file in the repo (requires backend deps installed).
prek:
    prek run --all-files

# Oxlint + oxfmt check on frontend TS (from backend package scripts).
lint-js:
    cd backend && npm run lint

# Format frontend TS in place.
fmt:
    cd backend && npm run format

# Structural TS conventions (konsistent).
konsistent:
    cd backend && npm run konsistent
