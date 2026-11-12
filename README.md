# Triglavian Buyback

Standalone EVE Online buyback, hauling, and financing platform inspired by the public workflows of JitaRun. This project is an independent implementation; it does not reuse JitaRun proprietary source code, branding, or private data.

## Initial scope

- Buyback appraisal: paste inventory lists/cargo scans, resolve EVE item types, quantities, origin, valuation, fees, and payout.
- Runner operations: contract board, acceptance, delivery workflow, status tracking, runner earnings.
- Financier operations: liquidity/claim workflow, withdrawal requests, payout ledger, balances.
- EVE SSO and character linking.
- ESI-backed contract and corporation asset verification.
- Administration: pricing, fees, service regions, role permissions, audit logs.
- Public activity and payout feed.

**Not in initial scope:** LP Exchange.

## Deployment goals

- Independent Docker Compose application under `/opt/buyback`.
- Dedicated database and application services.
- Independent configuration and secrets.
- Traefik reverse-proxy integration on its own hostname.
- No runtime dependency on SeAT or `/opt/rigauth`.

## Architecture

The implementation will be organized as a modular web application with clear boundaries for identity/ESI, appraisal, runner operations, financier operations, payouts, and administration. Market prices and EVE Static Data must be cached and refreshed through background jobs; appraisal results must record the price source and timestamp used so historical quotes remain explainable.

## Security and correctness

- Never commit EVE client secrets, session secrets, database passwords, or access tokens.
- Keep EVE SSO state validation and token storage server-side.
- Verify contracts and item ownership against ESI before marking deliveries complete.
- Make payout and balance changes auditable and transaction-safe.
- Treat ESI cache expiry and rate limits explicitly; never assume data is real-time.

## Project status

Repository bootstrap and scope definition. No production application or ESI credentials are included yet.
