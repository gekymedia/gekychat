#!/bin/bash

###############################################################################
# Replication Health Check Script
###############################################################################

echo "=== MySQL Replication Status ==="

# Check slave status
SLAVE_STATUS=$(mysql -e "SHOW SLAVE STATUS\G")

if [ -z "$SLAVE_STATUS" ]; then
    echo "This server is not configured as a slave"
    exit 0
fi

# Extract key metrics
IO_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_IO_Running:" | awk '{print $2}')
SQL_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_SQL_Running:" | awk '{print $2}')
SECONDS_BEHIND=$(echo "$SLAVE_STATUS" | grep "Seconds_Behind_Master:" | awk '{print $2}')
LAST_ERROR=$(echo "$SLAVE_STATUS" | grep "Last_Error:" | cut -d: -f2-)

echo "IO Thread: $IO_RUNNING"
echo "SQL Thread: $SQL_RUNNING"
echo "Seconds Behind Master: $SECONDS_BEHIND"

if [ "$IO_RUNNING" == "Yes" ] && [ "$SQL_RUNNING" == "Yes" ]; then
    echo "✓ Replication is running"
    
    if [ "$SECONDS_BEHIND" != "NULL" ] && [ "$SECONDS_BEHIND" -gt 60 ]; then
        echo "⚠ WARNING: Replication lag is $SECONDS_BEHIND seconds"
        exit 1
    else
        echo "✓ Replication lag is acceptable"
    fi
else
    echo "✗ ERROR: Replication is not running"
    if [ ! -z "$LAST_ERROR" ]; then
        echo "Last Error: $LAST_ERROR"
    fi
    exit 1
fi

exit 0
