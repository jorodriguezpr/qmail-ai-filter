# QMAIL AI Filter

**An Open Source Project by Jose Rodriguez Arroyo**  
📧 Email: jrpcone@gmail.com

A sophisticated PHP-based plugin for QMAIL email server that uses Artificial Intelligence to detect and filter spam emails **before they reach your mailbox**. This custom QMAIL AI spam filter integrates with the most popular AI platforms including **OpenAI GPT**, **Google Gemini**, **Claude Anthropic**, **GitHub Copilot**, and **Ollama Cloud** to provide intelligent spam detection. Detected spam is automatically quarantined or moved to the `.Spam` folder for each user/domain.

---

## Features

✨ **AI-Powered Spam Detection**
- Analyzes emails using advanced AI models (GitHub Copilot, OpenAI, Gemini, Claude, Ollama Cloud)
- Customizable confidence thresholds
- Detailed spam classification (phishing, scams, malware, marketing, etc.)

⚡ **Dual Processing Modes**
- **Synchronous Mode (RECOMMENDED)**: Real-time filtering with immediate spam quarantine
- **Asynchronous Mode**: Queue-based background processing for high volume
- Choose the mode that fits your needs

🏷️ **Email Header Tagging (Sync Mode)**
- Adds `X-AI-JRA-Spam-Class` header (true/false) to all emails
- Includes confidence score and spam reason
- Perfect for custom email rules and filtering
- Only HAM emails delivered to inbox

📧 **QMAIL Integration**
- Seamless integration with QMAIL/Kloxo NG
- Automatic spam quarantine (sync mode) or .Spam folder (async mode)
- Maildir++ format support
- Works with any Linux user account

🔧 **Flexible Configuration**
- Support for multiple AI providers
  - **GitHub Copilot** (2026+ - RECOMMENDED)
  - **OpenAI** (GPT-3.5-turbo, GPT-4, GPT-4o)
  - **Google Gemini** (Gemini Pro, Gemini Flash)
  - **Claude Anthropic** (Haiku, Sonnet, Opus)
   - **Ollama Cloud** (glm-5.1)
- Configurable spam detection threshold
- Dry-run mode for testing
- Detailed logging and statistics

📊 **Production-Ready**
- Error handling and graceful degradation
- Queue retry mechanism
- Automatic log rotation
- Queue cleanup

## Architecture

### Processing Modes

**Synchronous Mode (RECOMMENDED)** - Real-time filtering with header tagging
- Analyzes each email immediately using AI
- Adds `X-AI-JRA-Spam-Class` header (true/false)
- Only delivers HAM (non-spam) emails to inbox
- Quarantines spam emails automatically
- No background processing needed

**Asynchronous Mode** - Queue-based background processing
- Queues emails for later processing
- Non-blocking (instant delivery)
- Requires cron job for background processing
- Moves spam to .Spam folder after analysis

### Components

1. **qmail-ai-filter-wrapper-sync.sh** - Synchronous wrapper (RECOMMENDED)
   - Performs immediate AI analysis before delivery
   - Adds X-AI-JRA-Spam-Class header to all emails
   - Delivers only HAM emails to Maildir
   - Quarantines spam emails
   - No cron job required

2. **qmail-ai-filter.php** - Asynchronous entry point
   - Receives email from QMAIL stdin
   - Queues email for processing
   - Non-blocking (returns immediately to QMAIL)

3. **queue-processor.php** - Background processor (async mode only)
   - Processes queued emails asynchronously
   - Integrates with AI providers
   - Moves spam to .Spam folder
   - Designed to run via cron job

3. **AI Providers**
   - **GitHubCopilotProvider**: Uses GitHub Copilot models (gpt-4o - RECOMMENDED)
   - **OpenAIProvider**: Uses GPT-3.5-turbo, GPT-4, or GPT-4o
   - **GeminiProvider**: Uses Google Gemini Pro or Flash models
   - **ClaudeProvider**: Uses Claude models from Anthropic (Haiku, Sonnet, Opus)
   - **OllamaCloudProvider**: Uses Ollama Cloud models (glm-5.1 default)

4. **Queue Manager**
   - Manages email processing queue
   - Handles retries for failed emails
   - Provides statistics and cleanup

5. **Mailbox Manager**
   - Handles QMAIL mailbox operations
   - Creates .Spam folders
   - Moves emails to spam

## Installation

### Quick Links

📖 **Choose your installation method:**

- **[QUICK_SETUP.md](QUICK_SETUP.md)** - 5-minute quick start for any user
- **[INSTALLATION_CUSTOM.md](INSTALLATION_CUSTOM.md)** - Full guide for any Kloxo NG user
- **[README.md](#step-1-downloadclone)** - Default setup (admin user)

### Prerequisites

- PHP 7.4 or higher
- QMAIL email server with Kloxo NG
- curl extension enabled
- OpenAI or Claude API key

### Step 1: Download/Clone

```bash
# For default (admin user):
cd /home/admin
git clone <repository-url> qmail-ai-filter

# For custom user (e.g., yourusername):
cd /home/yourusername
git clone <repository-url> qmail-ai-filter
# Or extract the archive
```

### Step 2: Install (Choose One)

```bash
chmod 755 /home/admin/qmail-ai-filter/bin/qmail-ai-filter.php
chmod 755 /home/admin/qmail-ai-filter/bin/queue-processor.php
chmod 755 /home/admin/qmail-ai-filter/queue
chmod 755 /home/admin/qmail-ai-filter/logs
```

### Step 3: Configure Environment

Create a `.env` file in the project root:

# Choose: 'github_copilot', 'openai', 'gemini', 'claude_anthropic', or 'ollama-cloud'
AI_PROVIDER=github_copilot

# GitHub Copilot (RECOMMENDED)
GITHUB_COPILOT_API_KEY=ghp-your-key-here
GITHUB_COPILOT_MODEL=gpt-4o

# OR for OpenAI:
# OPENAI_API_KEY=sk-your-api-key-here
# OPENAI_MODEL=gpt-4o

# OR for Google Gemini:
# GEMINI_API_KEY=your-gemini-key-here
# GEMINI_MODEL=gemini-pr'claude_anthropic'
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_MODEL=gpt-3.5-turbo

# OR for Claude:
# CLAUDE_API_KEY=sk-ant-your-key-here
# CLAUDE_MODEL=claude-3-haiku-20240307

# OR for Ollama Cloud:
# AI_PROVIDER=ollama-cloud
# OLLAMA_CLOUD_API_KEY=your-ollama-cloud-api-key
# OLLAMA_CLOUD_MODEL=glm-5.1

# Spam Detection
SPAM_THRESHOLD=0.7  # 70% confidence threshold
API_TIMEOUT=30

# QMAIL Settings
QMAIL_MAILBOX_ROOT=/home/mail
CREATE_SPAM_FOLDER=true
PRESERVE_ORIGINAL=false  # false = move, true = copy

# Logging
LOG_LEVEL=info
DEBUG=false
DRY_RUN=false

# Queue
QUEUE_DIR=/home/admin/qmail-ai-filter/queue
MAX_CONCURRENT_REQUESTS=5
MAX_RETRIES=3
RETRY_DELAY=300
```

### Step 4: Load Environment

Create a shell wrapper script `/usr/local/bin/qmail-ai-filter`:

```bash
#!/bin/bash
export $(cat /home/admin/qmail-ai-filter/.env | grep -v '^#' | xargs)
/usr/bin/php /home/admin/qmail-ai-filter/bin/qmail-ai-filter.php "$@"
```

```bash
chmod +x /usr/local/bin/qmail-ai-filter
```

### Step 5: Integrate with QMAIL

For **global email filtering** in Kloxo NG:

Edit `.qmail-default` in a domain:

```
| /usr/local/bin/qmail-ai-filter
./Maildir/
```

For **user-specific filtering**, edit the user's `.qmail` file:

```
| /usr/local/bin/qmail-ai-filter
./Maildir/
```

For **domain-specific filtering** via alias:

```bash
# Create alias in Kloxo NG
admin: |/usr/local/bin/qmail-ai-filter
```

### Step 6: Setup Background Processing (Cron)

Add to root's crontab:

```bash
crontab -e
```

Add these lines:

```bash
# Process spam filter queue every 5 minutes
*/5 * * * * /usr/bin/php /home/admin/qmail-ai-filter/bin/queue-processor.php >> /var/log/qmail-ai-filter-queue.log 2>&1

# Cleanup old queue items daily at 2 AM
0 2 * * * /usr/bin/php -r "require_once '/home/admin/qmail-ai-filter/src/Autoloader.php'; Autoloader::register(); \$q = new \QmailAiFilter\Queue\QueueManager(); \$q->cleanup();" >> /var/log/qmail-ai-filter-queue.log 2>&1
```

## Configuration

### API Keys

#### OpenAI

1. Go to https://platform.openai.com/api-keys
2. Create a new API key
3. Set `OPENAI_API_KEY` in .env

Estimated costs: $0.0005 per 1K tokens (GPT-3.5-turbo)

#### Claude Anthropic

1. Go to https://console.anthropic.com/
2. Create API key in workspace settings
3. Set `CLAUDE_API_KEY` in .env

Estimated costs: Variable by model (Haiku is cheapest)

### Spam Detection Threshold

Set `SPAM_THRESHOLD` (0.0 - 1.0):

- **0.5**: More aggressive (catches more spam but may have false positives)
- **0.7**: Balanced (recommended)
- **0.9**: Conservative (catches only obvious spam)

### Email Size Limits

```bash
MAX_EMAIL_SIZE=10485760  # 10MB default
```

Larger emails are skipped to avoid API costs.

## Usage

### Manual Testing

```bash
# Test with a sample email
php /home/admin/qmail-ai-filter/bin/queue-processor.php --limit 5 --dry-run

# View logs
tail -f /var/log/qmail-ai-filter.log
```

### View Queue Status

```bash
# Get queue statistics
php -r "
require_once '/home/admin/qmail-ai-filter/src/Autoloader.php';
Autoloader::register();
\$q = new \QmailAiFilter\Queue\QueueManager();
print_r(\$q->getStats());
"
```

### Monitor Spam Detection

```bash
tail -f /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log
```

## Troubleshooting

### Emails Not Being Filtered

1. **Check QMAIL integration:**
   ```bash
   # Verify .qmail file has pipe to filter
   cat /home/mail/domain.com/user/.qmail
   ```

2. **Check logs:**
   ```bash
   tail -50 /home/admin/qmail-ai-filter/logs/qmail-ai-filter.log
   ```

3. **Test queue processor:**
   ```bash
   php /home/admin/qmail-ai-filter/bin/queue-processor.php --dry-run
   ```

### API Errors

- **"OpenAI API failed: HTTP 401"**: Invalid API key
- **"API timeout"**: Increase `API_TIMEOUT` in .env
- **"Rate limited"**: Reduce `MAX_CONCURRENT_REQUESTS`

### Permission Denied

```bash
# Fix ownership
chown -R mail:mail /home/admin/qmail-ai-filter

# Fix permissions
chmod 755 /home/admin/qmail-ai-filter/bin/*.php
chmod 755 /home/admin/qmail-ai-filter/queue
```

### Cron Not Running

```bash
# Check if cron is enabled for root
sudo crontab -l

# Check cron logs
sudo tail -f /var/log/cron
```

## Performance Tuning

### High Email Volume

1. Increase `MAX_CONCURRENT_REQUESTS`:
   ```bash
   MAX_CONCURRENT_REQUESTS=10
   ```

2. Reduce API timeout:
   ```bash
   API_TIMEOUT=15  # Use faster model, accept occasional timeouts
   ```

3. Run queue processor more frequently:
   ```bash
   */2 * * * * /usr/bin/php ...  # Every 2 minutes instead of 5
   ```

### Cost Optimization

1. Use Claude Haiku (cheaper):
   ```bash
   CLAUDE_MODEL=claude-3-haiku-20240307
   ```

2. Increase spam threshold to reduce API calls:
   ```bash
   SPAM_THRESHOLD=0.85
   ```

3. Skip large emails:
   ```bash
   MAX_EMAIL_SIZE=5242880  # 5MB instead of 10MB
   ```

## Security Considerations

⚠️ **API Key Security**

- Store `.env` file outside web root
- Restrict file permissions: `chmod 600 .env`
- Use separate API keys for development/production
- Rotate keys regularly

⚠️ **Email Privacy**

- AI providers may log email content for rate limiting/abuse detection
- Review provider privacy policies
- Consider on-premise solutions for sensitive data

⚠️ **Queue File Security**

- Queue files contain raw email data
- Restrict access: `chmod 700 queue/`
- Implement queue cleanup to remove old files

## Limitations

- Emails larger than `MAX_EMAIL_SIZE` are skipped
- Empty emails are not processed
This project is open source software released under the **MIT License**.

**Copyright (c) 2026 Jose Rodriguez Arroyo**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Author & Contact

**Jose Rodriguez Arroyo**  
📧 Email: jrpcone@gmail.com  
🌐 Project: QMAIL AI Filter - Custom Spam Detection for QMAIL  
📅 Year: 2026rnet connection for API calls
- False positives/negatives depend on AI model training

## Support & Contributing

For issues, feature requests, or contributions:

1. Check existing logs: `/home/admin/qmail-ai-filter/logs/`
2. Enable debug mode: `DEBUG=true` in .env
3. Test with sample emails in dry-run mode

## License

MIT / Open Source

## Changelog

### v1.0.0 (Initial Release)
- AI-powered spam detection (OpenAI, Claude)
- Asynchronous queue processing
- QMAIL/Kloxo NG integration
- Configurable thresholds and behavior

### 06-04-2026 Add Support to Ollama cloud models
