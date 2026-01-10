#!/bin/bash

###############################################################################
# GekyChat S3 Sync Script
# 
# This script syncs local backups to AWS S3 for offsite storage
# 
# Prerequisites:
# - AWS CLI installed and configured
# - S3 bucket created with appropriate permissions
# 
# Usage: ./sync_to_s3.sh
# Cron: 0 4 * * * /path/to/sync_to_s3.sh
###############################################################################

# Configuration
LOCAL_BACKUP_DIR="/backups"
S3_BUCKET="${AWS_S3_BUCKET:-s3://gekychat-backups}"
AWS_REGION="${AWS_REGION:-us-east-1}"

# Log file
LOG_FILE="/var/log/s3_sync.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=== Starting S3 sync ==="

# Check if AWS CLI is available
if ! command -v aws &> /dev/null; then
    log_message "ERROR: AWS CLI not found. Please install it first."
    exit 1
fi

# Check if AWS credentials are configured
if ! aws sts get-caller-identity &> /dev/null; then
    log_message "ERROR: AWS credentials not configured"
    exit 1
fi

# Sync MySQL backups to S3
log_message "Syncing MySQL backups to S3..."
aws s3 sync \
    "$LOCAL_BACKUP_DIR/mysql" \
    "$S3_BUCKET/mysql" \
    --storage-class STANDARD_IA \
    --region "$AWS_REGION" \
    --delete

if [ $? -eq 0 ]; then
    log_message "MySQL backups synced successfully"
else
    log_message "ERROR: MySQL backup sync failed"
    exit 1
fi

# Sync storage backups to S3
log_message "Syncing storage backups to S3..."
aws s3 sync \
    "$LOCAL_BACKUP_DIR/storage" \
    "$S3_BUCKET/storage" \
    --storage-class STANDARD_IA \
    --region "$AWS_REGION" \
    --delete

if [ $? -eq 0 ]; then
    log_message "Storage backups synced successfully"
else
    log_message "ERROR: Storage backup sync failed"
    exit 1
fi

# Verify sync by listing S3 contents
log_message "Verifying S3 sync..."
MYSQL_COUNT=$(aws s3 ls "$S3_BUCKET/mysql/" --recursive | wc -l)
STORAGE_COUNT=$(aws s3 ls "$S3_BUCKET/storage/" --recursive | wc -l)

log_message "S3 MySQL backups: $MYSQL_COUNT files"
log_message "S3 Storage backups: $STORAGE_COUNT directories"

# Calculate total S3 storage size
TOTAL_SIZE=$(aws s3 ls "$S3_BUCKET" --recursive --summarize | grep "Total Size" | awk '{print $3}')
TOTAL_SIZE_GB=$(echo "scale=2; $TOTAL_SIZE / 1024 / 1024 / 1024" | bc)
log_message "Total S3 storage: ${TOTAL_SIZE_GB} GB"

log_message "=== S3 sync completed ==="

# Optional: Send notification
# curl -X POST "https://your-notification-service.com/webhook" \
#     -H "Content-Type: application/json" \
#     -d "{\"message\": \"S3 sync completed: ${TOTAL_SIZE_GB} GB\"}"

exit 0
