#!/bin/bash

###############################################################################
# MySQL Replication Setup Script
# Sets up master-slave replication
###############################################################################

if [ $# -lt 1 ]; then
    echo "Usage: $0 <slave|master>"
    exit 1
fi

MODE=$1

if [ "$MODE" == "master" ]; then
    echo "=== Configuring Master Server ==="
    
    # Create replication user
    mysql -e "CREATE USER IF NOT EXISTS 'replication'@'%' IDENTIFIED BY '${REPLICATION_PASSWORD}';"
    mysql -e "GRANT REPLICATION SLAVE ON *.* TO 'replication'@'%';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Show master status
    echo "Master status:"
    mysql -e "SHOW MASTER STATUS\G"
    
    echo "Master configured. Note the File and Position values above."
    
elif [ "$MODE" == "slave" ]; then
    echo "=== Configuring Slave Server ==="
    
    read -p "Enter master host: " MASTER_HOST
    read -p "Enter master log file: " MASTER_LOG_FILE
    read -p "Enter master log position: " MASTER_LOG_POS
    
    # Stop slave if running
    mysql -e "STOP SLAVE;"
    
    # Configure slave
    mysql -e "CHANGE MASTER TO
        MASTER_HOST='$MASTER_HOST',
        MASTER_USER='replication',
        MASTER_PASSWORD='${REPLICATION_PASSWORD}',
        MASTER_LOG_FILE='$MASTER_LOG_FILE',
        MASTER_LOG_POS=$MASTER_LOG_POS;"
    
    # Start slave
    mysql -e "START SLAVE;"
    
    # Show slave status
    echo "Slave status:"
    mysql -e "SHOW SLAVE STATUS\G"
    
    echo "Slave configured."
else
    echo "Invalid mode. Use 'master' or 'slave'"
    exit 1
fi
