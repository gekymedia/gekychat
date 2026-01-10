#!/bin/bash

###############################################################################
# Drop Old Partitions Script
# Removes partitions older than specified months (for data retention)
###############################################################################

RETENTION_MONTHS=${1:-12}
CUTOFF_DATE=$(date -d "$RETENTION_MONTHS months ago" +%Y%m)

echo "Dropping partitions older than $RETENTION_MONTHS months (before $CUTOFF_DATE)"

# Get list of old partitions
OLD_PARTITIONS=$(mysql "${DB_DATABASE:-gekychat}" -N -e "
    SELECT PARTITION_NAME
    FROM INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_NAME = 'messages'
    AND PARTITION_NAME != 'p_future'
    AND CAST(SUBSTRING(PARTITION_NAME, 2) AS UNSIGNED) < $CUTOFF_DATE
    ORDER BY PARTITION_NAME;
")

if [ -z "$OLD_PARTITIONS" ]; then
    echo "No old partitions to drop"
    exit 0
fi

echo "Partitions to drop:"
echo "$OLD_PARTITIONS"
echo ""

read -p "Continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 0
fi

# Drop each partition
for PARTITION in $OLD_PARTITIONS; do
    echo "Dropping partition: $PARTITION"
    
    mysql "${DB_DATABASE:-gekychat}" -e "
        ALTER TABLE messages DROP PARTITION $PARTITION;
    "
    
    if [ $? -eq 0 ]; then
        echo "✓ Dropped $PARTITION"
    else
        echo "✗ Failed to drop $PARTITION"
    fi
done

echo ""
echo "Partition cleanup completed"
