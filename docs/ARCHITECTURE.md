# Architecture decisions

## Application framework

Laravel 12 on PHP 8.3 is the application foundation. MariaDB is the system of record. Redis is used for queues and cache. Docker Compose provides isolated services and Traefik ingress.

## Domain boundaries

- Identity: EVE SSO, character identity, token lifecycle, role authorization.
- EVE data: Static Data import, type resolution, ESI client, market observations.
- Appraisal: paste parser, item normalization, price snapshot, fee policy, immutable quote.
- Runner: job board, assignment, contract verification, delivery lifecycle.
- Ledger: append-only postings, idempotency keys, reconciliation.
- Administration: policies, pricing rules, service locations, access and audit.

## Financial integrity

Every appraisal records the exact price source, observed time, calculation version, and fee policy used. Financial balances are derived from immutable ledger entries rather than mutable balance-only fields. All payout state changes must be transactional and idempotent.

## External data

ESI is authoritative for EVE character, corporation, contract, and asset data where endpoints expose it. Market observations are timestamped and cached; quote calculations must never silently substitute a newer price into an already issued quote.
