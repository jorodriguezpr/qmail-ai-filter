#!/usr/bin/env php
<?php
/**
 * QMAIL AI Filter - Synchronous Filter
 * 
 * This script performs immediate AI spam analysis for email filtering.
 * Called by the wrapper script for real-time decision making.
 * 
 * Usage: php sync-filter.php <email-path> <recipient-email>
 * Output: JSON with is_spam, confidence, reason
 */

// Auto-detect installation directory
$installDir = dirname(__DIR__);

try {
    // Load autoloader
    require_once $installDir . '/src/Autoloader.php';
    Autoloader::register();
    
    // Load config
    $config = require $installDir . '/config/config.php';
    
    // Get command line arguments
    $emailPath = $argv[1] ?? null;
    $recipientEmail = $argv[2] ?? null;
    
    if (!$emailPath || !file_exists($emailPath)) {
        echo json_encode(['error' => 'Email file not found', 'is_spam' => false]);
        exit(1);
    }
    
    if (!$recipientEmail) {
        echo json_encode(['error' => 'Recipient email required', 'is_spam' => false]);
        exit(1);
    }
    
    // Initialize logger
    $logger = new \QmailAiFilter\Logging\Logger(
        $config['logging']['log_dir'],
        $config['logging']['level']
    );
    
    $logger->info("Synchronous filter called", [
        'email_path' => $emailPath,
        'recipient' => $recipientEmail
    ]);
    
    // Parse email
    $emailParser = new \QmailAiFilter\Email\EmailParser($emailPath, $logger);
    $emailData = $emailParser->getEmailData();
    
    // Initialize AI provider
    $providerName = $config['ai_provider']['provider'];
    $aiProvider = null;
    
    if ($providerName === 'github_copilot') {
        $apiKey = $config['ai_provider']['github_copilot']['api_key'] ?? '';
        $model = $config['ai_provider']['github_copilot']['model'] ?? 'gpt-4o';
        
        $aiProvider = new \QmailAiFilter\AI\Providers\GitHubCopilotProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } elseif ($providerName === 'openai') {
        $apiKey = $config['ai_provider']['openai']['api_key'] ?? '';
        $model = $config['ai_provider']['openai']['model'] ?? 'gpt-3.5-turbo';
        
        $aiProvider = new \QmailAiFilter\AI\Providers\OpenAIProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    } elseif ($providerName === 'claude' || $providerName === 'claude_anthropic') {
        $apiKey = $config['ai_provider']['claude_anthropic']['api_key'] ?? '';
        $model = $config['ai_provider']['claude_anthropic']['model'] ?? 'claude-3-haiku-20240307';
        
        $aiProvider = new \QmailAiFilter\AI\Providers\ClaudeProvider(
            $apiKey,
            $model,
            $logger,
            $config['spam_detection']['api_timeout'] ?? 30
        );
    }
    
    if (!$aiProvider || !$aiProvider->isConfigured()) {
        $logger->error("AI provider not configured: {$providerName}");
        echo json_encode(['error' => 'AI provider not configured', 'is_spam' => false]);
        exit(1);
    }
    
    // Analyze email
    $result = $aiProvider->analyzeEmail($emailData);
    
    // Apply confidence threshold
    $threshold = $config['spam_detection']['confidence_threshold'];
    $isSpam = $result['is_spam'] && $result['confidence'] >= $threshold;
    
    // Log result
    $logger->info("Synchronous filter result", [
        'recipient' => $recipientEmail,
        'is_spam' => $isSpam,
        'confidence' => $result['confidence'],
        'reason' => $result['reason']
    ]);
    
    // Output JSON result
    echo json_encode([
        'is_spam' => $isSpam,
        'confidence' => $result['confidence'],
        'reason' => $result['reason'],
        'spam_type' => $result['spam_type'] ?? 'unknown'
    ]);
    
    exit(0);
    
} catch (Throwable $e) {
    if (isset($logger)) {
        $logger->error("Sync filter error: " . $e->getMessage());
    }
    // On error, default to allowing email (fail open)
    echo json_encode(['error' => $e->getMessage(), 'is_spam' => false]);
    exit(1);
}
