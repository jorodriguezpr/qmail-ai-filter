#!/bin/bash
#
# QMAIL AI Filter - Custom Installation Script
# 
# This script customizes the installation for a specific Kloxo NG user
# 
# Usage: bash install-custom.sh <username> [install_dir]
# 
# Example: 
#   bash install-custom.sh yourusername
#   bash install-custom.sh yourusername /home/yourusername/qmail-ai-filter
#

set -e

# Check arguments
if [ $# -lt 1 ]; then
    echo "Usage: bash $0 <username> [install_dir]"
    echo ""
    echo "Examples:"
    echo "  bash $0 yourusername"
    echo "  bash $0 yourusername /home/yourusername/qmail-ai-filter"
    echo ""
    exit 1
fi

# Variables
INSTALL_USER=$1
INSTALL_DIR=${2:-/home/$INSTALL_USER/qmail-ai-filter}
WRAPPER_SCRIPT="/usr/local/bin/qmail-ai-filter-${INSTALL_USER}"

echo "========================================="
echo "QMAIL AI Filter - Custom Installation"
echo "========================================="
echo ""
echo "Installation User: $INSTALL_USER"
echo "Installation Dir:  $INSTALL_DIR"
echo "Wrapper Script:    $WRAPPER_SCRIPT"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "ERROR: This script must be run as root (or with sudo)"
   exit 1
fi

# Check if user exists
if ! id "$INSTALL_USER" &>/dev/null; then
    echo "ERROR: User '$INSTALL_USER' does not exist"
    echo "Create the user first: useradd $INSTALL_USER"
    exit 1
fi

# Get user's home directory
USER_HOME=$(eval echo "~$INSTALL_USER")
echo "[1/9] User home directory: $USER_HOME"
echo ""

# Get user ID and group ID
USER_UID=$(id -u "$INSTALL_USER")
USER_GID=$(id -g "$INSTALL_USER")

# Step 1: Create installation directory
echo "[2/9] Creating installation directories..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/queue/{pending,processing,completed,failed}"
mkdir -p "$INSTALL_DIR/logs"
echo "✓ Directories created"
echo ""

# Step 2: Copy project files if not already there
if [ ! -f "$INSTALL_DIR/bin/qmail-ai-filter.php" ]; then
    echo "[3/9] Copying project files..."
    # Assume script is run from project root
    cp -r src/ "$INSTALL_DIR/"
    cp -r bin/ "$INSTALL_DIR/"
    cp -r config/ "$INSTALL_DIR/"
    cp README.md "$INSTALL_DIR/" 2>/dev/null || true
    cp QUICK_REFERENCE.md "$INSTALL_DIR/" 2>/dev/null || true
    echo "✓ Files copied"
else
    echo "[3/9] Project files already exist (skipping copy)"
fi
echo ""

# Step 3: Set ownership and permissions
echo "[4/9] Setting file ownership and permissions..."
chown -R "$INSTALL_USER:$INSTALL_USER" "$INSTALL_DIR"
chmod 755 "$INSTALL_DIR"
chmod 755 "$INSTALL_DIR/bin"/*.php
chmod 755 "$INSTALL_DIR/queue"
chmod 755 "$INSTALL_DIR/logs"
chmod 600 "$INSTALL_DIR/.env" 2>/dev/null || true

# Allow mail group read access
chmod g+rx "$INSTALL_DIR/queue" 2>/dev/null || true
chmod g+rx "$INSTALL_DIR/logs" 2>/dev/null || true

echo "✓ Ownership and permissions set"
echo ""

# Step 4: Create .env file if it doesn't exist
echo "[5/9] Creating configuration file..."
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cat > "$INSTALL_DIR/.env" << EOF
# QMAIL AI Filter Configuration for $INSTALL_USER
# Generated: $(date)

# Installation & User Configuration
INSTALL_USER=$INSTALL_USER
INSTALL_DIR=$INSTALL_DIR

# AI Provider Configuration
AI_PROVIDER=openai
OPENAI_API_KEY=sk-YOUR-API-KEY-HERE
OPENAI_MODEL=gpt-3.5-turbo

# Spam Detection
SPAM_THRESHOLD=0.7
MAX_EMAIL_SIZE=10485760
API_TIMEOUT=30

# QMAIL Settings
QMAIL_MAILBOX_ROOT=/home/mail
CREATE_SPAM_FOLDER=true
PRESERVE_ORIGINAL=false

# Queue Processing
QUEUE_DIR=$INSTALL_DIR/queue
MAX_CONCURRENT_REQUESTS=5
MAX_RETRIES=3
RETRY_DELAY=300

# Logging
LOG_DIR=$INSTALL_DIR/logs
LOG_LEVEL=info
DEBUG=false
DRY_RUN=false
EOF

    chmod 600 "$INSTALL_DIR/.env"
    chown "$INSTALL_USER:$INSTALL_USER" "$INSTALL_DIR/.env"
    echo "✓ Configuration file created: $INSTALL_DIR/.env"
else
    echo "✓ Configuration file already exists"
fi
echo ""

# Step 5: Create wrapper script
echo "[6/9] Creating QMAIL integration wrapper..."
cat > "$WRAPPER_SCRIPT" << 'EOF'
#!/bin/bash
# QMAIL AI Filter Wrapper for INSTALL_USER
# Load environment and run filter

INSTALL_DIR=INSTALL_DIR_PLACEHOLDER

if [ -f "$INSTALL_DIR/.env" ]; then
    export $(cat "$INSTALL_DIR/.env" | grep -v '^#' | xargs)
fi

exec /usr/bin/php "$INSTALL_DIR/bin/qmail-ai-filter.php" "$@"
EOF

# Replace placeholder
sed -i "s|INSTALL_DIR_PLACEHOLDER|$INSTALL_DIR|g" "$WRAPPER_SCRIPT"

chmod 755 "$WRAPPER_SCRIPT"
echo "✓ Wrapper script created: $WRAPPER_SCRIPT"
echo ""

# Step 6: Create management symlink
echo "[7/9] Creating management command symlink..."
ln -sf "$INSTALL_DIR/manage.php" "/usr/local/bin/qmail-manage-${INSTALL_USER}" 2>/dev/null || true
chmod 755 "/usr/local/bin/qmail-manage-${INSTALL_USER}" 2>/dev/null || true
echo "✓ Management command: /usr/local/bin/qmail-manage-${INSTALL_USER}"
echo ""

# Step 7: Test configuration
echo "[8/9] Testing configuration..."
php "$INSTALL_DIR/manage.php" config:check 2>/dev/null | head -20

echo ""
echo "[9/9] Installation verification..."
echo "✓ Installation directory: $INSTALL_DIR"
echo "✓ QMAIL wrapper: $WRAPPER_SCRIPT"
echo "✓ Configuration: $INSTALL_DIR/.env"
echo "✓ Logs: $INSTALL_DIR/logs/"
echo "✓ Queue: $INSTALL_DIR/queue/"
echo ""

echo "========================================="
echo "Installation Complete!"
echo "========================================="
echo ""
echo "NEXT STEPS:"
echo ""
echo "1. Configure API credentials:"
echo "   nano $INSTALL_DIR/.env"
echo "   (Edit OPENAI_API_KEY or CLAUDE_API_KEY)"
echo ""
echo "2. Test the installation:"
echo "   php $INSTALL_DIR/manage.php test:api"
echo ""
echo "3. Integrate with QMAIL (for user: $INSTALL_USER)"
echo "   Edit /home/mail/domain.com/$INSTALL_USER/.qmail:"
echo ""
echo "   | $WRAPPER_SCRIPT"
echo "   ./Maildir/"
echo ""
echo "4. Setup cron job (as root):"
echo "   sudo crontab -e"
echo "   Add: */5 * * * * /usr/bin/php $INSTALL_DIR/bin/queue-processor.php"
echo ""
echo "5. Monitor logs:"
echo "   tail -f $INSTALL_DIR/logs/qmail-ai-filter.log"
echo ""
echo "6. Quick commands:"
echo "   php $INSTALL_DIR/manage.php queue:status"
echo "   php $INSTALL_DIR/manage.php config:check"
echo "   php $INSTALL_DIR/manage.php logs:tail"
echo ""
