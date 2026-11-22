# Discord alerts for incoming personal contracts

EveBuyback can send a Discord webhook notification when the personal EVE character named My Eve Online receives a new contract.

## Requirements

- Link the personal character named My Eve Online to the EveBuyback installation.
- Authorize the EVE SSO scope esi-contracts.read_character_contracts.v1.
- Discord webhook destinations are fixed in `config/services.php`; there are no admin setup forms or database overrides.
- The incoming-contract destination uses `services.discord.contract_webhook`.
- The appraisal destination uses `services.discord.appraisal_webhook`.

## Behavior

- Laravel's scheduler checks every two minutes.
- The monitor calls only GET /characters/{character_id}/contracts/.
- It considers contracts where My Eve Online is the assignee or acceptor, and excludes contracts issued by My Eve Online.
- It never calls the corporation contracts endpoint.
- On the first successful poll when no baseline exists, all currently visible incoming contracts are recorded as baseline history without sending alerts. Later newly appearing contracts are notified regardless of their original date_issued, so contracts that become visible late are not silently missed.
- Contract IDs are stored to prevent duplicate notifications. Successfully delivered or baselined IDs are not repeated. Failed webhook deliveries are retried by later polls.
- Webhook destinations are read directly from application configuration; database webhook overrides are not used.

If the page reports a missing scope, use Link characters and complete EVE SSO as My Eve Online again. Ensure EVE_SCOPES includes esi-contracts.read_character_contracts.v1 before re-authorizing.
