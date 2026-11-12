#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"

fail() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }
[[ -f .env ]] || fail "Missing .env. Copy .env.example to .env and configure it first."
command -v docker >/dev/null 2>&1 || fail "Docker is not installed or not on PATH."
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required."
docker network inspect traefik >/dev/null 2>&1 || fail "The external Docker network 'traefik' does not exist."

grep -Eq '^APP_ENV=production$' .env || fail "Set APP_ENV=production in .env."
grep -Eq '^APP_DEBUG=false$' .env || fail "Set APP_DEBUG=false in .env."
grep -Eq '^APP_URL=https://[^[:space:]]+$' .env || fail "Set APP_URL to the public HTTPS URL."
grep -Eq '^BUYBACK_DOMAIN=[^[:space:]]+$' .env || fail "Set BUYBACK_DOMAIN to the public hostname."
grep -q 'CHANGE_ME' .env && fail "Replace all CHANGE_ME database password placeholders in .env."
grep -Eq '^DB_PASSWORD=
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=[0-9]+(,[0-9]+)*
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_PASSWORD in .env."
grep -Eq '^DB_ROOT_PASSWORD=
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_ROOT_PASSWORD in .env."
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env || fail "Set comma-separated numeric EVE character IDs in BUYBACK_ADMIN_CHARACTER_IDS."
grep -Eq '^EVE_USER_AGENT=$|^EVE_USER_AGENT=""
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_PASSWORD in .env."
grep -Eq '^DB_ROOT_PASSWORD=
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_ROOT_PASSWORD in .env."
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set EVE_USER_AGENT with your project name and a real contact address."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_PASSWORD in .env."
grep -Eq '^DB_ROOT_PASSWORD=
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
 .env && fail "Set a strong DB_ROOT_PASSWORD in .env."
grep -Eq '^EVE_CLIENT_ID=$' .env && fail "Set EVE_CLIENT_ID in .env."
grep -Eq '^EVE_CLIENT_SECRET=$' .env && fail "Set EVE_CLIENT_SECRET in .env."
grep -Eq '^EVE_CALLBACK_URL=https://buyback\.example\.com/' .env && fail "Set EVE_CALLBACK_URL to this deployment's exact /auth/eve/callback URL."
grep -Eq '^BUYBACK_ADMIN_CHARACTER_IDS=$' .env && fail "Set at least one EVE character ID in BUYBACK_ADMIN_CHARACTER_IDS."
grep -q 'contact@example.com' .env && fail "Replace the placeholder EVE_USER_AGENT contact address."
grep -Eq '^EVE_SCOPES=.*esi-contracts\.read_character_contracts\.v1' .env || fail "EVE_SCOPES must include esi-contracts.read_character_contracts.v1."

mkdir -p data
docker compose config --quiet
printf '\nBuilding Buyback containers...\n'
docker compose build --pull
printf '\nStarting database and Redis...\n'
docker compose up -d --wait db cache

if grep -Eq '^APP_KEY=$' .env; then
    printf '\nGenerating Laravel encryption key...\n'
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show | awk '/^base64:/{print; exit}')"
    [[ "$key" == base64:* ]] || fail "Laravel did not return a valid APP_KEY."
    sed -i "s|^APP_KEY=.*|APP_KEY=$key|" .env
fi

printf '\nStarting application, web server, and queue worker...\n'
docker compose up -d --wait
printf '\nApplying database migrations...\n'
docker compose exec -T app php artisan migrate --force
printf '\nCaching production configuration and views...\n'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan view:cache

printf '\nDeployment containers:\n'
docker compose ps
printf '\nBuyback is running. Import CCP SDE data into ./data/types.jsonl, then run:\n'
printf '  docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl\n'
printf '  docker compose exec app php artisan buyback:refresh-jita-prices --all\n'
