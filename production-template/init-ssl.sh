#!/bin/bash
set -eo pipefail

# Print usage information
show_help() {
    echo "Usage: ./init-ssl.sh [options]"
    echo ""
    echo "Automated SSL/TLS Certificate Bootstrapper for production-template"
    echo ""
    echo "Options:"
    echo "  --staging    Use Let's Encrypt staging server (to test without hit rate limits)"
    echo "  -h, --help   Show this help message"
    echo ""
    exit 0
}

USE_STAGING=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --staging)
            USE_STAGING=1
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Load environment configurations
if [ -f .env ]; then
    echo "Loading environment configurations from .env..."
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
else
    echo "Error: .env file not found in current directory."
    echo "Please copy .env.example to .env and configure DOMAIN_NAME and CERTBOT_EMAIL."
    exit 1
fi

APP_NAME="${APP_NAME:-my-production-app}"
DOMAIN_NAME="${DOMAIN_NAME:-}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"

if [ -z "$DOMAIN_NAME" ] || [ "$DOMAIN_NAME" = "example.com" ]; then
    echo "Error: DOMAIN_NAME in .env is not configured properly."
    echo "Please set DOMAIN_NAME to your actual registered domain (e.g. app.yourdomain.com)."
    exit 1
fi

if [ -z "$CERTBOT_EMAIL" ] || [ "$CERTBOT_EMAIL" = "admin@example.com" ]; then
    echo "Error: CERTBOT_EMAIL in .env is not configured properly."
    echo "Please set CERTBOT_EMAIL to a valid email address for certificate notifications."
    exit 1
fi

echo "=========================================="
echo "Initializing SSL/TLS setup for domain: $DOMAIN_NAME"
echo "Certbot Email: $CERTBOT_EMAIL"
echo "=========================================="

CERT_DIR="/etc/letsencrypt/live/$DOMAIN_NAME"
NGINX_CONTAINER="${APP_NAME}-nginx"

# Step 1: Check if production certificates already exist
echo "Checking for existing SSL certificates..."
if docker run --rm -v "${APP_NAME}-certbot-data:/etc/letsencrypt" alpine test -d "$CERT_DIR"; then
    echo "Existing certificates found in ${CERT_DIR}."
    echo "Certificates are already set up. Skipping initial creation."
    exit 0
fi

echo "No existing certificate found. Provisioning dummy self-signed cert for Nginx startup..."

# Step 2: Provision dummy self-signed certificate so Nginx can start up safely
docker run --rm \
    -v "${APP_NAME}-certbot-data:/etc/letsencrypt" \
    alpine sh -c "
        apk add --no-cache openssl && \
        mkdir -p '$CERT_DIR' && \
        openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
            -keyout '$CERT_DIR/privkey.pem' \
            -out '$CERT_DIR/fullchain.pem' \
            -subj '/CN=localhost'
    "

echo "Starting Nginx service with dummy SSL certificate..."
docker compose up -d nginx php

# Step 3: Remove dummy certificate before requesting real certificate
echo "Removing dummy certificates..."
docker run --rm \
    -v "${APP_NAME}-certbot-data:/etc/letsencrypt" \
    alpine sh -c "rm -rf /etc/letsencrypt/live/$DOMAIN_NAME /etc/letsencrypt/archive/$DOMAIN_NAME /etc/letsencrypt/renewal/$DOMAIN_NAME.conf"

# Step 4: Request real production certificate from Let's Encrypt
echo "Requesting Let's Encrypt certificate..."
STAGING_FLAG=""
if [ "$USE_STAGING" -eq 1 ]; then
    STAGING_FLAG="--staging"
    echo "Note: Running in STAGING mode."
fi

docker compose run --rm certbot certonly \
    --webroot \
    -w /var/www/certbot \
    -d "$DOMAIN_NAME" \
    --email "$CERTBOT_EMAIL" \
    --rsa-key-size 4096 \
    --agree-tos \
    --non-interactive \
    $STAGING_FLAG

# Step 5: Reload Nginx to apply new certificate
echo "Reloading Nginx web server..."
docker exec "$NGINX_CONTAINER" nginx -s reload

echo "=========================================="
echo "SSL/TLS setup completed successfully!"
echo "Your domain $DOMAIN_NAME is now secured with HTTPS."
echo "=========================================="
