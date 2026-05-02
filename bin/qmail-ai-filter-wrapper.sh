#!/bin/bash
# QMAIL AI Filter Wrapper
# Load environment and run filter
# Version 2: With debug logging

INSTALL_DIR=/home/admin/qmail-ai-filter
LOG_FILE="/var/log/qmail-ai-filter-wrapper.log"

# Log function
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $@" >> "$LOG_FILE"
}

# Start logging
log "=== Wrapper called ==="
log "Arguments: $@"
log "Working directory: $(pwd)"
log "User: $(whoami)"

# Load environment from .env
if [ -f "$INSTALL_DIR/.env" ]; then
    log "Loading .env from: $INSTALL_DIR/.env"
    export $(cat "$INSTALL_DIR/.env" | grep -v '^#' | xargs) 2>>$LOG_FILE || log "Error loading .env"
else
    log "ERROR: .env file not found at $INSTALL_DIR/.env"
fi

# Verify PHP exists
if [ ! -f "/usr/bin/php" ]; then
    log "ERROR: PHP not found at /usr/bin/php"
    exit 1
fi

log "Executing PHP script"

# Execute PHP and log any errors
/usr/bin/php "$INSTALL_DIR/bin/qmail-ai-filter.php" "$@" 2>&1 | tee -a "$LOG_FILE"
EXIT_CODE=$?

log "PHP execution completed with exit code: $EXIT_CODE"
log "=== End wrapper ==="

exit $EXIT_CODE
