#!/bin/bash
#
# QMAIL AI Filter - Quick Start Installation Script
# Run this script to set up the plugin on your Kloxo NG server
#
# Usage: bash install.sh
#

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
INSTALL_DIR="${QMAIL_FILTER_INSTALL_DIR:-/home/admin/qmail-ai-filter}"

echo "========================================="
echo "QMAIL AI Filter - Installation"
echo "========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "This script must be run as root (or with sudo)"
   exit 1
fi

# Check PHP version
echo "[1/8] Checking PHP installation..."
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed"
    exit 1
fi

PHP_VERSION=$(php -v | head -1)
echo "✓ Found: $PHP_VERSION"
echo ""

# Check curl extension
echo "[2/8] Checking required PHP extensions..."
php -m | grep -q curl || {
    echo "ERROR: PHP curl extension is not installed"
    echo "Install with: apt-get install php-curl (Debian/Ubuntu) or yum install php-curl (CentOS)"
    exit 1
}
echo "✓ curl extension is installed"
echo ""

# Create installation directory
echo "[3/8] Creating installation directory..."
if [ ! -d "$INSTALL_DIR" ]; then
    mkdir -p "$INSTALL_DIR"
    echo "✓ Created: $INSTALL_DIR"
else
    echo "✓ Directory exists: $INSTALL_DIR"
fi
echo ""

# Copy files
echo "[4/8] Copying plugin files..."
cp -r "$SCRIPT_DIR"/* "$INSTALL_DIR/" 2>/dev/null || true
echo "✓ Files copied to: $INSTALL_DIR"
echo ""

# Create necessary directories
echo "[5/8] Creating working directories..."
mkdir -p "$INSTALL_DIR/queue/{pending,processing,completed,failed}"
mkdir -p "$INSTALL_DIR/logs"
chmod 755 "$INSTALL_DIR/queue" "$INSTALL_DIR/logs"
echo "✓ Working directories created"
echo ""

# Set permissions
echo "[6/8] Setting file permissions..."
chmod 755 "$INSTALL_DIR/bin/qmail-ai-filter.php"
chmod 755 "$INSTALL_DIR/bin/queue-processor.php"
chown -R mail:mail "$INSTALL_DIR/queue" "$INSTALL_DIR/logs" 2>/dev/null || true
echo "✓ Permissions set correctly"
echo ""

# Create wrapper script
echo "[7/8] Creating QMAIL integration script..."
cat > /usr/local/bin/qmail-ai-filter << 'EOF'
#!/bin/bash
# QMAIL AI Filter Wrapper
# Load environment variables and run filter

if [ -f /home/admin/qmail-ai-filter/.env ]; then
    export $(cat /home/admin/qmail-ai-filter/.env | grep -v '^#' | xargs)
fi

exec /usr/bin/php /home/admin/qmail-ai-filter/bin/qmail-ai-filter.php "$@"
EOF

chmod 755 /usr/local/bin/qmail-ai-filter
echo "✓ QMAIL wrapper script created: /usr/local/bin/qmail-ai-filter"
echo ""

# Copy example .env
echo "[8/8] Configuring environment..."
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    chmod 600 "$INSTALL_DIR/.env"
    echo "✓ Created .env file"
    echo ""
    echo "⚠️  IMPORTANT: Edit the configuration file:"
    echo "    nano $INSTALL_DIR/.env"
    echo ""
    echo "    Required settings:"
    echo "    - AI_PROVIDER: Set to 'openai' or 'claude_anthropic'"
    echo "    - API_KEY: Add your OpenAI or Claude API key"
else
    echo "✓ .env file already exists (not overwritten)"
fi
echo ""

echo "========================================="
echo "Installation Complete!"
echo "========================================="
echo ""
echo "Next Steps:"
echo ""
echo "1. Configure API credentials:"
echo "   nano $INSTALL_DIR/.env"
echo ""
echo "2. Test the installation:"
echo "   php $INSTALL_DIR/bin/queue-processor.php --dry-run"
echo ""
echo "3. Add cron job for background processing:"
echo "   crontab -e"
echo "   Add: */5 * * * * /usr/bin/php $INSTALL_DIR/bin/queue-processor.php >> /var/log/qmail-ai-filter-queue.log 2>&1"
echo ""
echo "4. Integrate with QMAIL:"
echo "   Edit user's .qmail file:"
echo "   | /usr/local/bin/qmail-ai-filter"
echo "   ./Maildir/"
echo ""
echo "5. Monitor logs:"
echo "   tail -f $INSTALL_DIR/logs/qmail-ai-filter.log"
echo ""
echo "For more information, see: $INSTALL_DIR/README.md"
echo ""
