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
- It considers contracts where My Eve Online is the assignee or acceptor, and excludes contracts issued by My Eve Online.
- It never calls the corporation contracts endpoint.
- On the first successful poll after a webhook is saved or changed, all currently visible incoming contracts are recorded as baseline history without sending alerts. Later newly appearing contracts are notified regardless of their original date_issued, so contracts that become visible late are not silently missed.
- Contract IDs are stored to prevent duplicate notifications. Successfully delivered or baselined IDs are not repeated. Failed webhook deliveries are retried by later polls.
- The webhook URL is encrypted using Laravel APP_KEY.

If the page reports a missing scope, use Link characters and complete EVE SSO as My Eve Online again. Ensure EVE_SCOPES includes esi-contracts.read_character_contracts.v1 before re-authorizing.
