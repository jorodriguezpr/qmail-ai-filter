#!/usr/bin/env php
<?php
/**
 * Queue Processor Debug - Trace exact issue
 */

echo "[DEBUG] Queue Processor Debug Started\n";
echo "[DEBUG] Time: " . date('Y-m-d H:i:s') . "\n\n";

// Auto-detect installation directory
$installDir = dirname(__DIR__);

try {
    // Load autoloader
    echo "[STEP 1] Loading autoloader\n";
    require_once $installDir . '/src/Autoloader.php';
    Autoloader::register();
    echo "[OK]\n\n";
    
    // Load config
    echo "[STEP 2] Loading configuration\n";
    $config = require $installDir . '/config/config.php';
    echo "[OK]\n";
    echo "  Provider: " . $config['ai_provider']['provider'] . "\n";
    echo "  Queue dir: " . $config['queue']['queue_dir'] . "\n\n";
    
    // Initialize logger
    echo "[STEP 3] Initializing Logger\n";
    $logger = new \QmailAiFilter\Logging\Logger(
        $config['logging']['log_dir'],
        $config['logging']['level']
    );
    echo "[OK]\n";
    $logger->info("===== DEBUG QUEUE PROCESSOR STARTED =====");
    echo "  Logging to: " . $config['logging']['log_dir'] . "\n\n";
    
    // Initialize queue manager
    echo "[STEP 4] Initializing Queue Manager\n";
    $queueManager = new \QmailAiFilter\Queue\QueueManager(
        $config['queue']['queue_dir'],
        $logger,
        $config['queue']['max_retries'],
        $config['queue']['retry_delay']
    );
    echo "[OK]\n\n";
    
    // Get pending
    echo "[STEP 5] Getting pending items\n";
    $pending = $queueManager->getPending(1);
    echo "  Found: " . count($pending) . " items\n";
    
    if (empty($pending)) {
        echo "[INFO] No pending items to process\n";
        $logger->info("Debug: No pending items found");
        exit(0);
    }
    
    $item = $pending[0];
    echo "  ID: " . $item['data']['id'] . "\n";
    echo "  Email Path: " . $item['data']['email_path'] . "\n";
    echo "  File exists: " . (file_exists($item['data']['email_path']) ? "YES" : "NO") . "\n";
    echo "  User Domain: " . $item['data']['user_domain'] . "\n\n";
    
    if (!file_exists($item['data']['email_path'])) {
        echo "[ERROR] Email file not found!\n";
        $logger->error("Email file missing: " . $item['data']['email_path']);
        exit(1);
    }
    
    // Initialize mailbox manager
    echo "[STEP 6] Initializing Mailbox Manager\n";
    $mailboxManager = new \QmailAiFilter\Email\MailboxManager(
        $config['qmail']['mailbox_root'],
        $logger,
        false
    );
    echo "[OK]\n\n";
    
    // Initialize AI provider
    echo "[STEP 7] Initializing AI Provider\n";
    $provider = $config['ai_provider']['provider'];
    
    if ($provider === 'github_copilot') {
        $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? '';
        $model = $config['ai_provider']['github_copilot']['model'] ?? 'claude-3.7-sonnet';
        echo "  Provider: GitHub Copilot\n";
        echo "  Model: " . $model . "\n";
        echo "  API Key set: " . (empty($apiKey) ? "NO" : "YES") . "\n";
        
        $aiProvider = new \QmailAiFilter\AI\Providers\GitHubCopilotProvider(
            $apiKey,
            $model,
            $logger,
            30
        );
    } else {
        echo "[ERROR] Unknown provider: " . $provider . "\n";
        exit(1);
    }
    
    echo "  Configured: " . ($aiProvider->isConfigured() ? "YES" : "NO") . "\n";
    echo "[OK]\n\n";
    
    // Initialize filter engine
    echo "[STEP 8] Initializing Filter Engine\n";
    $filterEngine = new \QmailAiFilter\Filter\SpamFilterEngine(
        $aiProvider,
        $mailboxManager,
        $logger,
        $config['spam_detection']['confidence_threshold'],
        false,
        $config['qmail']['preserve_original']
    );
    echo "[OK]\n\n";
    
    // Process the email
    echo "[STEP 9] Processing email\n";
    $queueId = $item['data']['id'];
    $emailPath = $item['data']['email_path'];
    $userDomain = $item['data']['user_domain'];
    
    // Mark as processing
    echo "  Marking as processing...\n";
    $queueManager->markProcessing($queueId);
    $logger->info("Marked as processing: " . $queueId);
    
    // Process email
    echo "  Running filter engine...\n";
    $result = $filterEngine->processEmail($emailPath, $userDomain);
    
    echo "  Processing complete:\n";
    echo "    Is Spam: " . ($result['is_spam'] ? "YES" : "NO") . "\n";
    echo "    Confidence: " . $result['confidence'] . "\n";
    echo "    Moved to Spam: " . ($result['moved_to_spam'] ? "YES" : "NO") . "\n";
    
    // Mark as completed
    echo "  Marking as completed...\n";
    $queueManager->markCompleted($queueId, $result);
    $logger->info("Marked as completed: " . $queueId);
    
    // Cleanup temp file
    if (file_exists($emailPath) && strpos($emailPath, sys_get_temp_dir()) === 0) {
        echo "  Cleaning up temp file...\n";
        @unlink($emailPath);
    }
    
    echo "[OK]\n\n";
    echo "[SUCCESS] Email processed successfully!\n";
    $logger->info("===== DEBUG QUEUE PROCESSOR COMPLETED =====");
    
} catch (Throwable $e) {
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "[FILE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "[TRACE]\n" . $e->getTraceAsString() . "\n";
    
    if (isset($logger)) {
        $logger->error("Fatal error: " . $e->getMessage());
    }
    exit(1);
}
