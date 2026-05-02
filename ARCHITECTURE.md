# QMAIL AI Filter - Architecture & Workflow Diagrams

**Open Source Project by Jose Rodriguez Arroyo** | 📧 jrpcone@gmail.com

A custom spam detection system for QMAIL using AI platforms: **GitHub Copilot**, **OpenAI GPT**, **Google Gemini**, and **Claude Anthropic**.

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          EMAIL DELIVERY FLOW                         │
└─────────────────────────────────────────────────────────────────────┘

                        ┌──────────────────┐
                        │  Incoming Email  │
                        │  from Internet   │
                        └────────┬─────────┘
                                 │
                        ┌────────▼─────────┐
                        │  QMAIL Server    │
                        │  (receives SMTP) │
                        └────────┬─────────┘
                                 │
                        ┌────────▼─────────────────────┐
                        │  .qmail routing file         │
                        │  | /usr/local/bin/...        │
                        │  ./Maildir/                  │
                        └────────┬──────────────────────┘
                                 │
                ┌────────────────┴────────────────┐
                │  (SYNCHRONOUS - FAST!)         │
                │                                │
                ▼                                ▼
     ┌──────────────────────┐        ┌──────────────────────┐
     │ qmail-ai-filter.php  │        │ Mailbox receives    │
     │ (Entry Point)        │        │ copy of email       │
     │                      │        │ (immediately)       │
     │ 1. Read stdin        │        └──────────────────────┘
     │ 2. Write temp file   │
     │ 3. Queue for process │
     │ 4. Exit(0)           │         User sees email appear
     │ (< 100ms)            │         in inbox right away!
     └────────┬─────────────┘
              │
              │ (JSON)
              ▼
     ┌──────────────────────┐
     │ Queue (JSON files)   │
     │ queue/pending/*.json │
     │ (stored on disk)     │
     └──────────────────────┘
              │
              │ (every 5 minutes)
              ▼
     ┌──────────────────────────────────┐
     │  Cron Job triggers               │
     │  queue-processor.php             │
     │  (ASYNCHRONOUS - BACKGROUND)     │
     └────────────────┬─────────────────┘
                      │
         ┌────────────┴────────────┐
         │                         │
         ▼                         ▼
    ┌─────────────┐         ┌──────────────────┐
    │ Get pending │         │ Process up to 10 │
    │ emails from │         │ emails per cron  │
    │ queue/      │         │ (configurable)   │
    │ pending/    │         └──────────────────┘
    └─────────────┘
         │
         │ FOR EACH EMAIL:
         ▼
    ┌──────────────────────┐
    │ Move to              │
    │ queue/processing/    │
    └──────────┬───────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ Parse Email Content              │
    │ (EmailParser.php)                │
    │                                  │
    │ • Extract headers                │
    │ • Parse body                     │
    │ • Find attachments               │
    │ • Extract URLs                   │
    └──────────┬───────────────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ Send to AI API                   │
    │ (OpenAI or Claude)               │
    │                                  │
    │ Request:                         │
    │ - Subject, From, Body            │
    │ - URLs, Attachments              │
    │ (max 2000 chars, 2MB limit)      │
    │                                  │
    │ Response:                        │
    │ - is_spam (bool)                 │
    │ - confidence (0.0-1.0)           │
    │ - reason (string)                │
    │ - spam_type (enum)               │
    └──────────┬───────────────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ Check Threshold                  │
    │ (default: 0.7)                   │
    └──────────┬───────────────────────┘
               │
        ┌──────┴──────┐
        │             │
      NO             YES
    (spam)         (spam &
    below        confidence
    threshold)    >= 0.7)
        │             │
        ▼             ▼
    ┌─────┐    ┌─────────────────────────┐
    │Keep │    │ Move Email to .Spam     │
    │in   │    │ (MailboxManager.php)    │
    │Inbox│    │                         │
    │     │    │ 1. Create .Spam folder  │
    └──┬──┘    │ 2. Move email file      │
       │        │ 3. Set permissions     │
       │        │ 4. Set ownership       │
       │        └──────────┬─────────────┘
       │                   │
       │                   ▼
       │         ┌──────────────────────┐
       │         │ User's Mailbox       │
       │         │                      │
       │         │ .Inbox/new/file      │
       │         │ .Spam/new/file       │
       │         └──────────────────────┘
       │
       └──────────┬──────────────────────┐
                  │                      │
                  ▼                      ▼
         ┌─────────────────┐    ┌──────────────────┐
         │ Mark as         │    │ Mark as          │
         │ queue/          │    │ queue/completed/ │
         │ completed/      │    │ (success)        │
         │ (success)       │    └──────────────────┘
         └─────────────────┘
                  │
                  ▼
         ┌──────────────────┐
         │ Cleanup & Log    │
         │ - Remove temp    │
         │ - Delete old     │
         │ - Write to log   │
         └──────────────────┘
```

---

## Component Interaction

```
                    ┌─────────────────────────────────────┐
                    │     qmail-ai-filter.php             │
                    │    (Entry Point)                    │
                    └──────────────┬──────────────────────┘
                                   │
                 ┌─────────────────┴──────────────────┐
                 │                                    │
                 ▼                                    ▼
    ┌────────────────────────┐        ┌─────────────────────┐
    │   EmailParser          │        │ QueueManager        │
    │ (src/Email/)           │        │ (src/Queue/)        │
    │                        │        │                     │
    │ - Parse MIME headers   │        │ - Create queue item │
    │ - Extract body         │        │ - Store JSON file   │
    │ - Find attachments     │        │ - Status tracking   │
    │ - Extract URLs         │        │ - Retry logic       │
    └────────────────────────┘        └─────────────────────┘
                                               │
                                               │ (every 5 min)
                                               ▼
                                    ┌──────────────────────┐
                                    │ queue-processor.php  │
                                    │ (Background Worker)  │
                                    └──────────┬───────────┘
                                               │
                            ┌──────────────────┼──────────────────┐
                            │                  │                  │
                            ▼                  ▼                  ▼
                    ┌──────────────┐   ┌──────────────┐  ┌──────────────┐
                    │EmailParser   │   │SpamFilter    │  │MailboxManager│
                    │(Parse email) │   │Engine        │  │(Move email)  │
                    └──────────────┘   │(Analyze)     │  └──────────────┘
                                       └──────┬───────┘
                                              │
                        ┌─────────────────────┼─────────────────────┐
                        │                     │                     │
                        ▼                     ▼                     ▼
                  ┌────────────────┐   ┌─────────────┐    ┌──────────────┐
                  │OpenAIProvider  │   │ClaudeProvider   │ Logger       │
                  │(GPT-3.5-turbo) │   │(Claude Haiku)   │ (src/Logging)│
                  └────────────────┘   └─────────────┘    └──────────────┘
                        │                     │                     │
                        └─────────────────────┼─────────────────────┘
                                              │
                                              ▼
                            ┌──────────────────────────────┐
                            │  logs/qmail-ai-filter.log    │
                            │  (All activity logged here)  │
                            └──────────────────────────────┘
```

---

## Database-like Queue Structure

```
Queue Directory: /home/admin/qmail-ai-filter/queue/

pending/
├── email_123abc.json      ← New emails waiting to process
├── email_456def.json
└── email_789ghi.json

processing/
├── email_123abc.json      ← Currently being analyzed by AI
└── email_456def.json

completed/
├── email_123abc.json      ← Successfully processed
├── email_456def.json      │  (kept for 7 days)
└── email_789ghi.json      │

failed/
├── email_xyz.json         ← Exhausted retries
└── email_uvw.json         │  (kept for audit)


Example queue item (JSON):
{
  "id": "email_123abc",
  "email_path": "/tmp/qmail_xyz123",
  "user_domain": "admin@example.com",
  "created_at": 1234567890,
  "retry_count": 0,
  "completed_at": 1234567920,
  "result": {
    "is_spam": true,
    "confidence": 0.89,
    "reason": "Common phishing pattern detected",
    "spam_type": "phishing",
    "moved_to_spam": true
  }
}
```

---

## Configuration Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    .env (Environment)                           │
│  AI_PROVIDER=openai                                             │
│  OPENAI_API_KEY=sk-...                                          │
│  SPAM_THRESHOLD=0.7                                             │
└────────────┬────────────────────────────────────────────────────┘
             │ (read by)
             ▼
┌─────────────────────────────────────────────────────────────────┐
│                 config/config.php                               │
│   (Loads env vars, returns config array)                        │
└────────┬────────────────────────────┬────────────────────────────┘
         │                            │
         ├─────────────────────┐      │
         │                     │      │
         ▼                     ▼      ▼
    ┌─────────┐         ┌──────────┐  ┌───────────────┐
    │AI Config│         │QMAIL     │  │Queue Settings │
    │         │         │Settings  │  │               │
    │Provider │         │          │  │Retry Logic    │
    │API Key  │         │Mailbox   │  │Queue Dir      │
    │Model    │         │Root Path │  │Max Concurrent │
    │Timeout  │         │Spam Fold │  │               │
    └─────────┘         └──────────┘  └───────────────┘
         │                     │              │
         └─────────────────────┼──────────────┘
                               │
                        (used by)
                               │
                    ┌──────────┴──────────┐
                    │                     │
                    ▼                     ▼
            ┌──────────────┐     ┌───────────────┐
            │AI Provider   │     │QueueManager   │
            │Classes       │     │MailboxManager│
            │              │     │SpamFilter    │
            └──────────────┘     └───────────────┘
```

---

## API Communication

```
SYNCHRONOUS (Fast - < 100ms)
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│ Email arrives                                                │
│    └─→ qmail-ai-filter.php                                   │
│         └─→ Read from stdin                                  │
│         └─→ Write temp file                                  │
│         └─→ Queue to disk                                    │
│         └─→ Exit(0)                                          │
│    └─→ Email delivered to mailbox                            │
│    └─→ User sees email (instantly!)                          │
│                                                              │
└──────────────────────────────────────────────────────────────┘

ASYNCHRONOUS (Smart - 1-5 seconds, background)
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│ Cron job runs queue-processor.php every 5 minutes           │
│    └─→ Get 10 pending emails from queue                     │
│    └─→ FOR EACH:                                            │
│         ├─→ Move to processing/                             │
│         ├─→ Parse email content                             │
│         ├─→ Call AI API:                                    │
│         │   POST https://api.openai.com/v1/chat/completions│
│         │   OR https://api.anthropic.com/v1/messages        │
│         │   (send email data + spam detection prompt)       │
│         ├─→ Receive analysis result                         │
│         ├─→ Check confidence threshold                      │
│         ├─→ IF spam & confident THEN:                       │
│         │   └─→ Call MailboxManager.moveToSpam()            │
│         │   └─→ Move email file to .Spam/new/               │
│         ├─→ Move to completed/                              │
│         └─→ Clean up temp files                             │
│    └─→ Clean up old queue items                             │
│                                                              │
│ Result: Spam folder populated, user checks and finds spam   │
│ (within 5-10 minutes of email receipt)                      │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Error Handling & Recovery

```
Processing Email
        │
        ▼
┌──────────────────┐
│ Analyze with AI  │
└────────┬─────────┘
         │
    ┌────┴─────┐
    │           │
   OK       FAILED (HTTP Error, Timeout, etc.)
    │           │
    ▼           ▼
 Success   ┌────────────────┐
 (move to  │ retry_count++  │
  completed)└────────┬───────┘
               │
           ┌───┴────────┐
           │            │
      retry_count   retry_count >= MAX_RETRIES
      < MAX_RETRIES  (default 3)
           │            │
           ▼            ▼
        Requeue     ┌─────────────┐
        pending/    │ Move to     │
        (wait 300s  │ queue/failed│
         before     │ PERMANENT   │
         retry)     │ FAILURE     │
                    └─────────────┘
                    (Admin review
                     needed)
```

---

## Log Entry Examples

```
[2024-04-19 10:30:45] [info] Processing email {"file":"file.eml","user":"admin@example.com"}

[2024-04-19 10:30:46] [info] Spam analysis complete {
  "user":"admin@example.com",
  "is_spam":true,
  "confidence":0.85,
  "reason":"Phishing pattern detected in subject and links"
}

[2024-04-19 10:30:47] [warning] Email moved to Spam {
  "user":"admin@example.com",
  "confidence":0.85
}

[2024-04-19 10:30:48] [info] Queue statistics {
  "pending":5,
  "processing":1,
  "completed":342,
  "failed":2
}

[2024-04-19 10:30:50] [error] OpenAI API failed: HTTP 429, Rate limited
```

---

## Timeline Example

```
10:00:00  Email arrives at QMAIL server
10:00:00  qmail-ai-filter.php called
10:00:05  Email queued (5 files under 100ms)
          User sees email in inbox ✓
          
10:05:00  Cron job triggers queue-processor.php
10:05:01  Email moved to processing/
10:05:02  Email parsed
10:05:03  API request sent to OpenAI
10:05:05  API response received (confidence: 0.89)
10:05:05  Email moved to .Spam/new/
10:05:06  Email moved to completed/
          User sees email moved to Spam folder ✓
          
10:10:00  Cleanup runs
          Old queue items deleted (> 7 days)
```

---

## Security Zones

```
┌──────────────────────────────────────────────────────────────┐
│                     INTERNET (Untrusted)                     │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│                   FIREWALL / MAIL SERVER                     │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ QMAIL Server Zone (mail user ownership)              │   │
│  │                                                       │   │
│  │  Queue storage (700 perms)                          │   │
│  │  Log files (600 perms)                              │   │
│  │  Mailboxes (700 perms)                              │   │
│  │                                                       │   │
│  │  .env file (600 perms - API KEYS!)                  │   │
│  │                                                       │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

These diagrams help visualize the complete system architecture and workflow.
