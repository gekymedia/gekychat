#!/bin/bash

###############################################################################
# GekyChat Storage Backup Script
# 
# This script backs up the Laravel storage directory
# 
# Usage: ./storage_backup.sh
# Cron: 0 3 * * * /path/to/storage_backup.sh
###############################################################################

# Configuration
STORAGE_DIR="/var/www/gekychat/storage/app"
BACKUP_DIR="/backups/storage"
DATE=$(date +%Y%m%d)
RETENTION_DAYS=7

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Log file
LOG_FILE="/var/log/storage_backup.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=== Starting storage backup ==="

# Check if rsync is available
if ! command -v rsync &> /dev/null; then
    log_message "ERROR: rsync command not found"
    exit 1
fi

# Create backup directory for today
BACKUP_PATH="$BACKUP_DIR/storage_${DATE}"
mkdir -p "$BACKUP_PATH"

log_message "Syncing storage to: $BACKUP_PATH"

# Sync storage directory
rsync -avz \
    --delete \
    --exclude 'framework/cache/*' \
    --exclude 'framework/sessions/*' \
    --exclude 'framework/views/*' \
    --exclude 'logs/*' \
    "$STORAGE_DIR/" \
    "$BACKUP_PATH/"

# Check if sync was successful
if [ $? -eq 0 ]; then
    BACKUP_SIZE=$(du -sh "$BACKUP_PATH" | cut -f1)
    log_message "Storage backup completed successfully: $BACKUP_SIZE"
    
    # Count files backed up
    FILE_COUNT=$(find "$BACKUP_PATH" -type f | wc -l)
    log_message "Files backed up: $FILE_COUNT"
else
    log_message "ERROR: Storage backup failed"
    exit 1
fi

# Clean up old backups (keep last N days)
log_message "Cleaning up backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -maxdepth 1 -type d -name "storage_*" -mtime +$RETENTION_DAYS -exec rm -rf {} \;

# Count remaining backups
BACKUP_COUNT=$(find "$BACKUP_DIR" -maxdepth 1 -type d -name "storage_*" | wc -l)
log_message "Total storage backups: $BACKUP_COUNT"

log_message "=== Storage backup completed ==="

exit 0
