#!/bin/bash
set -eo pipefail

# Configuration
BACKUP_DIR="./backups"

# Print usage help
show_help() {
    echo "Usage: ./restore.sh [options] [BACKUP_FILE]"
    echo ""
    echo "Options:"
    echo "  -y, --force    Skip interactive confirmation prompt"
    echo "  -h, --help     Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./restore.sh"
    echo "  ./restore.sh backups/my-production-app_db_backup_20260722_120000.sql.gz"
    echo "  ./restore.sh -y backups/latest.sql.gz"
    exit 0
}

# Parse options
FORCE_RESTORE=false
BACKUP_ARG=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        -y|--force)
            FORCE_RESTORE=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            BACKUP_ARG="$1"
            shift
            ;;
    esac
done

# Load environment configurations
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
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"
DB_USER="${DB_USER:-}"

CONTAINER_NAME="${APP_NAME}-database"

# Ensure container is running
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "Error: Container '${CONTAINER_NAME}' is not running."
    echo "Please start your stack first using: sudo docker compose up -d"
    exit 1
fi

# Select backup file
TARGET_FILE=""
if [ -n "$BACKUP_ARG" ]; then
    TARGET_FILE="$BACKUP_ARG"
else
    if [ ! -d "$BACKUP_DIR" ]; then
        echo "Error: Backup directory '$BACKUP_DIR' does not exist."
        exit 1
    fi

    # Find available backups sorted by modification time (newest first)
    mapfile -t BACKUP_FILES < <(find "$BACKUP_DIR" -type f \( -name "*.sql" -o -name "*.sql.gz" \) | sort -r)

    if [ ${#BACKUP_FILES[@]} -eq 0 ]; then
        echo "No backup files found in $BACKUP_DIR."
        exit 1
    fi

    echo "Available backups:"
    for i in "${!BACKUP_FILES[@]}"; do
        printf "  [%d] %s\n" $((i + 1)) "${BACKUP_FILES[$i]}"
    done

    echo ""
    read -rp "Select a backup to restore (1-${#BACKUP_FILES[@]}): " CHOICE

    if [[ ! "$CHOICE" =~ ^[0-9]+$ ]] || [ "$CHOICE" -lt 1 ] || [ "$CHOICE" -gt "${#BACKUP_FILES[@]}" ]; then
        echo "Invalid selection. Aborting."
        exit 1
    fi

    TARGET_FILE="${BACKUP_FILES[$((CHOICE - 1))]}"
fi

if [ ! -f "$TARGET_FILE" ]; then
    echo "Error: Backup file '$TARGET_FILE' does not exist."
    exit 1
fi

# Verify SHA256 checksum if checksum file exists
CHECKSUM_FILE="${TARGET_FILE}.sha256"
if [ -f "$CHECKSUM_FILE" ] && command -v sha256sum >/dev/null 2>&1; then
    echo "Verifying backup integrity via SHA256..."
    CHECKSUM_DIR=$(dirname "$TARGET_FILE")
    if (cd "$CHECKSUM_DIR" && sha256sum -c "$(basename "$CHECKSUM_FILE")" >/dev/null 2>&1); then
        echo "Checksum verification PASSED."
    else
        echo "Error: SHA256 checksum verification FAILED! The backup file may be corrupted or altered."
        exit 1
    fi
fi

echo "=========================================="
echo "Database Restore Setup"
echo "Target Container : $CONTAINER_NAME"
echo "Target Database  : $DB_NAME"
echo "Backup File      : $TARGET_FILE"
echo "=========================================="

if [ "$FORCE_RESTORE" = false ]; then
    read -rp "WARNING: This will overwrite data in database '$DB_NAME'. Continue? (y/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo "Restore cancelled by user."
        exit 0
    fi
fi

echo "Starting restoration..."

# Determine file compression and execute restore
if [[ "$TARGET_FILE" == *.gz ]]; then
    DECOMPRESS_CMD="gzip -dc"
else
    DECOMPRESS_CMD="cat"
fi

# MariaDB restore by default. If using PostgreSQL, comment MariaDB block and uncomment PostgreSQL block.
if $DECOMPRESS_CMD "$TARGET_FILE" | docker exec -i "$CONTAINER_NAME" mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME"; then
    echo "=========================================="
    echo "Success: Database restoration completed!"
    echo "=========================================="
else
    echo "Error: Database restoration failed!"
    exit 1
fi

# Option B: PostgreSQL Restore (Uncomment if using PostgreSQL)
# if $DECOMPRESS_CMD "$TARGET_FILE" | docker exec -i "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME"; then
#     echo "Success: PostgreSQL restoration completed!"
# else
#     echo "Error: PostgreSQL restoration failed!"
#     exit 1
# fi
