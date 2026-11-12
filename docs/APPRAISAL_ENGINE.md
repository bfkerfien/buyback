# Appraisal engine

## Current functionality

- Paste EVE inventory data in tab-separated, CSV, plain item-name, or quantity-prefix/suffix formats.
- Duplicate item lines are merged.
- Item names resolve against the local `eve_item_types` table.
- Appraisal uses the newest stored Jita-region market observation for each item.
- Buy-side prices are the default; set `BUYBACK_PRICE_SIDE=sell` to use sell-side observations.
- The payout defaults to 90% of gross market value; configure `BUYBACK_PAYOUT_PERCENT`.
- Quotes persist item quantities, unit prices, line values, price source timestamps, and calculation settings.
- Shareable quote route: `/q/{token}`.

## Import EVE Static Data

Run migrations, then import the CCP SDE type JSONL file:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan buyback:import-types /path/inside/container/types.jsonl
```

The importer streams the file and upserts published types. The SDE file must be mounted into the app container or copied into it.

## Import market observations

Prepare a CSV with these required columns:

```csv
type_id,buy_price,sell_price,source,observed_at
34,5.10,5.40,esi-jita,2026-11-12T10:00:00Z
```

Then run:

```bash
docker compose exec app php artisan buyback:import-prices /path/inside/container/jita-prices.csv
```

The importer defaults to region `10000002` (The Forge/Jita). Optional `source` and `observed_at` columns are retained. Each import adds timestamped observations; it does not overwrite historical prices.

## Refresh directly from EVE ESI

Set `EVE_USER_AGENT` in `.env`, then refresh selected item type IDs from The Forge/Jita:

```bash
docker compose exec app php artisan buyback:refresh-jita-prices 34 35 36
```

The command requests ESI market orders, calculates the best buy and sell prices across the returned pages (up to 20), and stores a new timestamped observation for each type. Refresh only the type IDs needed; do not request every SDE type in one run.

## Important limitations

- Market observations must currently be supplied by CSV; automatic ESI market refresh is a subsequent milestone.
- Only exact item-name matches are resolved.
- This is an appraisal estimate only. Contract creation, buyback acceptance, authentication, and payout processing are not implemented in this milestone.
- Do not expose the service publicly until authentication, authorization, abuse controls, and operational policy are implemented.
