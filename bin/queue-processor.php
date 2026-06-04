#!/usr/bin/env php
<?php
/**
 * QMAIL AI Filter - Queue Processor
 * 
 * This script runs asynchronously to process queued emails with AI spam detection.
 * 
 * Usage: php queue-processor.php [--limit 10] [--dry-run]
 * 
 * Recommended cron job:
 * Every 5 minutes: /usr/bin/php /path/to/queue-processor.php >> /var/log/qmail-ai-filter-queue.log 2>&1
 */

// Auto-detect installation directory
$installDir = dirname(__DIR__);

try {
    // Load autoloader
    require_once $installDir . '/src/Autoloader.php';
    Autoloader::register();
    
    // Load config
    $config = require $installDir . '/config/config.php';
    
    // Parse command line arguments
    $options = getopt('', ['limit:', 'dry-run', 'provider:']);
    $limit = intval($options['limit'] ?? $config['queue']['max_concurrent']);
    $dryRun = isset($options['dry-run']) || $config['development']['dry_run'];
    $providerName = $options['provider'] ?? $config['ai_provider']['provider'];
    
    // Initialize logger
    $logger = new \QmailAiFilter\Logging\Logger(
        $config['logging']['log_dir'],
        $config['logging']['level']
    );
    
    $logger->info("Queue processor started", ['limit' => $limit, 'dry_run' => $dryRun]);
    
    // Initialize queue manager
    $queueManager = new \QmailAiFilter\Queue\QueueManager(
        $config['queue']['queue_dir'],
        $logger,
        $config['queue']['max_retries'],
        $config['queue']['retry_delay']
    );
    
    // Get pending
    $pending = $queueManager->getPending($limit);
    
    if (empty($pending)) {
        $logger->debug("No pending emails in queue");
        $stats = $queueManager->getStats();
        $logger->info("Queue statistics", $stats);
        exit(0);
    }
    
    $logger->info("Processing queue items", ['count' => count($pending)]);
    
    // Initialize mailbox manager
    $mailboxManager = new \QmailAiFilter\Email\MailboxManager(
        $config['qmail']['mailbox_root'],
        $logger,
        $dryRun
    );
    
    // Initialize AI provider based on provider name
    $aiProvider = null;
    
    if ($providerName === 'github_copilot') {
        $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? '';
        $model = $config['ai_provider']['github_copilot']['model'] ?? 'claude-3.7-sonnet';
        
        if (empty($apiKey)) {
            $logger->error("GitHub Copilot API key not configured");
            exit(1);
        }
        
        $aiProvider = new \QmailAiFilter\AI\Providers\GitHubCopilotProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } elseif ($providerName === 'openai') {
        $apiKey = $config['ai_provider']['openai']['api_key'] ?? '';
        $model = $config['ai_provider']['openai']['model'] ?? 'gpt-3.5-turbo';
        
        if (empty($apiKey)) {
            $logger->error("OpenAI API key not configured");
            exit(1);
        }
        
        $aiProvider = new \QmailAiFilter\AI\Providers\OpenAIProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } elseif ($providerName === 'claude' || $providerName === 'claude_anthropic') {
        $apiKey = $config['ai_provider']['claude_anthropic']['api_key'] ?? '';
        $model = $config['ai_provider']['claude_anthropic']['model'] ?? 'claude-3-haiku-20240307';
        
        if (empty($apiKey)) {
            $logger->error("Claude API key not configured");
            exit(1);
        }
        
        $aiProvider = new \QmailAiFilter\AI\Providers\ClaudeProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } elseif ($providerName === 'ollama-cloud') {
        $apiKey = $config['ai_provider']['ollama-cloud']['api_key'] ?? '';
        $model = $config['ai_provider']['ollama-cloud']['model'] ?? 'glm-5.1';

        if (empty($apiKey)) {
            $logger->error("Ollama Cloud API key not configured");
            exit(1);
        }

        $aiProvider = new \QmailAiFilter\AI\Providers\OllamaCloudProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } else {
        $logger->error("Unknown AI provider: " . $providerName);
        exit(1);
    }
    
    if (!$aiProvider->isConfigured()) {
        $logger->error("AI provider not configured: {$providerName}");
        exit(1);
    }
    
    // Initialize filter engine
    $filterEngine = new \QmailAiFilter\Filter\SpamFilterEngine(
        $aiProvider,
        $mailboxManager,
        $logger,
        $config['spam_detection']['confidence_threshold'],
        $dryRun,
        $config['qmail']['preserve_original']
    );
    
    // Process each email
    $successCount = 0;
    $failCount = 0;
    
    foreach ($pending as $queueItem) {
        $queueId = $queueItem['data']['id'] ?? null;
        $emailPath = $queueItem['data']['email_path'] ?? null;
        $userDomain = $queueItem['data']['user_domain'] ?? null;
        
        if (!$queueId || !$emailPath || !$userDomain) {
            $logger->warning("Invalid queue item, skipping", ['file' => $queueItem['file']]);
            continue;
        }
        
        try {
            // Mark as processing
            $queueManager->markProcessing($queueId);
            
            // Process email
            $result = $filterEngine->processEmail($emailPath, $userDomain);
            
            // Mark as completed
            $queueManager->markCompleted($queueId, $result);
            
            // Cleanup temp file
            if (file_exists($emailPath) && strpos($emailPath, sys_get_temp_dir()) === 0) {
                @unlink($emailPath);
            }
            
            $successCount++;
        } catch (\Exception $e) {
            $logger->error("Error processing queue item: " . $e->getMessage(), ['id' => $queueId]);
            $queueManager->markFailed($queueId, $e->getMessage());
            $failCount++;
        }
    }
    
    // Clean old queue items
    $queueManager->cleanup();
    
    // Final statistics
    $stats = $queueManager->getStats();
    $logger->info("Queue processor completed", array_merge($stats, [
        'processed_success' => $successCount,
        'processed_failed' => $failCount,
    ]));
    
    exit(0);
    
} catch (Throwable $e) {
    if (isset($logger)) {
        $logger->error("Fatal error in queue processor: " . $e->getMessage());
    }
    exit(1);
}
