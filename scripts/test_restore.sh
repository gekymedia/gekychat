#!/bin/bash

###############################################################################
# GekyChat Restore Test Script
# Tests database restore procedures
###############################################################################

BACKUP_DIR="/backups/mysql"
TEST_DB="gekychat_restore_test"
LOG_FILE="/var/log/restore_test.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=== Starting restore test ==="

# Get latest backup
LATEST_BACKUP=$(ls -t $BACKUP_DIR/*.sql.gz | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    log_message "ERROR: No backup found"
    exit 1
fi

log_message "Testing backup: $LATEST_BACKUP"

# Test integrity
log_message "Testing backup integrity..."
if gunzip -t "$LATEST_BACKUP" 2>/dev/null; then
    log_message "✓ Backup integrity verified"
else
    log_message "✗ Backup integrity check failed"
    exit 1
fi

# Create test database
log_message "Creating test database..."
mysql -e "DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB;"

# Restore backup
log_message "Restoring backup..."
gunzip < "$LATEST_BACKUP" | mysql "$TEST_DB"

if [ $? -eq 0 ]; then
    log_message "✓ Restore successful"
else
    log_message "✗ Restore failed"
    exit 1
fi

# Verify tables
TABLE_COUNT=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TEST_DB'")
log_message "Tables restored: $TABLE_COUNT"

if [ $TABLE_COUNT -gt 50 ]; then
    log_message "✓ Table count looks good"
else
    log_message "✗ Table count too low"
    exit 1
fi

# Cleanup
log_message "Cleaning up test database..."
mysql -e "DROP DATABASE $TEST_DB;"

log_message "=== Restore test completed successfully ==="
exit 0
