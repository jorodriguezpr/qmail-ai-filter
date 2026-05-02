#!/bin/bash

# QMAIL AI Filter Wrapper - Synchronous Analysis with Header Tagging
# This script performs immediate AI spam analysis and only delivers HAM emails
#
# IMPORTANT: Replace ACCOUNT_NAME with your actual account name (e.g., yourusername)

# Configuration - CUSTOMIZE THESE
ACCOUNT_NAME="yourusername"  # REPLACE THIS WITH YOUR USERNAME
INSTALL_DIR="/home/${ACCOUNT_NAME}/qmail-ai-filter"
LOG_FILE="${INSTALL_DIR}/logs/wrapper-sync.log"
SPAM_QUARANTINE="${INSTALL_DIR}/quarantine"

# Load environment
if [ -f "${INSTALL_DIR}/.env" ]; then
    source "${INSTALL_DIR}/.env"
fi

# Get current delivery directory
DELIVERY_PATH="$PWD"

# Create necessary directories
mkdir -p "${INSTALL_DIR}/logs"
mkdir -p "${SPAM_QUARANTINE}"
chmod 777 "${INSTALL_DIR}/logs"
chmod 777 "${SPAM_QUARANTINE}"

# Log start
echo "[$(date)] === NEW EMAIL (SYNC) ===" >> "${LOG_FILE}"
echo "[$(date)] PWD: ${PWD}" >> "${LOG_FILE}"
echo "[$(date)] User: $(whoami)" >> "${LOG_FILE}"

# Extract domain and user from path
EMAIL_ADDRESS=""

if [[ $DELIVERY_PATH =~ /home/lxadmin/mail/domains/([^/]+)/([^/]+)$ ]]; then
    DOMAIN="${BASH_REMATCH[1]}"
    USER="${BASH_REMATCH[2]}"
    EMAIL_ADDRESS="${USER}@${DOMAIN}"
    echo "[$(date)] Recipient: ${EMAIL_ADDRESS}" >> "${LOG_FILE}"
else
    echo "[$(date)] ERROR: Could not extract email from path!" >> "${LOG_FILE}"
    # Fallback: check argument
    if [ -n "$1" ]; then
        EMAIL_ADDRESS="$1"
        echo "[$(date)] Using email from argument: ${EMAIL_ADDRESS}" >> "${LOG_FILE}"
    fi
fi

# Read email and save to temp file
TEMP_EMAIL=$(mktemp)
cat > "${TEMP_EMAIL}"

echo "[$(date)] Email saved to: ${TEMP_EMAIL}" >> "${LOG_FILE}"
echo "[$(date)] Email size: $(wc -c < ${TEMP_EMAIL}) bytes" >> "${LOG_FILE}"

# Call synchronous AI filter
echo "[$(date)] Calling AI filter..." >> "${LOG_FILE}"

AI_RESULT=$(/usr/bin/php "${INSTALL_DIR}/bin/sync-filter.php" "${TEMP_EMAIL}" "${EMAIL_ADDRESS}" 2>&1)
PHP_EXIT=$?

echo "[$(date)] AI filter exit code: ${PHP_EXIT}" >> "${LOG_FILE}"
echo "[$(date)] AI result: ${AI_RESULT}" >> "${LOG_FILE}"

# Parse JSON result
IS_SPAM=$(echo "${AI_RESULT}" | grep -o '"is_spam":[^,}]*' | cut -d':' -f2 | tr -d ' ')
CONFIDENCE=$(echo "${AI_RESULT}" | grep -o '"confidence":[^,}]*' | cut -d':' -f2 | tr -d ' ')
REASON=$(echo "${AI_RESULT}" | grep -o '"reason":"[^"]*"' | cut -d':' -f2- | tr -d '"')

echo "[$(date)] Parsed - Is Spam: ${IS_SPAM}, Confidence: ${CONFIDENCE}" >> "${LOG_FILE}"

# Add X-AI-JRA-Spam-Class header to email
TEMP_EMAIL_MODIFIED=$(mktemp)

# Read email and inject header after the first line (after Return-Path or first header)
{
    # Read first line
    read -r FIRST_LINE
    echo "$FIRST_LINE"
    
    # Add our custom header
    if [ "$IS_SPAM" = "true" ]; then
        echo "X-AI-JRA-Spam-Class: true"
        echo "X-AI-JRA-Spam-Confidence: ${CONFIDENCE}"
        echo "X-AI-JRA-Spam-Reason: ${REASON}"
    else
        echo "X-AI-JRA-Spam-Class: false"
        echo "X-AI-JRA-Spam-Confidence: ${CONFIDENCE}"
    fi
    
    # Output rest of email
    cat
} < "${TEMP_EMAIL}" > "${TEMP_EMAIL_MODIFIED}"

# Decide: Deliver or Quarantine
if [ "$IS_SPAM" = "true" ]; then
    # SPAM detected - quarantine instead of delivering
    echo "[$(date)] SPAM DETECTED - Quarantining email" >> "${LOG_FILE}"
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    QUARANTINE_FILE="${SPAM_QUARANTINE}/spam_${TIMESTAMP}_${RANDOM}.eml"
    
    cp "${TEMP_EMAIL_MODIFIED}" "${QUARANTINE_FILE}"
    echo "[$(date)] Quarantined to: ${QUARANTINE_FILE}" >> "${LOG_FILE}"
    
    EXITCODE=0  # Still report success to QMAIL (email was handled)
else
    # HAM - deliver to Maildir
    echo "[$(date)] HAM - Delivering to Maildir" >> "${LOG_FILE}"
    
    TIMESTAMP=$(date +%s)
    HOSTNAME=$(hostname)
    UNIQUE_ID="${TIMESTAMP}.${RANDOM}.${HOSTNAME}"
    
    mkdir -p ./Maildir/tmp ./Maildir/new ./Maildir/cur
    
    cp "${TEMP_EMAIL_MODIFIED}" "./Maildir/tmp/${UNIQUE_ID}"
    mv "./Maildir/tmp/${UNIQUE_ID}" "./Maildir/new/${UNIQUE_ID}"
    EXITCODE=$?
    
    # Fix ownership
    MAILDIR_OWNER=$(stat -c '%U:%G' ./Maildir 2>/dev/null)
    if [ -n "$MAILDIR_OWNER" ]; then
        chown "${MAILDIR_OWNER}" "./Maildir/new/${UNIQUE_ID}" 2>/dev/null
    fi
    
    echo "[$(date)] Delivered successfully" >> "${LOG_FILE}"
fi

# Cleanup
rm -f "${TEMP_EMAIL}" "${TEMP_EMAIL_MODIFIED}"

echo "[$(date)] Finished - Exit code: ${EXITCODE}" >> "${LOG_FILE}"
echo "[$(date)] === END EMAIL ===" >> "${LOG_FILE}"
echo "" >> "${LOG_FILE}"

exit ${EXITCODE}
