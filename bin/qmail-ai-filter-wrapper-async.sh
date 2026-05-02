#!/bin/bash

# QMAIL AI Filter Wrapper - Generic async version
# This script queues emails for AI processing and delivers them immediately

# Configuration - REPLACE WITH YOUR VALUES
INSTALL_DIR="/home/yourusername/qmail-ai-filter"
LOG_FILE="/var/log/qmail-ai-filter-wrapper.log"

# Load environment
if [ -f "${INSTALL_DIR}/.env" ]; then
    source "${INSTALL_DIR}/.env"
fi

# Get email address from path or argument
EMAIL_ADDRESS=""
if [ -n "$1" ]; then
    EMAIL_ADDRESS="$1"
else
    # Extract from delivery path
    DELIVERY_PATH="$PWD"
    if [[ $DELIVERY_PATH =~ /home/lxadmin/mail/domains/([^/]+)/([^/]+)$ ]]; then
        DOMAIN="${BASH_REMATCH[1]}"
        USER="${BASH_REMATCH[2]}"
        EMAIL_ADDRESS="${USER}@${DOMAIN}"
    fi
fi

# Log
echo "[$(date)] Processing email for: ${EMAIL_ADDRESS}" >> "${LOG_FILE}"

# Read email and save to temp file
TEMP_EMAIL=$(mktemp)
cat > "${TEMP_EMAIL}"

# Queue for AI processing (async, don't wait)
/usr/bin/php "${INSTALL_DIR}/bin/qmail-ai-filter.php" "${EMAIL_ADDRESS}" < "${TEMP_EMAIL}" >> "${LOG_FILE}" 2>&1

# Deliver email to Maildir immediately
# Create unique filename for Maildir
TIMESTAMP=$(date +%s)
HOSTNAME=$(hostname)
UNIQUE_ID="${TIMESTAMP}.${RANDOM}.${HOSTNAME}"

# Ensure Maildir structure exists
mkdir -p ./Maildir/tmp ./Maildir/new ./Maildir/cur

# Deliver: write to tmp, then move to new (atomic delivery)
cp "${TEMP_EMAIL}" "./Maildir/tmp/${UNIQUE_ID}"
mv "./Maildir/tmp/${UNIQUE_ID}" "./Maildir/new/${UNIQUE_ID}"

# Cleanup
rm -f "${TEMP_EMAIL}"

# Log success
echo "[$(date)] Email delivered for: ${EMAIL_ADDRESS}" >> "${LOG_FILE}"

# Exit success so QMAIL knows delivery completed
exit 0
