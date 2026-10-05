# localDev on a VPS

Run the full localDev stack on a VPS as a shared dev/staging environment, fronted by a
Cloudflare Tunnel, Nginx Proxy Manager (NPM), and an Authentik login gate.

## Topology

```
Browser
  └─> Cloudflare edge (DNS *.dev.example.com + TLS)
        └─> cloudflared tunnel
              └─> Nginx Proxy Manager (:443, wildcard cert, Authentik forward-auth)
                    └─> localDev nginx (local-nginx:80 on the Docker network)
                          └─> php82–85 FPM, MariaDB, Postgres, Redis, Mailpit, …
```

dnsmasq and the `./dev dns setup` host-DNS tooling are **not used** on the VPS —
Cloudflare resolves the domains. Everything else (the `./dev` CLI, `projects/`,
databases) works exactly like on your laptop. Examples below use `dev.example.com`.

## Prerequisites

- Docker + Compose plugin on the VPS
- `cloudflared` installed and authenticated (`cloudflared login`)
- Nginx Proxy Manager running on the same host
- Authentik reachable from the VPS (same host or elsewhere)
- A domain managed by Cloudflare

## 1. Copy localDev to the VPS

Clone or rsync the repo to the VPS, then:

```bash
cd localDev
./vps/setup.sh dev.example.com
```

The script configures `.env` for reverse-proxied use and starts the stack:

- `DOMAIN_SUFFIX=dev.example.com` — project URLs become `myapp.dev.example.com`
  (version pinning: `myapp.php84.dev.example.com`), and `./dev new` writes matching
  `APP_URL` values
- nginx moves to `127.0.0.1:8080` / `127.0.0.1:8443` — NPM keeps ports 80/443 and
  nothing unauthenticated is publicly exposed
- the NPM container is connected to `local-dev-network` so it can reach `local-nginx`

Do **not** run `./dev dns setup` on the VPS.

## 2. Cloudflare Tunnel

Use [vps/cloudflared.config.yml](cloudflared.config.yml) as the tunnel config
(`/etc/cloudflared/config.yml`), or paste its `ingress:` block into the tunnel's
Public Hostnames in the Cloudflare dashboard (token mode).

Add a proxied wildcard DNS record:

```
*.dev.example.com  CNAME  <tunnel-id>.cfargotunnel.com
```

The wildcard covers version-pinned hosts (`myapp.php84.dev.example.com`) too.

## 3. Nginx Proxy Manager

Create a Proxy Host:

- **Domains**: `*.dev.example.com`
- **Forward to**: `http` → `local-nginx` port `80` (Websockets ON, Cache OFF)
- **SSL tab**: wildcard certificate via Let's Encrypt DNS challenge (Cloudflare API
  token), then enable **Force HTTPS**
- **Advanced tab**: paste [vps/npm-authentik.conf](npm-authentik.conf) and replace
  `AUTHENTIK_ORIGIN` with your authentik server URL (leave Custom Locations empty)

If you prefer not to terminate TLS at NPM, point cloudflared at `http://127.0.0.1:80`
instead and make sure ports 80/443 are not publicly reachable except via the tunnel.

## 4. Authentik

- Create an application + **Forward Auth** provider whose domain covers
  `*.dev.example.com` (the embedded outpost is enough)
- Keep [goauthentik.io/docs/add-secure-apps/forward-auth-nginx](https://goauthentik.io/docs/add-secure-apps/forward-auth-nginx/)
  open — if your authentik version generates its own nginx snippet, use that one in
  place of `npm-authentik.conf`

## 5. Verify

```bash
./dev new demo --type=blank
curl -I https://demo.dev.example.com    # expect 302 -> authentik
```

Then open `https://demo.dev.example.com` in a browser, log in through Authentik, and
the project should load. The dashboard is at `https://dashboard.dev.example.com`.

## Daily use

- `./dev new`, `./dev artisan`, `./dev db`, `./dev snapshot`, … work as on the laptop
- Tool UIs (Mailpit, phpMyAdmin, …) bind to `127.0.0.1` — reach them over an SSH
  tunnel (`ssh -L 8025:127.0.0.1:8025 user@vps`), or add an NPM proxy host with the
  same Authentik gate for e.g. `mailpit.dev.example.com`
- The port fallback (`http://localhost:8084/myapp`) works on the VPS itself or over
  an SSH tunnel
- Tailscale still works independently: the `TAILSCALE_DOMAIN` block serves a chosen
  project to your tailnet on `local-nginx:8084` without going through Cloudflare

## Changing configuration later

Edit `.env` and run `./dev up` — Compose recreates only the containers whose settings
changed. Don't re-run `vps/setup.sh` for config tweaks: it force-rewrites the keys it
manages (domain, nginx ports/bind, phpMyAdmin port) from its arguments.

## Security notes

- Only NPM and cloudflared face the network; nginx and all tool ports bind to
  `127.0.0.1`
- The Authentik gate is the only thing protecting your projects — keep its policy
  strict and check `curl -I` shows the 302 for every new hostname
- phpMyAdmin moves to `:8280` (set by setup.sh) to avoid clashing with nginx's `:8080`
- The php82–85 containers are shared multi-tenant runtimes — fine for your own code,
  not for untrusted users
