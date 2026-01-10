#!/bin/bash

###############################################################################
# Add New Partition Script
# Adds a new monthly partition to the messages table
###############################################################################

if [ $# -lt 1 ]; then
    echo "Usage: $0 <YYYYMM>"
    echo "Example: $0 202701 (for January 2027)"
    exit 1
fi

PARTITION_MONTH=$1
NEXT_MONTH=$(date -d "${PARTITION_MONTH}01 +1 month" +%Y%m)

echo "Adding partition for $PARTITION_MONTH"
echo "Partition will contain data up to $NEXT_MONTH"

# Add partition
mysql "${DB_DATABASE:-gekychat}" <<EOF
ALTER TABLE messages 
REORGANIZE PARTITION p_future INTO (
    PARTITION p${PARTITION_MONTH} VALUES LESS THAN (${NEXT_MONTH}),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
EOF

if [ $? -eq 0 ]; then
    echo "✓ Partition p${PARTITION_MONTH} added successfully"
    
    # Show current partitions
    echo ""
    echo "Current partitions:"
    mysql "${DB_DATABASE:-gekychat}" -e "
        SELECT 
            PARTITION_NAME,
            PARTITION_EXPRESSION,
            PARTITION_DESCRIPTION,
            TABLE_ROWS
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_NAME = 'messages'
        ORDER BY PARTITION_ORDINAL_POSITION;
    "
else
    echo "✗ Failed to add partition"
    exit 1
fi
