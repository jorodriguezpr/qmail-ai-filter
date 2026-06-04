# QMAIL AI Filter - Custom Installation Guide

This guide explains how to install and configure the QMAIL AI Filter for any Kloxo NG user directory, not just the default `/home/admin`.

## Installation for Specific User (yourusername Example)

### Step 1: Download Project to User's Home

```bash
# Login as yourusername or use sudo
sudo -u yourusername bash

# Or directly with sudo
sudo bash -c 'cd /home/yourusername && git clone <repo-url> qmail-ai-filter'
# Or extract archive to /home/yourusername/qmail-ai-filter
```

### Step 2: Create `.env` Configuration File

```bash
# Create .env file for yourusername user
sudo tee /home/yourusername/qmail-ai-filter/.env > /dev/null << 'EOF'
# Installation Configuration
INSTALL_USER=yourusername
INSTALL_DIR=/home/yourusername/qmail-ai-filter

# AI Provider
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
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
QUEUE_DIR=/home/yourusername/qmail-ai-filter/queue
MAX_CONCURRENT_REQUESTS=5
MAX_RETRIES=3
RETRY_DELAY=300

# Logging
LOG_DIR=/home/yourusername/qmail-ai-filter/logs
LOG_LEVEL=info
DEBUG=false
DRY_RUN=false
EOF

# Set proper permissions on .env
sudo chmod 600 /home/yourusername/qmail-ai-filter/.env
```

### Step 3: Create Directories and Set Permissions

```bash
# Create necessary directories
sudo mkdir -p /home/yourusername/qmail-ai-filter/queue/{pending,processing,completed,failed}
sudo mkdir -p /home/yourusername/qmail-ai-filter/logs

# Set proper ownership (mail user needs access for queue operations)
sudo chown -R yourusername:yourusername /home/yourusername/qmail-ai-filter
sudo chmod 755 /home/yourusername/qmail-ai-filter
sudo chmod 755 /home/yourusername/qmail-ai-filter/bin/*.php
sudo chmod 755 /home/yourusername/qmail-ai-filter/queue
sudo chmod 755 /home/yourusername/qmail-ai-filter/logs

# Allow mail group to read queue/logs
sudo chmod g+rx /home/yourusername/qmail-ai-filter/queue
sudo chmod g+rx /home/yourusername/qmail-ai-filter/logs
```

### Step 4: Create QMAIL Integration Wrapper

```bash
# Create wrapper script with environment loaded
sudo tee /usr/local/bin/qmail-ai-filter-yourusername << 'EOF'
#!/bin/bash
# QMAIL AI Filter Wrapper for yourusername user
# Loads environment from yourusername's .env and runs filter

if [ -f /home/yourusername/qmail-ai-filter/.env ]; then
    export $(cat /home/yourusername/qmail-ai-filter/.env | grep -v '^#' | xargs)
fi

exec /usr/bin/php /home/yourusername/qmail-ai-filter/bin/qmail-ai-filter.php "$@"
EOF

# Make executable
sudo chmod +x /usr/local/bin/qmail-ai-filter-yourusername
```

### Step 5: Configure QMAIL Filter Hook

You have two options:

#### Option A: Per-User Filtering (Recommended for specific user)

In Kloxo NG, edit the `.qmail` file for yourusername user:

```bash
# Via command line
cat > /home/mail/domain.com/yourusername/.qmail << 'EOF'
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
EOF

# Set permissions
chmod 644 /home/mail/domain.com/yourusername/.qmail
```

#### Option B: Domain-Wide Filtering

Edit domain's `.qmail-default`:

```bash
cat > /home/mail/domain.com/.qmail-default << 'EOF'
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
EOF

chmod 644 /home/mail/domain.com/.qmail-default
```

### Step 6: Setup Cron Job for Queue Processing

Add to root's crontab (cron processes mail queue):

```bash
sudo crontab -e
```

Add these lines:

```bash
# Process QMAIL AI Filter queue every 5 minutes for yourusername
*/5 * * * * /usr/bin/php /home/yourusername/qmail-ai-filter/bin/queue-processor.php >> /var/log/qmail-ai-filter-yourusername.log 2>&1

# Clean up old queue items daily at 2 AM
0 2 * * * /usr/bin/php -r "require_once '/home/yourusername/qmail-ai-filter/src/Autoloader.php'; Autoloader::register(); \$q = new \QmailAiFilter\Queue\QueueManager('/home/yourusername/qmail-ai-filter/queue'); \$q->cleanup();" >> /var/log/qmail-ai-filter-yourusername.log 2>&1
```

### Step 7: Test Configuration

```bash
# Test API connection
php /home/yourusername/qmail-ai-filter/manage.php test:api

# Validate configuration
php /home/yourusername/qmail-ai-filter/manage.php config:check

# Check queue status
php /home/yourusername/qmail-ai-filter/manage.php queue:status

# View logs
tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log
```

## Environment Variable Reference

These variables should be set in the `.env` file:

### Installation Settings

| Variable | Default | Example | Notes |
|----------|---------|---------|-------|
| `INSTALL_USER` | `yourusername` | `yourusername` | Username who owns the installation |
| `INSTALL_DIR` | `/home/{user}/qmail-ai-filter` | `/home/yourusername/qmail-ai-filter` | Installation directory |

### AI Provider Settings

| Variable | Default | Example | Notes |
|----------|---------|---------|-------|
| `AI_PROVIDER` | `openai` | `openai`, `claude_anthropic`, or `ollama-cloud` | Which AI to use |
| `OPENAI_API_KEY` | (required) | `sk-proj-...` | OpenAI API key |
| `OPENAI_MODEL` | `gpt-3.5-turbo` | `gpt-4` | OpenAI model |
| `CLAUDE_API_KEY` | (required) | `sk-ant-...` | Claude API key |
| `CLAUDE_MODEL` | `claude-3-haiku-20240307` | `claude-3-sonnet-20240229` | Claude model |
| `OLLAMA_CLOUD_API_KEY` | (required) | `oc_...` | Ollama Cloud API key |
| `OLLAMA_CLOUD_MODEL` | `glm-5.1` | `glm-5.1` | Ollama Cloud model |

### Queue Processing

| Variable | Default | Example | Notes |
|----------|---------|---------|-------|
| `QUEUE_DIR` | `{INSTALL_DIR}/queue` | `/home/yourusername/qmail-ai-filter/queue` | Queue storage |
| `MAX_CONCURRENT_REQUESTS` | `5` | `10` | Parallel API requests |
| `MAX_RETRIES` | `3` | `5` | Retry failed emails |
| `RETRY_DELAY` | `300` | `600` | Seconds between retries |

### QMAIL Settings

| Variable | Default | Example | Notes |
|----------|---------|---------|-------|
| `QMAIL_MAILBOX_ROOT` | `/home/mail` | `/home/mail` | QMAIL mailbox root |
| `CREATE_SPAM_FOLDER` | `true` | `true` | Auto-create .Spam |
| `PRESERVE_ORIGINAL` | `false` | `true` | Copy vs move |

### Logging

| Variable | Default | Example | Notes |
|----------|---------|---------|-------|
| `LOG_DIR` | `{INSTALL_DIR}/logs` | `/home/yourusername/qmail-ai-filter/logs` | Log directory |
| `LOG_LEVEL` | `info` | `debug` | Logging level |
| `DEBUG` | `false` | `true` | Debug mode |
| `DRY_RUN` | `false` | `true` | Test without moving |

## Multi-User Setup

If you have multiple users (e.g., `admin`, `yourusername`), install separately for each:

```bash
# For admin
/home/admin/qmail-ai-filter/
/usr/local/bin/qmail-ai-filter-admin

# For yourusername
/home/yourusername/qmail-ai-filter/
/usr/local/bin/qmail-ai-filter-yourusername
```

Each has its own:
- `.env` configuration
- Queue storage
- Log files
- Cron job
- Wrapper script

## Template Configuration Files

### For yourusername User

**.env file:**
```ini
INSTALL_USER=yourusername
INSTALL_DIR=/home/yourusername/qmail-ai-filter
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
OLLAMA_CLOUD_API_KEY=your-ollama-cloud-key
OLLAMA_CLOUD_MODEL=glm-5.1
SPAM_THRESHOLD=0.7
QMAIL_MAILBOX_ROOT=/home/mail
QUEUE_DIR=/home/yourusername/qmail-ai-filter/queue
LOG_DIR=/home/yourusername/qmail-ai-filter/logs
LOG_LEVEL=info
```

**.qmail file for yourusername user:**
```
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
```

## Troubleshooting Custom Installation

### "Command not found" Error

```bash
# Verify wrapper script exists
ls -la /usr/local/bin/qmail-ai-filter-yourusername

# Make sure it's executable
sudo chmod +x /usr/local/bin/qmail-ai-filter-yourusername

# Check .env path inside wrapper
grep INSTALL_DIR /usr/local/bin/qmail-ai-filter-yourusername
```

### Permission Denied on .Spam Folder

```bash
# Verify mailbox permissions
ls -la /home/mail/domain.com/yourusername/

# Fix permissions
sudo chown -R mail:mail /home/mail/domain.com/yourusername/
sudo chmod 700 /home/mail/domain.com/yourusername/Maildir/
```

### Queue Not Processing

```bash
# Check cron job
sudo crontab -l | grep qmail-ai-filter

# Check cron logs
sudo tail -50 /var/log/cron

# Check log file
tail -50 /var/log/qmail-ai-filter-yourusername.log
```

### API Connection Fails

```bash
# Verify .env is being loaded
cat /home/yourusername/qmail-ai-filter/.env | grep API_KEY

# Test API manually
OPENAI_API_KEY=$(grep OPENAI_API_KEY /home/yourusername/qmail-ai-filter/.env | cut -d= -f2) \
curl -X POST https://api.openai.com/v1/chat/completions \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-3.5-turbo","messages":[{"role":"user","content":"test"}]}'
```

## Configuration Best Practices

1. **Security**: Keep `.env` readable only by owner
   ```bash
   chmod 600 /home/yourusername/qmail-ai-filter/.env
   ```

2. **Isolation**: Use separate API keys per installation
   ```
   admin: API key 1
   yourusername: API key 2
   ```

3. **Monitoring**: Check logs regularly
   ```bash
   tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log
   ```

4. **Testing**: Use DRY_RUN before production
   ```bash
   echo "DRY_RUN=true" >> /home/yourusername/qmail-ai-filter/.env
   ```

5. **Backups**: Store `.env` securely
   ```bash
   sudo cp /home/yourusername/qmail-ai-filter/.env /root/backup/
   chmod 600 /root/backup/.env
   ```

## Migration from Default Installation

If you're moving from `/home/admin` to `/home/yourusername`:

```bash
# 1. Copy configuration
sudo cp /home/admin/qmail-ai-filter/.env /home/yourusername/qmail-ai-filter/.env

# 2. Update user reference
sudo sed -i 's/INSTALL_USER=admin/INSTALL_USER=yourusername/g' /home/yourusername/qmail-ai-filter/.env
sudo sed -i 's|INSTALL_DIR=/home/admin|INSTALL_DIR=/home/yourusername|g' /home/yourusername/qmail-ai-filter/.env

# 3. Fix ownership
sudo chown -R yourusername:yourusername /home/yourusername/qmail-ai-filter

# 4. Update QMAIL hook
# Edit /home/mail/domain.com/yourusername/.qmail to use qmail-ai-filter-yourusername

# 5. Update cron job
# Change cron entry to use /home/yourusername path

# 6. Test
php /home/yourusername/qmail-ai-filter/manage.php config:check
```

## Support

For issues specific to your configuration:

1. Check your `.env` file
2. Run `manage.php config:check`
3. Review logs in `{INSTALL_DIR}/logs/`
4. See main `TROUBLESHOOTING.md` for common issues
