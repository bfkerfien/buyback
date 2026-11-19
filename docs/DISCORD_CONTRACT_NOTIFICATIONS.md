# Discord alerts for incoming personal contracts

EveBuyback can send a Discord webhook notification when the personal EVE character named My Eve Online receives a new contract.

## Requirements

- Link the personal character named My Eve Online to the EveBuyback installation.
- Authorize the EVE SSO scope esi-contracts.read_character_contracts.v1.
- Create an incoming webhook in the desired Discord channel.
- Save the webhook URL on the admin-only Discord Alerts page.

## Behavior

- Laravel's scheduler checks every two minutes.
- The monitor calls only GET /characters/{character_id}/contracts/.
- It considers only contracts whose assignee_id is the My Eve Online character ID.
- It never calls the corporation contracts endpoint.
- Existing contracts issued before the webhook was enabled are recorded as baseline history and do not create old alerts.
- Successfully delivered contract IDs are stored to prevent duplicate notifications. Failed webhook deliveries are retried by later polls.
- The webhook URL is encrypted using Laravel APP_KEY.

If the page reports a missing scope, use Link characters and complete EVE SSO as My Eve Online again. Ensure EVE_SCOPES includes esi-contracts.read_character_contracts.v1 before re-authorizing.
