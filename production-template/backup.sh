#!/bin/bash

# === Database Restore Instructions for Host Machine ===
# Since we changed host ports to avoid conflicts with XAMPP/Laragon,
# if you restore from your host terminal using a native client, use these ports:
#
# For MariaDB (Host Port 3307):
# mysql -h 127.0.0.1 -P 3307 -u root -pYOUR_ROOT_PASSWORD app_db < backup.sql
#
# For PostgreSQL (Host Port 5433):
# psql -h 127.0.0.1 -p 5433 -U db_user -d app_db < backup.sql

# Configuration
BACKUP_DIR="./backups"
mkdir -p "$BACKUP_DIR"

# Timestamp format (YYYYMMDD_HHMMSS)
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/pos_db_backup_$TIMESTAMP.sql"

# Load database credentials safely from the local .env file
if [ -f .env ]; then
    echo "Loading environment configurations..."
    # Read line-by-line to safely parse variables with spaces/special characters
    while IFS= read -r line || [ -n "$line" ]; do
        # Strip leading/trailing whitespaces
        line=$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
        # Skip empty lines and lines starting with '#'
        if [[ -n "$line" && ! "$line" =~ ^# ]]; then
            export "$line"
        fi
    done < .env
else
    echo "Error: .env file not found in current directory."
    exit 1
fi

echo "=========================================="
echo "Starting Database Backup for: $DB_NAME"
echo "Time: $(date)"
echo "=========================================="

# === Database Backup Execution ===

# Option A: MariaDB / MySQL (Active by default)
docker exec "$APP_NAME-database" mariadb-dump --single-transaction -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" > "$BACKUP_FILE"

# Option B: PostgreSQL (Comment out Option A and uncomment this to switch)
# docker exec "$APP_NAME-database" pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"

# Check if the backup command succeeded
if [ $? -eq 0 ]; then
    echo "Success: Backup file created at $BACKUP_FILE"

    # Compress the SQL backup to save disk space
    gzip "$BACKUP_FILE"
    echo "Compressed backup file: ${BACKUP_FILE}.gz"

    # Keep only the last 30 days of backups to prevent running out of disk space
    echo "Cleaning up backups older than 30 days..."
    find "$BACKUP_DIR" -name "pos_db_backup_*.sql.gz" -mtime +30 -delete
    echo "Cleanup finished."
else
    echo "Error: Database backup failed!"
    exit 1
fi

echo "=========================================="
echo "Backup process finished."
echo "=========================================="
