#!/bin/bash

# QMAIL AI Filter Wrapper - Maildrop compatible version
# This script queues emails for AI processing and delivers via maildrop

# Configuration - REPLACE WITH YOUR VALUES
INSTALL_DIR="/home/yourusername/qmail-ai-filter"
LOG_FILE="/var/log/qmail-ai-filter-wrapper.log"
MAILDROP_RC="/home/lxadmin/mail/domains/yourdomain.com/yourusername/.maildroprc"

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

# Deliver via maildrop (respects existing mail rules)
/var/qmail/bin/preline maildrop "${MAILDROP_RC}" < "${TEMP_EMAIL}"
EXITCODE=$?

# Cleanup
rm -f "${TEMP_EMAIL}"

# Log result
echo "[$(date)] Email delivered via maildrop for: ${EMAIL_ADDRESS}, exit code: ${EXITCODE}" >> "${LOG_FILE}"

# Exit with maildrop's exit code
exit ${EXITCODE}
