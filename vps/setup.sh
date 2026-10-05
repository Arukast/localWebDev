#!/usr/bin/env bash
set -e

# localDev VPS bootstrap.
# Prepares .env for a reverse-proxied deployment (Cloudflare Tunnel -> Nginx Proxy
# Manager -> localDev nginx) and starts the stack.
#
# Usage: ./setup.sh <domain> [npm-container]
#   e.g. ./setup.sh dev.example.com nginx-proxy-manager

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="${1:-}"
NPM_CONTAINER="${2:-}"

if [[ -z "$DOMAIN" ]]; then
    echo "❌ Usage: ./setup.sh <domain> [npm-container]  (e.g. ./setup.sh dev.example.com)"
    exit 1
fi

command -v docker &>/dev/null || { echo "❌ Docker is not installed."; exit 1; }
docker compose version &>/dev/null || { echo "❌ Docker Compose plugin is not installed."; exit 1; }

# Dots escaped as [.] so the value needs no backslashes in .env
DOMAIN_REGEX="${DOMAIN//./[.]}"

# Helper: Set (or replace) KEY=VALUE in an env file, uncommenting if needed
set_env_key() {
    local file="$1" key="$2" value="$3" esc
    esc="${value//\\/\\\\}"
    esc="${esc//&/\\&}"
    esc="${esc//|/\\|}"
    if grep -qE "^[[:space:]]*#?[[:space:]]*${key}=" "$file"; then
        sed -i -E "s|^[[:space:]]*#?[[:space:]]*${key}=.*|${key}=${esc}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo "✅ Created .env from .env.example"
fi

set_env_key .env DOMAIN_SUFFIX "$DOMAIN"
set_env_key .env DOMAIN_SUFFIX_REGEX "$DOMAIN_REGEX"
set_env_key .env NGINX_BIND_IP "127.0.0.1"
set_env_key .env NGINX_PORT_HTTP "8080"
set_env_key .env NGINX_PORT_HTTPS "8443"
set_env_key .env PHPMYADMIN_PORT "8280"
echo "✅ Configured .env for reverse-proxied deployment (domain: $DOMAIN)"

echo "🚀 Starting localDev stack..."
./dev up

# Attach Nginx Proxy Manager to the localDev network so it can reach local-nginx
if [[ -z "$NPM_CONTAINER" ]]; then
    NPM_CONTAINER="$(docker ps --format '{{.Names}}\t{{.Image}}' | awk -F'\t' 'tolower($0) ~ /proxy.?manager/ {print $1; exit}')"
fi

if [[ -n "$NPM_CONTAINER" ]]; then
    if docker network inspect local-dev-network --format '{{range .Containers}}{{.Name}} {{end}}' | grep -qw "$NPM_CONTAINER"; then
        echo "✅ '$NPM_CONTAINER' is already on local-dev-network"
    else
        docker network connect local-dev-network "$NPM_CONTAINER"
        echo "✅ Connected '$NPM_CONTAINER' to local-dev-network"
    fi
else
    echo "⚠️  Could not find the Nginx Proxy Manager container."
    echo "   Run manually: docker network connect local-dev-network <npm-container>"
fi

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Next steps (details in vps/README.md):
 1. Cloudflare: add the tunnel ingress from vps/cloudflared.config.yml and the
    wildcard DNS record:  *.$DOMAIN CNAME <tunnel-id>.cfargotunnel.com
 2. Nginx Proxy Manager: proxy host *.$DOMAIN -> http://local-nginx:80
    (SSL: wildcard cert via DNS challenge + Force HTTPS;
     Advanced tab: paste vps/npm-authentik.conf)
 3. Authentik: create a Forward Auth provider covering *.$DOMAIN
 4. Verify: curl -I https://demo.$DOMAIN   (expect a 302 to Authentik)
EOF
