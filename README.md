# Triglavian Buyback

Standalone EVE Online buyback, runner, and financier platform inspired by publicly observable JitaRun workflows. This is an independent implementation and does not reuse JitaRun proprietary source code, branding, or private data.

## Initial scope

- Buyback appraisal: paste EVE inventory lists/cargo scans, resolve item names, value using recorded Jita prices, and create shareable quotes.
- Runner operations: contract board, acceptance, delivery workflow, status tracking, runner earnings.
- Financier operations: liquidity/claim workflow, withdrawal requests, payout ledger, balances.
- EVE SSO and character linking.
- ESI-backed contract and corporation asset verification.
- Administration: pricing, fees, service regions, role permissions, audit logs.
- Public activity and payout feed.

**Not in initial scope:** LP Exchange.

## Stack

- PHP 8.3 / Laravel 12
- MariaDB 11.4
- Redis 7
- Docker Compose and Traefik

## Deployment goals

- Independent Docker Compose application under `/opt/buyback`.
- Dedicated database and application services.
- Independent configuration and secrets.
- Own Traefik hostname.
- No runtime dependency on SeAT or `/opt/rigauth`.

## Current implementation

The `feature/appraisal-engine` branch contains the first working application milestone:

- Laravel application entrypoint, health endpoint, and dark-themed appraisal interface.
- Inventory paste parser supporting tab-separated, CSV, and quantity-prefix/suffix formats.
- Local EVE item type catalog and streaming CCP SDE JSONL importer.
- Timestamped market-price snapshot table and CSV importer.
- Jita buy-side valuation by default, configurable payout percentage, and immutable quote item/price snapshots.
- Shareable appraisal result page.
- Unit tests for inventory parsing.

See [Appraisal Engine documentation](docs/APPRAISAL_ENGINE.md) for import and setup instructions.

## Security and correctness

- Never commit EVE client secrets, session secrets, database passwords, or access tokens.
- Keep EVE SSO state validation and token storage server-side.
- Verify contracts and item ownership against ESI before marking deliveries complete.
- Make payout and balance changes auditable and transaction-safe.
- Treat ESI cache expiry and rate limits explicitly; never assume data is real-time.

## Current limitations

Market prices are imported from CSV in this milestone; automatic ESI price refresh is next. EVE SSO, buyback acceptance, runner/financier operations, payout processing, and production access controls are not implemented yet. Do not expose this initial milestone publicly until authentication and authorization are added.
