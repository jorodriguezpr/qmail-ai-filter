#!/usr/bin/env php
<?php
/**
 * Simple Queue Processor Debug
 */

echo "[DEBUG] PHP Version: " . phpversion() . "\n";
echo "[DEBUG] Working directory: " . getcwd() . "\n";
echo "[DEBUG] Script started\n\n";

// Auto-detect installation directory
$installDir = dirname(__DIR__);

// Step 1: Load autoloader
echo "[STEP 1] Loading autoloader...\n";
if (!file_exists($installDir . '/src/Autoloader.php')) {
    echo "[ERROR] Autoloader not found\n";
    exit(1);
}

try {
    require_once $installDir . '/src/Autoloader.php';
    Autoloader::register();
    echo "[OK] Autoloader loaded\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 2: Load config
echo "[STEP 2] Loading configuration...\n";
try {
    $config = require $installDir . '/config/config.php';
    echo "[OK] Config loaded\n";
    echo "  Queue Dir: " . ($config['queue']['queue_dir'] ?? 'NOT SET') . "\n";
    echo "  Provider: " . ($config['ai_provider']['provider'] ?? 'NOT SET') . "\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 3: Initialize Logger
echo "[STEP 3] Initializing Logger...\n";
try {
    $logger = new \QmailAiFilter\Logging\Logger(
        $config['logging']['log_dir'],
        $config['logging']['level']
    );
    echo "[OK] Logger initialized\n";
    $logger->info("Debug script started");
    echo "  Log dir: " . $config['logging']['log_dir'] . "\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 4: Initialize Queue Manager
echo "[STEP 4] Initializing Queue Manager...\n";
try {
    $queueManager = new \QmailAiFilter\Queue\QueueManager(
        $config['queue']['queue_dir'],
        $logger,
        $config['queue']['max_retries'],
        $config['queue']['retry_delay']
    );
    echo "[OK] Queue Manager initialized\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 5: Get pending items
echo "[STEP 5] Getting pending items...\n";
try {
    $pending = $queueManager->getPending(1);
    echo "[OK] Found " . count($pending) . " pending items\n";
    
    if (empty($pending)) {
        echo "[INFO] No pending items\n";
        exit(0);
    }
    
    $item = $pending[0];
    echo "  ID: " . $item['data']['id'] . "\n";
    echo "  Email Path: " . $item['data']['email_path'] . "\n";
    echo "  File Exists: " . (file_exists($item['data']['email_path']) ? "YES" : "NO") . "\n";
    echo "  User Domain: " . $item['data']['user_domain'] . "\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 6: Initialize AI Provider
echo "[STEP 6] Initializing GitHub Copilot Provider...\n";
try {
    $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? null;
    $model = $config['ai_provider']['github_copilot']['model'] ?? 'claude-3.7-sonnet';
    
    echo "  API Key set: " . (empty($apiKey) ? "NO" : "YES") . "\n";
    echo "  Model: " . $model . "\n";
    
    if (empty($apiKey)) {
        echo "[ERROR] API key not set!\n";
        exit(1);
    }
    
    $aiProvider = new \QmailAiFilter\AI\Providers\GitHubCopilotProvider(
        $apiKey,
        $model,
        $logger,
        30
    );
    
    echo "  Configured: " . ($aiProvider->isConfigured() ? "YES" : "NO") . "\n";
    echo "[OK] Provider initialized\n\n";
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 7: Test email analysis
echo "[STEP 7] Testing email analysis...\n";
try {
    $emailPath = $item['data']['email_path'];
    echo "  Reading: " . $emailPath . "\n";
    
    // Parse email
    echo "  Parsing email...\n";
    $parser = new \QmailAiFilter\Email\EmailParser($emailPath);
    $emailData = $parser->getEmailData();
    echo "  Parsed email: subject='" . $emailData['subject'] . "'\n";
    echo "  Body size: " . strlen($emailData['body']) . " bytes\n";
    
    echo "  Calling analyzeEmail()...\n";
    $result = $aiProvider->analyzeEmail($emailData);
    
    echo "[OK] Analysis completed\n";
    echo "  Is Spam: " . ($result['is_spam'] ? "YES" : "NO") . "\n";
    echo "  Confidence: " . $result['confidence'] . "\n";
    echo "  Details: " . substr($result['details'], 0, 100) . "...\n\n";
    
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "[SUCCESS] Everything works!\n";
