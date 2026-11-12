# Deployment outline

Target path: `/opt/buyback`.

1. Clone `https://github.com/bfkerfien/buyback.git`.
2. Create `.env` from `.env.example`; generate unique application/session secrets and strong DB passwords.
3. Set `BUYBACK_DOMAIN` and `APP_URL` to the chosen hostname.
4. Confirm the existing Docker network used by Traefik is actually named `traefik`; adjust the compose external network name if necessary.
5. Build and start the stack only after the application entrypoints are implemented.
6. Configure the EVE developer application callback to the exact callback URL and keep credentials only in `.env`.

The Dockerfile and Compose file are a deployment scaffold, not a deployable application yet. Do not launch this scaffold in production until the server and worker binaries exist and health checks are added.
