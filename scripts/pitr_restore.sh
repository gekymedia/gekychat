#!/bin/bash

###############################################################################
# Point-in-Time Recovery Script
# Restores database to a specific point in time
###############################################################################

if [ $# -lt 2 ]; then
    echo "Usage: $0 <backup_file> <restore_datetime>"
    echo "Example: $0 /backups/mysql/gekychat_20260110_020000.sql.gz '2026-01-10 14:30:00'"
    exit 1
fi

BACKUP_FILE=$1
RESTORE_TO_DATETIME=$2
DB_NAME="${DB_DATABASE:-gekychat}"
BINLOG_DIR="/var/log/mysql"

echo "=== Point-in-Time Recovery ==="
echo "Backup: $BACKUP_FILE"
echo "Restore to: $RESTORE_TO_DATETIME"
echo "Database: $DB_NAME"
echo ""

# Confirm
read -p "This will restore the database. Continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

# Stop application
echo "Stopping application..."
php artisan down

# Restore base backup
echo "Restoring base backup..."
gunzip < "$BACKUP_FILE" | mysql "$DB_NAME"

if [ $? -ne 0 ]; then
    echo "ERROR: Base restore failed"
    php artisan up
    exit 1
fi

# Apply binary logs
echo "Applying binary logs up to $RESTORE_TO_DATETIME..."
mysqlbinlog --stop-datetime="$RESTORE_TO_DATETIME" \
    $BINLOG_DIR/mysql-bin.* | mysql "$DB_NAME"

if [ $? -ne 0 ]; then
    echo "ERROR: Binary log application failed"
    php artisan up
    exit 1
fi

# Verify
echo "Verifying restore..."
mysql "$DB_NAME" -e "SELECT NOW();"

# Start application
echo "Starting application..."
php artisan up

echo "=== Point-in-Time Recovery completed ==="
