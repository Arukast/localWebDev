#!/bin/bash
set -eo pipefail

# Configuration
BACKUP_DIR="./backups"
mkdir -p "$BACKUP_DIR"

# Load database credentials safely from local .env file
if [ -f .env ]; then
    echo "Loading environment configurations..."
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
else
    echo "Error: .env file not found in current directory."
    exit 1
fi

APP_NAME="${APP_NAME:-app}"
DB_NAME="${DB_NAME:-app_db}"

# Timestamp format (YYYYMMDD_HHMMSS)
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/${APP_NAME}_db_backup_$TIMESTAMP.sql"

echo "=========================================="
echo "Starting Database Backup for: $DB_NAME"
echo "Application: $APP_NAME"
echo "Time: $(date)"
echo "=========================================="

# === Database Backup Execution ===

# Option A: MariaDB / MySQL (Active by default)
docker exec "$APP_NAME-database" mariadb-dump --single-transaction -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" > "$BACKUP_FILE"

# Option B: PostgreSQL (Comment out Option A and uncomment this to switch)
# docker exec "$APP_NAME-database" pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"

# Check if the backup command succeeded
if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
    echo "Success: Backup file created at $BACKUP_FILE"

    # Compress the SQL backup to save disk space
    gzip "$BACKUP_FILE"
    echo "Compressed backup file: ${BACKUP_FILE}.gz"

    # Keep only the last 30 days of backups to prevent running out of disk space
    echo "Cleaning up backups older than 30 days..."
    find "$BACKUP_DIR" -name "${APP_NAME}_db_backup_*.sql.gz" -mtime +30 -delete
    echo "Cleanup finished."
else
    echo "Error: Database backup failed or created an empty file!"
    rm -f "$BACKUP_FILE"
    exit 1
fi

echo "=========================================="
echo "Backup process finished successfully."
echo "=========================================="

