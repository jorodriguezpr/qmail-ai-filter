#!/usr/bin/env php
<?php
/**
 * QMAIL AI Filter - Main Entry Point
 * 
 * This script intercepts emails from QMAIL and queues them for AI-based spam analysis.
 * 
 * Usage: qmail-ai-filter <email_file> <recipient_user@domain>
 * 
 * Integration with QMAIL:
 * Add to .qmail-default or specific user .qmail file:
 * | /path/to/qmail-ai-filter.php
 */

// Set up autoloader
require_once __DIR__ . '/../src/Autoloader.php';
Autoloader::register();

use QmailAiFilter\Queue\QueueManager;
use QmailAiFilter\Logging\Logger;

// Configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize logger
$logger = new Logger(
    $config['logging']['log_dir'],
    $config['logging']['level']
);

try {
    // Get email from stdin
    $emailContent = file_get_contents('php://stdin');
    
    if (empty($emailContent)) {
        $logger->error("No email content received from QMAIL");
        exit(0); // Don't bounce
    }
    
    // Get recipient information
    $recipient = $_SERVER['argv'][1] ?? $_ENV['RECIPIENT'] ?? 'unknown@example.com';
    
    // Create temporary email file
    $tempDir = sys_get_temp_dir();
    $emailFile = tempnam($tempDir, 'qmail_');
    
    if (file_put_contents($emailFile, $emailContent) === false) {
        $logger->error("Failed to write email to temporary file");
        exit(0);
    }
    
    // Queue email for asynchronous processing
    $queueManager = new QueueManager(
        $config['queue']['queue_dir'],
        $logger,
        $config['queue']['max_retries'],
        $config['queue']['retry_delay']
    );
    
    $metadata = [
        'original_stdin' => true,
        'qmail_recipient' => $recipient,
        'processing_initiated_at' => date('Y-m-d H:i:s'),
    ];
    
    if ($queueManager->enqueue($emailFile, $recipient, $metadata)) {
        $logger->debug("Email queued successfully", ['recipient' => $recipient]);
        exit(0); // Success - continue mail delivery
    } else {
        $logger->error("Failed to queue email", ['recipient' => $recipient]);
        unlink($emailFile);
        exit(0); // Don't bounce on queue failure
    }
} catch (\Exception $e) {
    $logger->error("Fatal error in filter: " . $e->getMessage());
    exit(0); // Don't bounce on error
}
