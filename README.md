# Triglavian Buyback

Standalone EVE Online buyback platform with an optional courier runner board. Independent of SeAT and /opt/rigauth. LP Exchange is explicitly excluded.

## Included in this release

- EVE SSO login, secure state validation, multi-character linking, and encrypted access/refresh tokens.
- Member/admin roles, active-account enforcement, and administrator bootstrap by EVE character ID.
- Public inventory appraisal with shareable quotes and immutable price snapshots.
- Admin-published runner jobs, runner acceptance, ESI verification of completed courier contracts, and internal reward ledger entries.
- Audit log, PHP-FPM, Nginx, MariaDB 11.4, Redis 7, Docker Compose, and Traefik deployment.

## Configure EVE SSO

Create an EVE application and register this exact callback URL:

\`https://YOUR-BUYBACK-HOST/auth/eve/callback\`

Configure these values in \`.env\`:
- \`EVE_CLIENT_ID\` and \`EVE_CLIENT_SECRET\`
- \`EVE_CALLBACK_URL\` matching the registered callback exactly
- \`EVE_SCOPES="publicData esi-contracts.read_character_contracts.v1"\`
- \`BUYBACK_ADMIN_CHARACTER_IDS\` as comma-separated EVE character IDs that you control
- \`EVE_USER_AGENT\` with a real contact address

The configured administrator character receives the admin role when it first signs in. A signed-in user can link additional characters by selecting **Link another character** on the dashboard. A character already linked to a different account cannot be claimed.

## Server deployment

Prerequisites: Docker Engine with Compose v2, an existing external Docker network named \`traefik\`, DNS pointing your hostname at the server, and a Traefik HTTPS entrypoint named \`websecure\` with certificate resolver \`letsencrypt\`.

1. Clone \`https://github.com/bfkerfien/buyback.git\` into \`/opt/buyback\`.
2. Copy \`.env.example\` to \`.env\`.
3. Set \`APP_URL\`, \`BUYBACK_DOMAIN\`, \`DB_PASSWORD\`, \`DB_ROOT_PASSWORD\`, EVE SSO values, admin character ID, and EVE User-Agent contact. Use unique random database passwords. Leave \`APP_KEY=\` blank; the deployment script generates and saves it.
4. Ensure the EVE developer application callback matches \`EVE_CALLBACK_URL\`.
5. Run the deployment script:

\`\`\`bash
cd /opt/buyback
bash deploy.sh
\`\`\`

The script validates required production settings, checks the Traefik network, builds the PHP-FPM and Nginx images, starts MariaDB and Redis, generates an APP_KEY if missing, starts the services, applies migrations, and caches Laravel config and views. It does not delete volumes or reset the database.

## Load EVE item types and Jita prices

Place the CCP SDE JSONL \`types.jsonl\` file at \`/opt/buyback/data/types.jsonl\` on the server. The \`data/\` directory is deliberately excluded from Git and Docker build context. Then run:

\`\`\`bash
cd /opt/buyback
docker compose exec app php artisan buyback:import-types /var/www/html/data/types.jsonl
docker compose exec app php artisan buyback:import-taxonomy /var/www/html/data/categories.jsonl /var/www/html/data/groups.jsonl
docker compose exec app php artisan buyback:import-type-materials /var/www/html/data/typeMaterials.jsonl
docker compose exec app php artisan buyback:refresh-jita-prices --all
\`\`\`

All ESI calls send the configured \`X-Compatibility-Date\` and \`User-Agent\` headers. Update the compatibility date only after reviewing and testing ESI changes.

The Ore Refining page at `/ore-values` compares compressed asteroid ore Jita 4-4 buy prices against CCP SDE mineral outputs using the fixed Tatara profile shown on the page. The type importer stores each item's SDE portion size, and the type-material importer loads the full reprocessing recipe dataset.

The bulk market refresh reads The Forge market order pages and only uses orders located at Jita IV - Moon 4 - Caldari Navy Assembly Plant (station ID 60003760). It stores the best buy and sell observations for each item found there. You can refresh prices again with the same command when needed. The app will report an item as unpriced if there is no current Jita 4-4 buy/sell order for it.

**Automatic refresh:** the Buyback scheduler refreshes all Jita IV - Moon 4 prices every 30 minutes. A dedicated Docker Compose \`scheduler\` service runs Laravel's scheduler continuously, so no host cron entry is required. The task uses an overlap lock to prevent two full market refreshes from running at once. Confirm the \`scheduler\` container is running with \`docker compose ps\`.

To refresh only selected types manually:

\`\`\`bash
docker compose exec app php artisan buyback:refresh-jita-prices 34 35 36
\`\`\`

Check the service at \`https://YOUR-BUYBACK-HOST/health\`. Sign in using the configured admin EVE character, then manage buyback settings and (if used) courier runner jobs from the dashboard.

For code updates after initial deployment:

\`\`\`bash
cd /opt/buyback
git pull origin main
bash deploy.sh
\`\`\`

## Operational boundaries

Create the EVE courier contract first and assign it to the corporation (recommended for a shared runner board) or intended runner. Publish that existing contract ID in the admin dashboard. The runner accepts the job on the website, accepts the contract in EVE, completes it in-game, then submits it for ESI verification. Buyback checks the runner character's ESI contract record (courier type, finished status, and matching accepting character) before posting the reward to the internal ledger. The app does not create EVE contracts or transfer ISK.
 Corporation asset verification, automated wallet transfers, and public payout publication are not implemented. Never represent an internal ledger entry as a completed in-game payment.

All OAuth tokens are encrypted at rest using Laravel \`APP_KEY\`. Keep \`.env\`, EVE client secrets, database passwords, and tokens off GitHub. The repository may be public.
