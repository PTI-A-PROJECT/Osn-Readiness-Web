#!/usr/bin/env bash
set -Eeuo pipefail

readonly branch="monorepo"
readonly compose_file="docker-compose.prod.yml"
readonly env_file=".env.production"
skip_pull=false

if [[ "${1:-}" == "--skip-pull" ]]; then
    skip_pull=true
elif [[ $# -gt 0 ]]; then
    echo "Usage: bash scripts/deploy-vps.sh [--skip-pull]" >&2
    exit 2
fi

if [[ ! -d .git ]]; then
    echo "Deployment directory is not a Git checkout." >&2
    exit 1
fi

if [[ "$(git branch --show-current)" != "$branch" ]]; then
    echo "Expected deployment checkout on branch '$branch'." >&2
    exit 1
fi

if [[ ! -f "$env_file" ]]; then
    echo "Missing $env_file. Create it from .env.production.example on the VPS." >&2
    exit 1
fi

if [[ "$skip_pull" == false ]]; then
    git pull --ff-only origin "$branch"
    exec bash scripts/deploy-vps.sh --skip-pull
fi

if ! grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/]{43}=$' "$env_file"; then
    echo "APP_KEY must be a generated base64-encoded 32-byte Laravel key." >&2
    exit 1
fi

for key in APP_DB_PASSWORD INTERNAL_API_TOKEN; do
    value="$(grep -m1 "^${key}=" "$env_file" | cut -d= -f2- || true)"
    if [[ ! "$value" =~ ^[[:xdigit:]]{64}$ ]]; then
        echo "$key must be a 64-character hexadecimal secret." >&2
        exit 1
    fi
done

database_password="$(grep -m1 '^APP_DB_PASSWORD=' "$env_file" | cut -d= -f2-)"
internal_token="$(grep -m1 '^INTERNAL_API_TOKEN=' "$env_file" | cut -d= -f2-)"
if [[ "$database_password" == "$internal_token" ]]; then
    echo "APP_DB_PASSWORD and INTERNAL_API_TOKEN must be different secrets." >&2
    exit 1
fi

compose=(docker compose --env-file "$env_file" -f "$compose_file")
"${compose[@]}" config --quiet
"${compose[@]}" build
"${compose[@]}" up -d app-db analytics-api backend frontend
"${compose[@]}" exec -T backend php artisan migrate --force
"${compose[@]}" --profile worker up -d queue-worker
"${compose[@]}" ps
