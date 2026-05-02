#!/bin/bash

# QMAIL AI Filter Wrapper - Async Generic Version
# This script queues emails for AI processing and delivers them immediately
# Automatically detects email address from the delivery path

# Configuration - REPLACE WITH YOUR VALUES
INSTALL_DIR="/home/yourusername/qmail-ai-filter"
LOG_FILE="/var/log/qmail-ai-filter-wrapper.log"

# Load environment
if [ -f "${INSTALL_DIR}/.env" ]; then
    source "${INSTALL_DIR}/.env"
fi

# Get current delivery directory
DELIVERY_PATH="$PWD"

# Extract domain and user from path
EMAIL_ADDRESS=""
MAILDROP_RC=""

if [[ $DELIVERY_PATH =~ /home/lxadmin/mail/domains/([^/]+)/([^/]+)$ ]]; then
    DOMAIN="${BASH_REMATCH[1]}"
    USER="${BASH_REMATCH[2]}"
    EMAIL_ADDRESS="${USER}@${DOMAIN}"
    MAILDROP_RC="${DELIVERY_PATH}/.maildroprc"
fi

# Fallback: check if email passed as argument
if [ -z "$EMAIL_ADDRESS" ] && [ -n "$1" ]; then
    EMAIL_ADDRESS="$1"
fi

# Log
echo "[$(date)] Processing email for: ${EMAIL_ADDRESS} in ${DELIVERY_PATH}" >> "${LOG_FILE}"

# Read email and save to temp file
TEMP_EMAIL=$(mktemp)
cat > "${TEMP_EMAIL}"

# Queue for AI processing (async, don't wait)
if [ -n "$EMAIL_ADDRESS" ]; then
    /usr/bin/php "${INSTALL_DIR}/bin/qmail-ai-filter.php" "${EMAIL_ADDRESS}" < "${TEMP_EMAIL}" >> "${LOG_FILE}" 2>&1
fi

# Deliver via maildrop if .maildroprc exists
if [ -f "$MAILDROP_RC" ]; then
    echo "[$(date)] Delivering via maildrop: ${MAILDROP_RC}" >> "${LOG_FILE}"
    /var/qmail/bin/preline maildrop "${MAILDROP_RC}" < "${TEMP_EMAIL}"
    EXITCODE=$?
else
    # Fallback: direct Maildir delivery if no maildrop
    echo "[$(date)] No maildrop found, delivering to Maildir directly" >> "${LOG_FILE}"
    
    TIMESTAMP=$(date +%s)
    HOSTNAME=$(hostname)
    UNIQUE_ID="${TIMESTAMP}.${RANDOM}.${HOSTNAME}"
    
    mkdir -p ./Maildir/tmp ./Maildir/new ./Maildir/cur
    cp "${TEMP_EMAIL}" "./Maildir/tmp/${UNIQUE_ID}"
    mv "./Maildir/tmp/${UNIQUE_ID}" "./Maildir/new/${UNIQUE_ID}"
    EXITCODE=$?
fi

# Cleanup
rm -f "${TEMP_EMAIL}"

# Log result
echo "[$(date)] Email delivered for: ${EMAIL_ADDRESS}, exit code: ${EXITCODE}" >> "${LOG_FILE}"

# Exit with delivery exit code
exit ${EXITCODE}
