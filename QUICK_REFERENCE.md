# QMAIL AI Filter - Quick Reference

## Installation One-Liner

```bash
sudo bash /home/yourusername/qmail-ai-filter/install-custom.sh yourusername
```

## Configuration Quick Setup

```bash
# 1. Copy example config
cp /home/yourusername/qmail-ai-filter/.env.example /home/yourusername/qmail-ai-filter/.env

# 2. Edit with your API key
nano /home/yourusername/qmail-ai-filter/.env

# 3. Test API connection
php /home/yourusername/qmail-ai-filter/manage.php test:api

# 4. Verify configuration
php /home/yourusername/qmail-ai-filter/manage.php config:check
```

## QMAIL Integration

### Synchronous Filtering (RECOMMENDED) ⚡

**Per-User with Header Tagging:**
```bash
# 1. Copy and customize wrapper
cp /home/yourusername/qmail-ai-filter/bin/qmail-ai-filter-wrapper-sync.sh \
   /usr/local/bin/qmail-ai-filter-sync-yourusername

# 2. Edit ACCOUNT_NAME in the script
nano /usr/local/bin/qmail-ai-filter-sync-yourusername
# Change: ACCOUNT_NAME="yourusername"

# 3. Make executable
chmod 755 /usr/local/bin/qmail-ai-filter-sync-yourusername

# 4. Add to .qmail file
echo '| /usr/local/bin/qmail-ai-filter-sync-yourusername' > ~/.qmail
```

**Benefits:**
- ✅ Immediate spam filtering
- ✅ Only HAM emails delivered
- ✅ X-AI-JRA-Spam-Class header added
- ✅ No cron job needed
- ✅ Spam quarantined automatically

---

### Asynchronous Filtering (Queue-Based)

**Per-User Filtering:**
```bash
# Edit user's .qmail file in Kloxo NG
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
```

**Domain-Wide Filtering:**
```bash
# Edit domain's .qmail-default
| /usr/local/bin/qmail-ai-filter-yourusername
./Maildir/
```

**Note:** Async mode requires cron job for background processing.

## Essential Commands

### Synchronous Mode
```bash
# Check sync wrapper logs
tail -f /home/yourusername/qmail-ai-filter/logs/wrapper-sync.log

# View quarantined spam
ls -la /home/yourusername/qmail-ai-filter/quarantine/

# Count quarantined emails
find /home/yourusername/qmail-ai-filter/quarantine/ -type f | wc -l

# Test sync filter directly
/usr/bin/php /home/yourusername/qmail-ai-filter/bin/sync-filter.php /path/to/email.eml user@domain.com
```

### Asynchronous Mode
```bash
# Check queue status
php /home/yourusername/qmail-ai-filter/manage.php queue:status

# Process queued emails (manual)
php /home/yourusername/qmail-ai-filter/bin/queue-processor.php --limit 10

# View logs
tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log

# Test with sample email
php /home/yourusername/qmail-ai-filter/manage.php test:email /tmp/email.txt user@domain.com

# Validate configuration
php /home/yourusername/qmail-ai-filter/manage.php config:check

# Check user's mailbox
php /home/yourusername/qmail-ai-filter/manage.php folders:check user@domain.com
```

## Cron Job Setup (Async Mode Only)

**Note:** Only needed for asynchronous filtering mode.

```bash
# Add to root's crontab
sudo crontab -e

# Add these lines:
*/5 * * * * /usr/bin/php /home/yourusername/qmail-ai-filter/bin/queue-processor.php >> /var/log/qmail-ai-filter-queue.log 2>&1
0 2 * * * find /home/yourusername/qmail-ai-filter/queue -type f -mtime +7 -delete
```

## Permissions & Ownership

```bash
# Fix all permissions
sudo chown -R mail:mail /home/yourusername/qmail-ai-filter
sudo chmod 755 /home/yourusername/qmail-ai-filter/bin/*.php
sudo chmod 700 /home/yourusername/qmail-ai-filter/.env
sudo chmod 755 /home/yourusername/qmail-ai-filter/queue
sudo chmod 755 /home/yourusername/qmail-ai-filter/logs
```

## Common Configuration

### OpenAI (Recommended for accuracy)
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-YOUR-KEY-HERE
OPENAI_MODEL=gpt-3.5-turbo
SPAM_THRESHOLD=0.7
```

### Claude (Cheaper option)
```env
AI_PROVIDER=claude_anthropic
CLAUDE_API_KEY=sk-ant-YOUR-KEY-HERE
CLAUDE_MODEL=claude-3-haiku-20240307
SPAM_THRESHOLD=0.7
```

## Monitoring

```bash
# Real-time log monitoring
tail -f /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log

# Queue statistics
watch -n 5 'php /home/admin/qmail-ai-filter/manage.php queue:status'

# Last 50 lines
tail -50 /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log

# Search for errors
grep "error" /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log
```

## Troubleshooting Quick Fixes

```bash
# Emails not filtering?
1. Check .qmail file has pipe to filter
2. Verify /usr/local/bin/qmail-ai-filter exists
3. Check queue status: php manage.php queue:status
4. Run queue processor: php bin/queue-processor.php
5. Check logs: tail -100 logs/qmail-ai-filter.log

# API errors?
1. Verify API key: php manage.php test:api
2. Check network: curl -I https://api.openai.com
3. Increase timeout: API_TIMEOUT=60

# Permissions issues?
sudo chown -R mail:mail /home/admin/qmail-ai-filter
sudo chmod -R 755 /home/admin/qmail-ai-filter

# High CPU/Memory?
1. Reduce MAX_CONCURRENT_REQUESTS
2. Reduce MAX_EMAIL_SIZE
3. Run queue processor less frequently (*/10 instead of */5)

# .Spam folder not created?
1. Enable: CREATE_SPAM_FOLDER=true
2. Create manually: mkdir -p /home/mail/domain/user/Maildir/.Spam/{new,cur,tmp}
3. Fix permissions: chown -R mail:mail /home/mail/domain/user/Maildir/.Spam
```

## API Costs Estimate

### OpenAI (GPT-3.5-turbo)
- Cost: ~$0.0005 per email
- 1000 emails: ~$0.50
- 100,000 emails: ~$50

### Claude Haiku (cheapest)
- Cost: ~$0.00015 per email
- 1000 emails: ~$0.15
- 100,000 emails: ~$15

## Performance Targets

- **Mail delivery latency:** < 100ms
- **Queue processing:** 10-20 emails/minute
- **Average API response:** 1-3 seconds
- **Memory per process:** 20-50MB

## Files to Monitor

```
/home/admin/qmail-ai-filter/
├── logs/qmail-ai-filter.log       # Main activity log
├── queue/pending/                 # Emails to process
├── queue/completed/               # Successfully processed
├── queue/failed/                  # Failed emails (check these)
└── .env                           # Configuration (DO NOT SHARE)
```

## Log Levels

```
DEBUG    - Detailed diagnostic information
INFO     - General informational messages (default)
WARNING  - Warning messages (recoverable issues)
ERROR    - Error messages (problems that occurred)
```

## Testing Checklist

- [ ] API key is valid: `php manage.php test:api`
- [ ] Configuration is correct: `php manage.php config:check`
- [ ] Queue processor runs: `php bin/queue-processor.php --limit 1`
- [ ] Cron job is set: `sudo crontab -l | grep queue-processor`
- [ ] .qmail file is updated with pipe
- [ ] Logs show activity: `tail logs/qmail-ai-filter.log`
- [ ] Spam folder is created: `php manage.php folders:check user@domain`
- [ ] Sample email gets processed: `php manage.php test:email`

## Emergency Procedures

### Disable Spam Filtering (Temporarily)
```bash
# Set threshold to 1.0 (essentially disables)
echo "SPAM_THRESHOLD=1.0" >> /home/admin/qmail-ai-filter/.env

# Or remove pipe from .qmail file:
# Just delete the "| /usr/local/bin/qmail-ai-filter" line
```

### Clear Queue (All Items)
```bash
# Use with caution - removes all queue files
rm -rf /home/admin/qmail-ai-filter/queue/*/*
```

### Restart Service
```bash
# QMAIL doesn't need restart for config changes
# Just update .env and cron will pick it up

# Kill any stuck PHP processes
pkill -9 queue-processor.php
pkill -9 qmail-ai-filter.php
```

## Support Resources

- GitHub: [Your repo URL]
- Documentation: `/home/admin/qmail-ai-filter/README.md`
- Troubleshooting: `/home/admin/qmail-ai-filter/TROUBLESHOOTING.md`
- Project Structure: `/home/admin/qmail-ai-filter/PROJECT_STRUCTURE.md`

## Key Contacts

- API Issues → OpenAI Support or Claude Support
- QMAIL Issues → Kloxo NG Support
- PHP Issues → Your hosting provider
