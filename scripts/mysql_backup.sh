#!/bin/bash

###############################################################################
# GekyChat MySQL Backup Script
# 
# This script creates automated MySQL backups with compression and retention
# 
# Usage: ./mysql_backup.sh
# Cron: 0 2 * * * /path/to/mysql_backup.sh
###############################################################################

# Configuration
BACKUP_DIR="/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="${DB_DATABASE:-gekychat}"
DB_USER="${DB_USERNAME:-root}"
DB_PASS="${DB_PASSWORD}"
DB_HOST="${DB_HOST:-127.0.0.1}"
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Log file
LOG_FILE="/var/log/mysql_backup.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=== Starting MySQL backup ==="

# Check if mysqldump is available
if ! command -v mysqldump &> /dev/null; then
    log_message "ERROR: mysqldump command not found"
    exit 1
fi

# Create backup with compression
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

log_message "Creating backup: $BACKUP_FILE"

# Perform backup
mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --add-drop-database \
    --databases "$DB_NAME" | gzip > "$BACKUP_FILE"

# Check if backup was successful
if [ $? -eq 0 ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log_message "Backup completed successfully: $BACKUP_SIZE"
    
    # Verify backup integrity
    if gunzip -t "$BACKUP_FILE" 2>/dev/null; then
        log_message "Backup integrity verified"
    else
        log_message "WARNING: Backup integrity check failed"
    fi
else
    log_message "ERROR: Backup failed"
    exit 1
fi

# Clean up old backups (keep last N days)
log_message "Cleaning up backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Count remaining backups
BACKUP_COUNT=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" | wc -l)
log_message "Total backups: $BACKUP_COUNT"

log_message "=== Backup completed ==="

# Optional: Send notification (uncomment and configure)
# curl -X POST "https://your-notification-service.com/webhook" \
#     -H "Content-Type: application/json" \
#     -d "{\"message\": \"MySQL backup completed: $BACKUP_SIZE\"}"

exit 0
