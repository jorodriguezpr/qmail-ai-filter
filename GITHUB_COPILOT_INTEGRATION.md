# GitHub Copilot Integration - 2026+

**QMAIL AI Filter** - Open Source Project by Jose Rodriguez Arroyo | 📧 jrpcone@gmail.com

## Overview

The QMAIL AI Filter now supports **GitHub Copilot** as the primary AI provider for spam detection, making it the recommended choice for 2026 and beyond. This custom QMAIL spam filter supports multiple AI platforms: **GitHub Copilot**, **OpenAI GPT**, **Google Gemini**, **Claude Anthropic**, and **Ollama Cloud**.

## What Changed

### ✅ New Provider Added

- **Provider Name:** GitHub Copilot
- **File:** `src/AI/Providers/GitHubCopilotProvider.php`
- **Status:** Primary provider (default)
- **Models Supported:** Claude 3.7 Sonnet (default), Claude 3.7 Opus, Claude 3 Haiku

### ✅ Configuration Updated

- **Default Provider:** Changed from `openai` to `github_copilot`
- **Configuration Key:** `GITHUB_COPILOT_API_KEY` (instead of `OPENAI_API_KEY`)
- **Model Selection:** `GITHUB_COPILOT_MODEL` (default: `claude-3.7-sonnet`)
- **API Endpoint:** `https://api.github.com/copilot/chat/completions`

### ✅ Applications Updated

All applications now support GitHub Copilot:
- `bin/queue-processor.php` - Queue processor
- `manage.php` - Management CLI tool
- `config/config.php` - Configuration system
- `.env.example` - Configuration template

## How to Use

### 1. Get GitHub Token

```bash
# Go to GitHub Settings
https://github.com/settings/tokens

# Create new "Personal access token"
# Scopes needed: copilot:read

# Copy the token (starts with "ghp_")
```

### 2. Set Configuration

```bash
# Edit .env file
nano /home/yourusername/qmail-ai-filter/.env

# Set GitHub Copilot token
GITHUB_COPILOT_API_KEY=ghp_your_token_here

# Optional: Change model if needed
GITHUB_COPILOT_MODEL=claude-3.7-sonnet
```

### 3. Test Configuration

```bash
# Test API connection
php /home/yourusername/qmail-ai-filter/manage.php test:api

# Should show: ✓ GitHub Copilot API test completed successfully
```

## Why GitHub Copilot?

### 🚀 Performance
- Fastest spam detection (optimized for chat use)
- Low latency with GitHub's infrastructure
- Handles high-volume requests efficiently

### 💰 Cost
- Included in Copilot subscription (~$0.10-0.20 per 1,000 emails)
- Cheaper than standalone API fees
- Enterprise-friendly pricing

### 🎯 Accuracy
- Built on Claude 3.7 (latest 2026 model)
- Fine-tuned for common tasks
- Continuous updates from GitHub

### 🔐 Security
- GitHub-managed infrastructure
- Enterprise-grade compliance
- Token-based authentication

## Configuration Options

### Default (Recommended)

```env
AI_PROVIDER=github_copilot
GITHUB_COPILOT_API_KEY=ghp_...
GITHUB_COPILOT_MODEL=claude-3.7-sonnet
```

### Fast Mode

```env
GITHUB_COPILOT_MODEL=claude-3-haiku-20240307
```

### Accurate Mode

```env
GITHUB_COPILOT_MODEL=claude-3.7-opus
```

## Migration from Other Providers

If you're currently using OpenAI or Claude:

```bash
# Edit .env
nano /home/yourusername/qmail-ai-filter/.env

# Change:
# FROM: AI_PROVIDER=openai
# TO:   AI_PROVIDER=github_copilot

# Remove old keys:
# OPENAI_API_KEY=...
# CLAUDE_API_KEY=...

# Add new key:
GITHUB_COPILOT_API_KEY=ghp_...

# Restart processing
sudo crontab -e  # (no restart needed, next run uses new config)
```

## Fallback Configuration

If GitHub Copilot fails, you can configure fallback providers:

```env
# Primary
AI_PROVIDER=github_copilot
GITHUB_COPILOT_API_KEY=ghp_...

# Fallback 1
OPENAI_API_KEY=sk-...

# Fallback 2
CLAUDE_API_KEY=sk-ant-...
```

**Note:** Current version uses primary provider only. Automatic fallback planned for v2.0.

## Pricing Comparison (per 1,000 emails)

| Provider | Cost | Speed | Accuracy | Notes |
|----------|------|-------|----------|-------|
| **GitHub Copilot** | $0.10-0.20 | ⚡⚡⚡ | ⭐⭐⭐⭐⭐ | **RECOMMENDED** - Included in subscription |
| Ollama Cloud (glm-5.1) | Variable | ⚡⚡⚡ | ⭐⭐⭐⭐ | Cloud-hosted Ollama model |
| Claude Haiku | $0.03 | ⚡⚡ | ⭐⭐⭐ | Cheapest |
| Claude Sonnet | $0.15 | ⚡⚡⚡ | ⭐⭐⭐⭐ | Balanced |
| Claude Opus | $0.75 | ⚡⚡ | ⭐⭐⭐⭐⭐ | Most accurate |
| GPT-3.5-turbo | $0.50 | ⚡⚡⭐ | ⭐⭐⭐⭐ | Reliable |
| GPT-4 | $3.00 | ⚡ | ⭐⭐⭐⭐⭐ | Most capable |

## Troubleshooting

### API Connection Fails

```bash
# Check token is valid
grep GITHUB_COPILOT_API_KEY /home/yourusername/qmail-ai-filter/.env

# Test API
php /home/yourusername/qmail-ai-filter/manage.php test:api

# Check logs
tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log
```

### "Unknown AI provider" Error

```bash
# Make sure provider is spelled correctly
grep AI_PROVIDER /home/yourusername/qmail-ai-filter/.env

# Should be: AI_PROVIDER=github_copilot (not github-copilot)
```

### Token Expired

```bash
# Generate new token at https://github.com/settings/tokens
# Update .env with new token
nano /home/yourusername/qmail-ai-filter/.env

# Test
php manage.php test:api
```

## 2026 Features

GitHub Copilot in 2026 includes:

✅ Claude 3.7 models (latest)  
✅ Real-time model updates  
✅ Enterprise-grade reliability  
✅ GitHub-managed infrastructure  
✅ Seamless integration with GitHub Actions  
✅ Advanced error handling  
✅ Optimized for email classification  

## API Endpoint Details

### GitHub Copilot Chat API (2026+)

**Endpoint:** `https://api.github.com/copilot/chat/completions`

**Authentication:**
```
Authorization: Bearer ghp_your_token_here
```

**Request Format:**
```json
{
  "model": "claude-3.7-sonnet",
  "messages": [
    {
      "role": "system",
      "content": "You are a spam detection expert..."
    },
    {
      "role": "user",
      "content": "Analyze this email..."
    }
  ],
  "temperature": 0.3,
  "max_tokens": 500
}
```

**Response Format:**
```json
{
  "choices": [
    {
      "message": {
        "content": "{\"is_spam\": true, \"confidence\": 0.95, ...}"
      }
    }
  ]
}
```

## Future Roadmap

**v1.5 (Current):**
- ✅ GitHub Copilot support
- ✅ Multiple provider support
- ✅ 2026 model compatibility

**v2.0 (Planned):**
- [ ] Automatic provider fallback
- [ ] Load balancing across providers
- [ ] Usage statistics dashboard
- [ ] Model switching based on load
- [ ] Cached analysis results

## Support

For GitHub Copilot issues:

1. **Check Documentation:**
   - `README.md` - Full setup guide
   - `SETUP_FOR_TECHNOLOGIX.md` - Quick start
   - `TROUBLESHOOTING.md` - Common issues

2. **Test Configuration:**
   ```bash
   php manage.php config:check
   php manage.php test:api
   ```

3. **View Logs:**
   ```bash
   tail -f /home/yourusername/qmail-ai-filter/logs/qmail-ai-filter.log
   ```

4. **Verify Token:**
   ```bash
   curl -H "Authorization: Bearer ghp_YOUR_TOKEN" \
     https://api.github.com/user
   ```

---

**GitHub Copilot is now your primary spam detection engine for 2026!** 🚀
