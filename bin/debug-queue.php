#!/usr/bin/env php
<?php
/**
 * Debug Queue Processor - Shows what's happening
 */

echo "=== QMAIL AI Filter Debug ===\n";
echo "PHP Version: " . phpversion() . "\n";

// Check if installation directory exists
// Auto-detect installation directory
$installDir = dirname(__DIR__);
echo "Installation dir: $installDir\n";
echo "Exists: " . (is_dir($installDir) ? "YES" : "NO") . "\n";

// Check autoloader
$autoloader = $installDir . '/src/Autoloader.php';
echo "\nAutoloader: $autoloader\n";
echo "Exists: " . (is_file($autoloader) ? "YES" : "NO") . "\n";

if (!is_file($autoloader)) {
    echo "ERROR: Autoloader not found!\n";
    exit(1);
}

// Load autoloader
require_once $autoloader;
echo "Autoloader loaded successfully\n";

// Check config
$configFile = $installDir . '/config/config.php';
echo "\nConfig file: $configFile\n";
echo "Exists: " . (is_file($configFile) ? "YES" : "NO") . "\n";

if (!is_file($configFile)) {
    echo "ERROR: Config file not found!\n";
    exit(1);
}

// Load config
$config = require $configFile;
echo "Config loaded successfully\n";

// Check queue directory
$queueDir = $config['queue']['queue_dir'];
echo "\nQueue directory: $queueDir\n";
echo "Exists: " . (is_dir($queueDir) ? "YES" : "NO") . "\n";

// List queue files
if (is_dir($queueDir)) {
    $files = glob($queueDir . '/*.json');
    echo "Queue files: " . count($files) . "\n";
    if (!empty($files)) {
        foreach (array_slice($files, 0, 3) as $file) {
            echo "  - " . basename($file) . "\n";
        }
    }
}

// Check API configuration
echo "\nAPI Provider: " . $config['ai_provider']['provider'] . "\n";

$provider = $config['ai_provider']['provider'];
if ($provider === 'github_copilot') {
    $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? 'NOT SET';
    echo "GitHub Copilot API Key: " . (empty($apiKey) ? "NOT SET" : "Set (length: " . strlen($apiKey) . ")") . "\n";
    echo "GitHub Copilot Model: " . ($config['ai_provider']['github_copilot']['model'] ?? 'NOT SET') . "\n";
}

// Try to initialize logger
echo "\nInitializing Logger...\n";
try {
    $logger = new \QmailAiFilter\Logging\Logger(
        $config['logging']['log_dir'],
        $config['logging']['level']
    );
    echo "Logger initialized successfully\n";
    $logger->info("Debug script running");
} catch (Exception $e) {
    echo "ERROR initializing logger: " . $e->getMessage() . "\n";
    exit(1);
}

// Try to initialize queue manager
echo "Initializing Queue Manager...\n";
try {
    $queueManager = new \QmailAiFilter\Queue\QueueManager(
        $config['queue']['queue_dir'],
        $logger,
        $config['queue']['max_retries'],
        $config['queue']['retry_delay']
    );
    echo "Queue Manager initialized successfully\n";
} catch (Exception $e) {
    echo "ERROR initializing queue manager: " . $e->getMessage() . "\n";
    exit(1);
}

// Get pending items
echo "\nGetting pending items...\n";
try {
    $pending = $queueManager->getPending(10);
    echo "Pending items: " . count($pending) . "\n";
    
    if (!empty($pending)) {
        foreach ($pending as $index => $item) {
            echo "\n  Item " . ($index + 1) . ":\n";
            echo "    File: " . basename($item['file']) . "\n";
            echo "    ID: " . ($item['data']['id'] ?? 'N/A') . "\n";
            echo "    Recipient: " . ($item['data']['qmail_recipient'] ?? 'N/A') . "\n";
            echo "    Status: " . ($item['data']['status'] ?? 'N/A') . "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR getting pending items: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Debug complete ===\n";
