#!/usr/bin/env php
<?php
/**
 * QMAIL AI Filter - Management CLI
 * 
 * Provides commands to manage, monitor, and test the spam filter.
 * 
 * Usage: php manage.php <command> [options]
 */

require_once __DIR__ . '/src/Autoloader.php';
Autoloader::register();

use QmailAiFilter\Queue\QueueManager;
use QmailAiFilter\Filter\SpamFilterEngine;
use QmailAiFilter\Email\MailboxManager;
use QmailAiFilter\Logging\Logger;
use QmailAiFilter\Email\EmailParser;
use QmailAiFilter\AI\Providers\GitHubCopilotProvider;
use QmailAiFilter\AI\Providers\OpenAIProvider;
use QmailAiFilter\AI\Providers\ClaudeProvider;

// Configuration
$config = require __DIR__ . '/config/config.php';

// Commands
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'queue:status':
        commandQueueStatus($config);
        break;
    
    case 'queue:process':
        commandQueueProcess($config, $argv);
        break;
    
    case 'queue:clear':
        commandQueueClear($config, $argv);
        break;
    
    case 'test:email':
        commandTestEmail($config, $argv);
        break;
    
    case 'test:api':
        commandTestAPI($config, $argv);
        break;
    
    case 'config:check':
        commandConfigCheck($config);
        break;
    
    case 'logs:tail':
        commandLogsTail($config, $argv);
        break;
    
    case 'folders:check':
        commandFoldersCheck($config, $argv);
        break;
    
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Show help/usage
 */
function showHelp(): void
{
    $help = <<<'HELP'
QMAIL AI Filter - Management CLI

Usage: php manage.php <command> [options]

Commands:
  queue:status              Show queue statistics
  queue:process [--limit N] Process queue (default limit: 10)
  queue:clear              Clear all queue items
  
  test:email <file> <user> Test email file for spam
  test:api                 Test AI API connection
  
  config:check             Validate configuration
  logs:tail [--lines N]    Show recent log lines
  
  folders:check <user@domain>  Check user mailbox folders
  
  help                      Show this help message

Examples:
  php manage.php queue:status
  php manage.php queue:process --limit 5
  php manage.php test:email /tmp/email.txt user@domain.com
  php manage.php logs:tail --lines 50
  php manage.php folders:check admin@example.com

HELP;

    echo $help;
}

/**
 * Show queue status
 */
function commandQueueStatus(array $config): void
{
    $logger = new Logger($config['logging']['log_dir']);
    $queueManager = new QueueManager($config['queue']['queue_dir'], $logger);
    
    $stats = $queueManager->getStats();
    
    echo "\n";
    echo "Queue Status:\n";
    echo str_repeat("=", 40) . "\n";
    echo sprintf("  Pending:     %d items\n", $stats['pending']);
    echo sprintf("  Processing:  %d items\n", $stats['processing']);
    echo sprintf("  Completed:   %d items\n", $stats['completed']);
    echo sprintf("  Failed:      %d items\n", $stats['failed']);
    echo str_repeat("=", 40) . "\n";
    echo "\n";
}

/**
 * Process queue
 */
function commandQueueProcess(array $config, array $argv): void
{
    $limit = 10;
    if (($key = array_search('--limit', $argv)) !== false && isset($argv[$key + 1])) {
        $limit = intval($argv[$key + 1]);
    }
    
    echo "Processing queue (limit: {$limit})...\n";
    
    $logger = new Logger($config['logging']['log_dir']);
    $queueManager = new QueueManager($config['queue']['queue_dir'], $logger);
    
    $pending = $queueManager->getPending($limit);
    
    if (empty($pending)) {
        echo "No pending emails in queue.\n";
        return;
    }
    
    echo "Found " . count($pending) . " emails to process.\n\n";
    
    // This is simplified - in production, use queue-processor.php
    foreach ($pending as $item) {
        echo "Email: " . basename($item['data']['email_path']) . "\n";
        echo "  Status: Queued for processing\n";
    }
    
    echo "\nRun queue-processor.php for actual processing:\n";
    echo "php bin/queue-processor.php --limit {$limit}\n\n";
}

/**
 * Clear queue
 */
function commandQueueClear(array $config, array $argv): void
{
    $force = in_array('--force', $argv);
    
    if (!$force) {
        echo "This will delete ALL queue items. Use --force to confirm.\n";
        return;
    }
    
    $dirs = ['pending', 'processing', 'completed', 'failed'];
    $total = 0;
    
    foreach ($dirs as $dir) {
        $path = $config['queue']['queue_dir'] . "/{$dir}";
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                unlink("{$path}/{$file}");
                $total++;
            }
        }
    }
    
    echo "Cleared {$total} queue items.\n";
}

/**
 * Test email file
 */
function commandTestEmail(array $config, array $argv): void
{
    $emailFile = $argv[2] ?? null;
    $userDomain = $argv[3] ?? null;
    
    if (!$emailFile || !$userDomain) {
        echo "Usage: php manage.php test:email <email-file> <user@domain>\n";
        return;
    }
    
    if (!file_exists($emailFile)) {
        echo "Error: Email file not found: {$emailFile}\n";
        return;
    }
    
    try {
        echo "\nParsing email: {$emailFile}\n";
        $parser = new EmailParser($emailFile);
        $emailData = $parser->getEmailData();
        
        echo "\nEmail Details:\n";
        echo str_repeat("=", 50) . "\n";
        echo sprintf("From:       %s\n", $emailData['from']);
        echo sprintf("To:         %s\n", $emailData['to']);
        echo sprintf("Subject:    %s\n", $emailData['subject']);
        echo sprintf("Date:       %s\n", $emailData['date']);
        echo sprintf("Body:       %d chars\n", strlen($emailData['body']));
        echo sprintf("Attachments: %d\n", count($emailData['attachments']));
        echo sprintf("URLs:       %d\n", count($emailData['urls']));
        echo str_repeat("=", 50) . "\n";
        
    } catch (\Exception $e) {
        echo "Error parsing email: " . $e->getMessage() . "\n";
    }
}

/**
 * Test API connection
 */
function commandTestAPI(array $config, array $argv): void
{
    $provider = $config['ai_provider']['provider'];
    $logger = new Logger($config['logging']['log_dir']);
    
    echo "\nTesting AI Provider: {$provider}\n";
    echo str_repeat("=", 50) . "\n";
    
    try {
        $aiProvider = null;
        
        if ($provider === 'github_copilot') {
            $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? '';
            $model = $config['ai_provider']['github_copilot']['model'] ?? 'claude-3.7-sonnet';
            $aiProvider = new GitHubCopilotProvider($apiKey, $model, $logger);
        } else if ($provider === 'openai') {
            $apiKey = $config['ai_provider']['openai']['api_key'] ?? '';
            $model = $config['ai_provider']['openai']['model'] ?? 'gpt-3.5-turbo';
            $aiProvider = new OpenAIProvider($apiKey, $model, $logger);
        } else if ($provider === 'claude_anthropic') {
            $apiKey = $config['ai_provider']['claude_anthropic']['api_key'] ?? '';
            $model = $config['ai_provider']['claude_anthropic']['model'] ?? 'claude-3-haiku-20240307';
            $aiProvider = new ClaudeProvider($apiKey, $model, $logger);
        }
        
        if (!$aiProvider) {
            echo "Error: Unknown provider\n";
            return;
        }
        
        echo sprintf("Provider:   %s\n", $aiProvider->getProviderName());
        echo sprintf("Configured: %s\n", $aiProvider->isConfigured() ? 'Yes' : 'No');
        
        if (!$aiProvider->isConfigured()) {
            echo "\nError: API key not configured\n";
            return;
        }
        
        // Test with sample email
        $testEmail = [
            'subject' => 'Test Email',
            'from' => 'test@example.com',
            'body' => 'This is a test email',
        ];
        
        echo "\nSending test request to API...\n";
        $result = $aiProvider->analyzeEmail($testEmail);
        
        echo "\nResults:\n";
        echo sprintf("  Is Spam:    %s\n", $result['is_spam'] ? 'Yes' : 'No');
        echo sprintf("  Confidence: %.2f%%\n", $result['confidence'] * 100);
        echo sprintf("  Reason:     %s\n", $result['reason'] ?? 'N/A');
        
        if (isset($result['error'])) {
            echo sprintf("  Error:      %s\n", $result['error']);
        }
        
        echo str_repeat("=", 50) . "\n";
        echo "✓ API test completed successfully\n";
        
    } catch (\Exception $e) {
        echo "✗ API test failed: " . $e->getMessage() . "\n";
    }
}

/**
 * Check configuration
 */
function commandConfigCheck(array $config): void
{
    echo "\n";
    echo "Configuration Check:\n";
    echo str_repeat("=", 50) . "\n";
    
    // Check AI Provider
    $provider = $config['ai_provider']['provider'] ?? 'unknown';
    $configured = false;
    
    if ($provider === 'openai') {
        $configured = !empty($config['ai_provider']['openai']['api_key']);
        echo sprintf("AI Provider:  %s %s\n", $provider, $configured ? '✓' : '✗');
        echo sprintf("Model:        %s\n", $config['ai_provider']['openai']['model'] ?? 'N/A');
    } else if ($provider === 'claude_anthropic') {
        $configured = !empty($config['ai_provider']['claude_anthropic']['api_key']);
        echo sprintf("AI Provider:  %s %s\n", $provider, $configured ? '✓' : '✗');
        echo sprintf("Model:        %s\n", $config['ai_provider']['claude_anthropic']['model'] ?? 'N/A');
    }
    
    echo "\n";
    echo sprintf("Spam Threshold:     %.2f\n", $config['spam_detection']['confidence_threshold']);
    echo sprintf("QMAIL Mailbox Root: %s %s\n", 
        $config['qmail']['mailbox_root'],
        is_dir($config['qmail']['mailbox_root']) ? '✓' : '✗'
    );
    echo sprintf("Queue Directory:    %s %s\n",
        $config['queue']['queue_dir'],
        is_dir($config['queue']['queue_dir']) ? '✓' : '✗'
    );
    echo sprintf("Log Directory:      %s %s\n",
        $config['logging']['log_dir'],
        is_dir($config['logging']['log_dir']) ? '✓' : '✗'
    );
    
    echo "\n";
    echo sprintf("Max Concurrent:     %d\n", $config['queue']['max_concurrent']);
    echo sprintf("Max Retries:        %d\n", $config['queue']['max_retries']);
    echo sprintf("Debug Mode:         %s\n", $config['development']['debug'] ? 'Yes' : 'No');
    echo sprintf("Dry Run:            %s\n", $config['development']['dry_run'] ? 'Yes' : 'No');
    
    echo str_repeat("=", 50) . "\n";
    
    if (!$configured) {
        echo "\n⚠️  WARNING: AI Provider is not properly configured!\n";
        echo "Please set API_KEY in .env and restart the service.\n";
    } else {
        echo "\n✓ Configuration looks good!\n";
    }
    echo "\n";
}

/**
 * Show recent logs
 */
function commandLogsTail(array $config, array $argv): void
{
    $lines = 20;
    if (($key = array_search('--lines', $argv)) !== false && isset($argv[$key + 1])) {
        $lines = intval($argv[$key + 1]);
    }
    
    $logFile = $config['logging']['log_dir'] . '/qmail-ai-filter.log';
    
    if (!file_exists($logFile)) {
        echo "No log file found: {$logFile}\n";
        return;
    }
    
    echo "\nRecent Log Entries (last {$lines} lines):\n";
    echo str_repeat("=", 70) . "\n";
    
    $content = file_get_contents($logFile);
    $logLines = explode("\n", $content);
    $recentLines = array_slice($logLines, -($lines + 1), $lines);
    
    foreach ($recentLines as $line) {
        if (!empty($line)) {
            echo $line . "\n";
        }
    }
    
    echo str_repeat("=", 70) . "\n\n";
}

/**
 * Check user folders
 */
function commandFoldersCheck(array $config, array $argv): void
{
    $userDomain = $argv[2] ?? null;
    
    if (!$userDomain) {
        echo "Usage: php manage.php folders:check <user@domain>\n";
        return;
    }
    
    $logger = new Logger($config['logging']['log_dir']);
    $mailboxManager = new MailboxManager($config['qmail']['mailbox_root'], $logger);
    
    echo "\nChecking mailbox for: {$userDomain}\n";
    echo str_repeat("=", 50) . "\n";
    
    $folders = $mailboxManager->getUserFolders($userDomain);
    
    if (empty($folders)) {
        echo "User mailbox not found or no folders.\n";
        return;
    }
    
    echo "Folders:\n";
    foreach ($folders as $folder) {
        $isSpam = ($folder === '.Spam') ? ' (SPAM)' : '';
        echo "  {$folder}{$isSpam}\n";
    }
    
    $hasSpam = $mailboxManager->hasSpamFolder($userDomain);
    echo "\n.Spam Folder: " . ($hasSpam ? 'Exists ✓' : 'Not found ✗') . "\n";
    
    echo str_repeat("=", 50) . "\n\n";
}
