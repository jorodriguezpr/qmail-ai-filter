# QMAIL AI Filter - Quick Setup Guide

**Open Source Project by Jose Rodriguez Arroyo** | 📧 jrpcone@gmail.com

Complete quick-start guide for deploying QMAIL AI spam filter on any Kloxo NG user account.

06-04-2026 New update
Now you can use a OLLAMA Cloud models with your API KEY.
1. Create your account in https://ollama.com/
2. Setup an API Key (Free models available)
3. Point your .env settings to Ollama Cloud.
** .env settings **
AI_PROVIDER=ollama-cloud
OLLAMA_CLOUD_API_KEY=your-ollama-cloud-api-key
OLLAMA_CLOUD_MODEL=glm-5.1


## 📋 Prerequisites

- Kloxo NG server with QMAIL
- SSH/root access
- API key from one of: GitHub Copilot, OpenAI, Google Gemini, Claude Anthropic, or Ollama Cloud
- Your Linux username (e.g., admin, yourusername, etc.)

## 🚀 5-Minute Quick Start

### Step 1: Upload Files

```bash
# SSH into your server
ssh root@your-server.com

# Navigate to your user's home directory
cd /home/yourusername

# Extract or clone the project
# Option A: Extract archive
tar -xzf qmail-ai-filter.tar.gz

# Option B: Clone from Git
git clone <repository-url> qmail-ai-filter
```

### Step 2: Run Custom Installer

```bash
cd /home/yourusername/qmail-ai-filter

# Run the custom installer (replace 'yourusername' with your actual username)
sudo bash install-custom.sh yourusername
```

The installer will:
- ✅ Create necessary directories (queue/, logs/)
- ✅ Set correct permissions
- ✅ Create wrapper script at `/usr/local/bin/qmail-ai-filter-yourusername`
- ✅ Create management tool symlink

### Step 3: Configure API Provider

```bash
# Edit the .env file
nano /home/yourusername/qmail-ai-filter/.env
```

**For GitHub Copilot (RECOMMENDED):**
```bash
AI_PROVIDER=github_copilot
GITHUB_COPILOT_API_KEY=ghp_your-key-here
GITHUB_COPILOT_MODEL=gpt-4o
```

**For OpenAI:**
```bash
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4o
```

**For Google Gemini:**
```bash
AI_PROVIDER=gemini
GEMINI_API_KEY=your-key-here
GEMINI_MODEL=gemini-pro
```

**For Claude Anthropic:**
```bash
AI_PROVIDER=claude_anthropic
CLAUDE_API_KEY=sk-ant-your-key-here
CLAUDE_MODEL=claude-3-haiku-20240307
```

**For Ollama Cloud (glm-5.1):**
```bash
AI_PROVIDER=ollama-cloud
OLLAMA_CLOUD_API_KEY=your-ollama-cloud-api-key
OLLAMA_CLOUD_MODEL=glm-5.1
```

Save and exit (Ctrl+O, Enter, Ctrl+X).

### Step 4: Test API Connection

```bash
/usr/bin/php /home/yourusername/qmail-ai-filter/manage.php test:api
```

Expected output:
```
✓ API Connection: Success
✓ AI Response: Valid
```

### Step 5: Integrate with QMAIL

Edit your `.qmail` file (or `.qmail-default` for catch-all):

```bash
# For specific user mailbox
nano /home/lxadmin/mail/domains/yourdomain.com/yourusername/.qmail
```

Add this line (replace yourusername):
```
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
```

Save and exit.

### Step 6: Setup Background Processing (Cron)

**Only needed if you chose Option B (Asynchronous Filtering):**

```bash
# Add to root's crontab
crontab -e
```

Add this line (replace yourusername):
```bash
*/5 * * * * /usr/bin/php /home/yourusername/qmail-ai-filter/bin/queue-processor.php >> /home/yourusername/qmail-ai-filter/logs/queue.log 2>&1
```

Save and exit.

**If you chose Option A (Synchronous), skip this step.**

### Step 7: Test Email Delivery

Send a test email to your mailbox and verify:

**For Synchronous Mode (Option A):**
```bash
# Check sync wrapper logs
tail -f /home/yourusername/qmail-ai-filter/logs/wrapper-sync.log

# Check quarantine folder for spam
ls -la /home/yourusername/qmail-ai-filter/quarantine/

# Verify header was added (check email headers in your email client)
# Look for: X-AI-JRA-Spam-Class: false
```

**For Asynchronous Mode (Option B):**
```bash
# Check wrapper logs
tail -f /var/log/qmail-ai-filter-wrapper-yourusername.log

# Check application logs  
tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log

# Check queue status
/usr/bin/php /home/yourusername/qmail-ai-filter/manage.php queue:status
```

## ✅ Verification Checklist

- [ ] Files extracted to `/home/yourusername/qmail-ai-filter/`
- [ ] Installer ran successfully
- [ ] `.env` file configured with API key
- [ ] API test passes
- [ ] `.qmail` file updated with chosen mode
- [ ] **If Sync Mode:** Wrapper script customized and executable
- [ ] **If Async Mode:** Cron job added
- [ ] Test email received

## 📁 Key Locations

| Item | Path |
|------|------|
| Installation | `/home/yourusername/qmail-ai-filter/` |
| Configuration | `/home/yourusername/qmail-ai-filter/.env` |
| Logs (Sync) | `/home/yourusername/qmail-ai-filter/logs/wrapper-sync.log` |
| Logs (Async) | `/home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log` |
| Queue (Async) | `/home/yourusername/qmail-ai-filter/queue/` |
| Quarantine (Sync) | `/home/yourusername/qmail-ai-filter/quarantine/` |
| Wrapper Script (Async) | `/usr/local/bin/qmail-ai-filter-yourusername` |
| Wrapper Script (Sync) | `/usr/local/bin/qmail-ai-filter-sync-yourusername` |
| Manager Tool | `/usr/local/bin/qmail-manage-yourusername` |

## 🔧 Common Commands

```bash
# Test API connection
/usr/bin/php /home/yourusername/qmail-ai-filter/manage.php test:api

# Check configuration
/usr/bin/php /home/yourusername/qmail-ai-filter/manage.php config:check

# View queue status
/usr/bin/php /home/yourusername/qmail-ai-filter/manage.php queue:status

# Monitor logs
tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log

# Process queue manually
/usr/bin/php /home/yourusername/qmail-ai-filter/bin/queue-processor.php
```

## 🆘 Troubleshooting

### Emails Not Being Filtered

**For Sync Mode:**
1. Check `.qmail` file has the correct pipe command
2. Verify sync wrapper script exists and is executable: `ls -l /usr/local/bin/qmail-ai-filter-sync-yourusername`
3. Check wrapper logs: `tail -f /home/yourusername/qmail-ai-filter/logs/wrapper-sync.log`
4. Verify ACCOUNT_NAME is set correctly in wrapper script

**For Async Mode:**
1. Check `.qmail` file has the pipe command and `./Maildir/` line
2. Verify wrapper script exists and is executable
3. Check logs for errors
4. Verify cron job is running: `crontab -l`

### Spam Not Being Detected

**For Sync Mode:**
- Check quarantine folder: `ls /home/yourusername/qmail-ai-filter/quarantine/`
- Verify wrapper-sync.log shows "Calling AI filter"
- Test sync filter directly: `php bin/sync-filter.php /path/to/email.eml user@domain.com`

**For Async Mode:**
- Check queue status: `php manage.php queue:status`
- Manually process queue: `php bin/queue-processor.php`
- Check if emails are being queued properly

### API Errors
- Verify API key is correct
- Check internet connectivity
- Ensure API provider is set correctly in `.env`

### Permission Denied
```bash
# Fix ownership
sudo chown -R yourusername:yourusername /home/yourusername/qmail-ai-filter

# Fix permissions
sudo chmod 755 /home/yourusername/qmail-ai-filter/bin/*.php
sudo chmod 777 /home/yourusername/qmail-ai-filter/queue
sudo chmod 777 /home/yourusername/qmail-ai-filter/logs
```

## 📚 Additional Documentation

- `INSTALLATION_CUSTOM.md` - Detailed installation guide
- `README.md` - Complete project documentation
- `TROUBLESHOOTING.md` - Common issues and solutions
- `ARCHITECTURE.md` - System architecture diagrams

## 🎯 Next Steps

Once setup is complete:

1. **Monitor for 24 hours** - Watch logs to ensure processing works
2. **Review spam detection** - Check the `.Spam` folder periodically
3. **Adjust threshold** - Modify `SPAM_THRESHOLD` in `.env` if needed (0.7 is default)
4. **Setup log rotation** - Prevent logs from growing too large

---

**You're all set!** Your QMAIL AI spam filter is now protecting your inbox using AI. 🎉

For support, refer to [TROUBLESHOOTING.md](TROUBLESHOOTING.md) or review logs at `/home/yourusername/qmail-ai-filter/logs/`.
