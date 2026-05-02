#!/usr/bin/env php
<?php
/**
 * Debug Queue Processor - Detailed tracing
 */

echo "[DEBUG] Starting queue processor debug...\n";
echo "[DEBUG] PHP Version: " . phpversion() . "\n";
echo "[DEBUG] Current user: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "[DEBUG] Working directory: " . getcwd() . "\n\n";

// Auto-detect installation directory
$installDir = dirname(__DIR__);

// Step 1: Load autoloader
echo "[STEP 1] Loading autoloader...\n";
try {
    require_once $installDir . '/src/Autoloader.php';
    \QmailAiFilter\Autoloader::register();
    echo "[OK] Autoloader loaded\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to load autoloader: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Load config
echo "[STEP 2] Loading configuration...\n";
try {
    $config = require $installDir . '/config/config.php';
    echo "[OK] Config loaded\n";
    echo "  Queue Dir: " . $config['queue']['queue_dir'] . "\n";
    echo "  Provider: " . $config['ai_provider']['provider'] . "\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to load config: " . $e->getMessage() . "\n";
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
    $logger->info("=== DEBUG QUEUE PROCESSOR STARTED ===");
    echo "  Log dir: " . $config['logging']['log_dir'] . "\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to initialize logger: " . $e->getMessage() . "\n";
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
} catch (Exception $e) {
    echo "[ERROR] Failed to initialize queue manager: " . $e->getMessage() . "\n";
    $logger->error("Queue Manager init failed: " . $e->getMessage());
    exit(1);
}

// Step 5: Get pending items
echo "[STEP 5] Getting pending items...\n";
try {
    $pending = $queueManager->getPending(10);
    echo "[OK] Found " . count($pending) . " pending items\n";
    
    if (empty($pending)) {
        echo "[INFO] No pending items to process\n";
        exit(0);
    }
    
    echo "\nPending items:\n";
    foreach ($pending as $index => $item) {
        echo "  [" . ($index + 1) . "] ID: " . $item['data']['id'] . "\n";
        echo "      Email Path: " . $item['data']['email_path'] . "\n";
        echo "      Exists: " . (file_exists($item['data']['email_path']) ? "YES" : "NO") . "\n";
        echo "      User Domain: " . $item['data']['user_domain'] . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to get pending items: " . $e->getMessage() . "\n";
    $logger->error("Get pending failed: " . $e->getMessage());
    exit(1);
}

// Step 6: Initialize AI Provider
echo "[STEP 6] Initializing AI Provider...\n";
try {
    $providerName = $config['ai_provider']['provider'];
    echo "  Provider name: " . $providerName . "\n";
    
    if ($providerName === 'github_copilot') {
        $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? null;
        echo "  API Key set: " . (empty($apiKey) ? "NO" : "YES (length: " . strlen($apiKey) . ")") . "\n";
        
        if (empty($apiKey)) {
            echo "[ERROR] GitHub Copilot API key not configured!\n";
            exit(1);
        }
        
        $aiProvider = new \QmailAiFilter\AI\Providers\GitHubCopilotProvider(
            $apiKey,
            $config['ai_provider']['github_copilot']['model'] ?? 'claude-3.7-sonnet',
            $logger,
            30
        );
    } else {
        echo "[ERROR] Unknown provider: " . $providerName . "\n";
        exit(1);
    }
    
    echo "  Configured: " . ($aiProvider->isConfigured() ? "YES" : "NO") . "\n";
    echo "[OK] AI Provider initialized\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to initialize AI provider: " . $e->getMessage() . "\n";
    $logger->error("AI Provider init failed: " . $e->getMessage());
    exit(1);
}

// Step 7: Try to process first item
if (!empty($pending)) {
    echo "[STEP 7] Testing email analysis on first item...\n";
    try {
        $firstItem = $pending[0];
        $emailPath = $firstItem['data']['email_path'];
        
        echo "  Reading email from: " . $emailPath . "\n";
        
        if (!file_exists($emailPath)) {
            echo "[ERROR] Email file not found: " . $emailPath . "\n";
            exit(1);
        }
        
        $emailContent = file_get_contents($emailPath);
        echo "  Email size: " . strlen($emailContent) . " bytes\n";
        
        if (strlen($emailContent) < 50) {
            echo "[WARNING] Email seems too small\n";
        }
        
        echo "  Testing API call...\n";
        $result = $aiProvider->analyzeEmail($emailContent);
        
        echo "[OK] Analysis completed\n";
        echo "  Is Spam: " . ($result['is_spam'] ? "YES" : "NO") . "\n";
        echo "  Confidence: " . $result['confidence'] . "\n";
        echo "  Details: " . $result['details'] . "\n";
        
    } catch (Exception $e) {
        echo "[ERROR] Failed to analyze email: " . $e->getMessage() . "\n";
        $logger->error("Email analysis failed: " . $e->getMessage());
        exit(1);
    }
}

echo "\n[SUCCESS] All checks passed! Queue processor should work.\n";
