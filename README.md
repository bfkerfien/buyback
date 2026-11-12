# Triglavian Buyback

Standalone EVE Online buyback, runner, and financier platform. Independent of SeAT and /opt/rigauth. LP Exchange is explicitly excluded from this release.

## Implemented

- EVE SSO login and multi-character linking; encrypted access and refresh tokens at rest.
- Role-based member/admin access, active-account enforcement, and first-admin bootstrap from EVE character IDs.
- Public inventory appraisal with shareable quotes and price snapshots.
- Admin-managed runner job board, runner acceptance and delivery status, and idempotent reward ledger entries.
- Financier liquidity and withdrawal requests, admin review, ledger postings, and audit records.
- PHP 8.3 / Laravel 12, MariaDB 11.4, Redis 7, Docker Compose, and Traefik.

## EVE SSO configuration

Create an EVE application with the callback URL exactly matching EVE_CALLBACK_URL. Set EVE_CLIENT_ID, EVE_CLIENT_SECRET, EVE_CALLBACK_URL, EVE_SCOPES=publicData, and EVE_USER_AGENT. Set BUYBACK_ADMIN_CHARACTER_IDS to a comma-separated list of EVE character IDs you control. Those characters receive the admin role when they sign in. A signed-in user can link additional characters by visiting the EVE sign-in link again. A character already linked to another account cannot be claimed.

## Deployment

1. Clone the repository into /opt/buyback and copy .env.example to .env.
2. Set a unique APP_KEY, APP_URL, BUYBACK_DOMAIN, strong unique DB_PASSWORD and DB_ROOT_PASSWORD, EVE SSO credentials, callback URL, admin character ID, and a real EVE_USER_AGENT contact.
3. Ensure DNS points the hostname to the server and Docker network traefik exists.
4. Run docker compose up -d --build.
5. Run docker compose exec app php artisan migrate --force.
6. Import CCP SDE type data and seed Jita prices:
   - docker compose exec app php artisan buyback:import-types /path/to/types.jsonl
   - docker compose exec app php artisan buyback:refresh-jita-prices TYPE_ID ...
7. Sign in with the configured admin character and use the dashboard.

## Operational boundaries

Runner delivery creates an internal earned-reward ledger entry; it does not create or verify an EVE contract or transfer ISK. Financier approval changes the internal ledger only; actual wallet transfers remain manual and must be reconciled by an administrator. ESI contract/asset verification, automated wallet transfers, scheduled price refresh, and public payout publication are not implemented. Never represent an internal ledger entry as a completed in-game payment.

All OAuth tokens are encrypted with Laravel APP_KEY. Never commit .env, EVE client secrets, database passwords, or tokens. The repository may be public.
