# QMAIL AI Filter - Troubleshooting Guide

## Common Issues and Solutions

### 1. Emails Not Being Marked as Spam

#### Check if filter is being called
```bash
# Monitor QMAIL logs for filter execution
tail -f /var/log/mail.log | grep qmail-ai-filter

# Or check if the queue is receiving emails
php manage.php queue:status
```

**Solution:**
- Verify `.qmail` file has the pipe to the filter:
  ```
  | /usr/local/bin/qmail-ai-filter
  ./Maildir/
  ```
- Make sure the wrapper script is executable: `chmod +x /usr/local/bin/qmail-ai-filter`
- Test with a simple email:
  ```bash
  echo "Test email body" | /usr/local/bin/qmail-ai-filter
  ```

---

### 2. "Command not found" Error

#### Problem: `/usr/local/bin/qmail-ai-filter` not found

**Solution:**
```bash
# Reinstall the wrapper script
cat > /usr/local/bin/qmail-ai-filter << 'EOF'
#!/bin/bash
export $(cat /home/admin/qmail-ai-filter/.env | grep -v '^#' | xargs)
exec /usr/bin/php /home/admin/qmail-ai-filter/bin/qmail-ai-filter.php "$@"
EOF

chmod +x /usr/local/bin/qmail-ai-filter
```

---

### 3. API Authentication Errors

#### Error: "OpenAI API failed: HTTP 401"

**Causes:**
- Invalid API key
- Expired API key
- Wrong API provider selected

**Solution:**
```bash
# Check API key in .env
grep OPENAI_API_KEY /home/admin/qmail-ai-filter/.env

# Test API connection
php /home/admin/qmail-ai-filter/manage.php test:api

# Regenerate API key at:
# - OpenAI: https://platform.openai.com/api-keys
# - Claude: https://console.anthropic.com/
# - Ollama Cloud: https://ollama.com/settings/keys
```

---

### 4. Queue Processing Not Running

#### Problem: Emails queued but never processed

**Solution:**
```bash
# Check if cron job is installed
sudo crontab -l

# Check cron logs
sudo tail -50 /var/log/cron

# Manually test queue processor
/usr/bin/php /home/admin/qmail-ai-filter/bin/queue-processor.php

# Add cron job if missing (run as root)
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /home/admin/qmail-ai-filter/bin/queue-processor.php >> /var/log/qmail-ai-filter-queue.log 2>&1") | crontab -
```

---

### 5. Permission Denied Errors

#### Problem: "Permission denied" when moving emails to Spam

**Solution:**
```bash
# Fix ownership of queue and logs
sudo chown -R mail:mail /home/admin/qmail-ai-filter/queue
sudo chown -R mail:mail /home/admin/qmail-ai-filter/logs

# Fix permissions
sudo chmod 755 /home/admin/qmail-ai-filter/queue
sudo chmod 755 /home/admin/qmail-ai-filter/logs
sudo chmod 755 /home/admin/qmail-ai-filter/bin/*.php

# Verify mailbox permissions
ls -la /home/mail/domain.com/user/
```

---

### 6. API Timeout Errors

#### Problem: "API timeout" in logs

**Causes:**
- Network connection issues
- API server overloaded
- Timeout too short

**Solution:**
```bash
# Increase timeout in .env
echo "API_TIMEOUT=60" >> /home/admin/qmail-ai-filter/.env

# Or use a faster model (Claude Haiku)
echo "AI_PROVIDER=claude_anthropic" >> /home/admin/qmail-ai-filter/.env
echo "CLAUDE_MODEL=claude-3-haiku-20240307" >> /home/admin/qmail-ai-filter/.env

# Check network connection
curl -I https://api.openai.com
curl -I https://api.anthropic.com
curl -I https://ollama.com
```

---

### 7. High Memory Usage

#### Problem: PHP processes consuming lots of memory

**Cause:** Processing large emails repeatedly

**Solution:**
```bash
# Reduce max email size in .env
echo "MAX_EMAIL_SIZE=5242880" >> /home/admin/qmail-ai-filter/.env  # 5MB instead of 10MB

# Reduce concurrent requests
echo "MAX_CONCURRENT_REQUESTS=3" >> /home/admin/qmail-ai-filter/.env

# Restart the service
systemctl restart qmail-smtpd 2>/dev/null || service qmail restart
```

---

### 8. Spam Folder Not Created

#### Problem: Emails not moved because .Spam folder doesn't exist

**Solution:**
```bash
# Manually create .Spam folder for user
MAILDIR="/home/mail/domain.com/user/Maildir"
mkdir -p "$MAILDIR/.Spam/{new,cur,tmp}"
touch "$MAILDIR/.Spam/maildirfolder"
chown -R mail:mail "$MAILDIR/.Spam"
chmod 700 "$MAILDIR/.Spam"

# Or enable automatic creation in .env
echo "CREATE_SPAM_FOLDER=true" >> /home/admin/qmail-ai-filter/.env
```

---

### 9. .Spam Folder Exists but Emails Not Showing

#### Problem: Mail client doesn't see .Spam folder

**Cause:** Improper maildir structure

**Solution:**
```bash
# Verify maildir structure
ls -la /home/mail/domain.com/user/Maildir/.Spam/

# Should show:
# drwx------  cur
# drwx------  new
# drwx------  tmp
# -rw-------  maildirfolder

# If missing, recreate
MAILDIR="/home/mail/domain.com/user/Maildir"
rm -rf "$MAILDIR/.Spam"
mkdir -p "$MAILDIR/.Spam/{new,cur,tmp}"
touch "$MAILDIR/.Spam/maildirfolder"
chown -R mail:mail "$MAILDIR/.Spam"
chmod -R 700 "$MAILDIR/.Spam"

# In mail client: IMAP subscribed folders might need refresh
```

---

### 10. Legitimate Emails Marked as Spam

#### Problem: False positives (too strict)

**Solution:**
```bash
# Lower the confidence threshold
echo "SPAM_THRESHOLD=0.5" >> /home/admin/qmail-ai-filter/.env  # Less aggressive

# Enable preserve_original to keep copy in inbox
echo "PRESERVE_ORIGINAL=true" >> /home/admin/qmail-ai-filter/.env

# Enable dry-run to test without moving emails
echo "DRY_RUN=true" >> /home/admin/qmail-ai-filter/.env

# Check logs to see what's being flagged
tail -50 /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log | grep "moved to Spam"
```

---

### 11. CPU High Usage During Queue Processing

#### Problem: Queue processor consuming CPU

**Solution:**
```bash
# Reduce frequency of queue processor
# Edit crontab to run every 10 minutes instead of 5
# (crontab -l 2>/dev/null; echo "*/10 * * * * /usr/bin/php ...") | crontab -

# Or run during off-peak hours only
# Run between 2 AM - 6 AM
# 0 2-6 * * * /usr/bin/php /home/admin/qmail-ai-filter/bin/queue-processor.php

# Reduce concurrent requests
echo "MAX_CONCURRENT_REQUESTS=2" >> /home/admin/qmail-ai-filter/.env
```

---

### 12. Storage Space Issues

#### Problem: Queue files consuming disk space

**Solution:**
```bash
# Check queue size
du -sh /home/admin/qmail-ai-filter/queue

# Clear old completed items (older than 7 days)
php /home/admin/qmail-ai-filter/manage.php queue:clear --force

# Or manually
find /home/admin/qmail-ai-filter/queue -type f -mtime +7 -delete

# Monitor future cleanup
echo "0 3 * * * find /home/admin/qmail-ai-filter/queue -type f -mtime +7 -delete" | crontab -
```

---

## Debugging Steps

### Enable Debug Mode
```bash
# Set DEBUG to true in .env
echo "DEBUG=true" >> /home/admin/qmail-ai-filter/.env
echo "LOG_LEVEL=debug" >> /home/admin/qmail-ai-filter/.env

# Check detailed logs
tail -100 /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log
```

### Test Email Processing
```bash
# Test with sample email
php /home/admin/qmail-ai-filter/manage.php test:email /path/to/email.txt user@domain.com

# Process queue with verbose output
/usr/bin/php /home/admin/qmail-ai-filter/bin/queue-processor.php --limit 1
```

### Check Configuration
```bash
# Validate configuration
php /home/admin/qmail-ai-filter/manage.php config:check

# View current settings
grep -v '^#' /home/admin/qmail-ai-filter/.env
```

### Monitor Logs in Real-time
```bash
# Main application log
tail -f /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log

# Queue processor log
tail -f /var/log/qmail-ai-filter-queue.log

# QMAIL logs (if available)
tail -f /var/log/mail.log
```

---

## Getting Help

1. **Check Logs First:**
   ```bash
   tail -50 /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log
   ```

2. **Verify Configuration:**
   ```bash
   php /home/admin/qmail-ai-filter/manage.php config:check
   ```

3. **Test API Connection:**
   ```bash
   php /home/admin/qmail-ai-filter/manage.php test:api
   ```

4. **Check Queue Status:**
   ```bash
   php /home/admin/qmail-ai-filter/manage.php queue:status
   ```

5. **Enable Debug Mode:**
   Add `DEBUG=true` to `.env`

6. **Run in Dry-run Mode:**
   Add `DRY_RUN=true` to `.env` to test without moving emails
