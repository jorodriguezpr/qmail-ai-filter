<?php
/**
 * QMAIL AI Filter - Ollama Cloud Provider
 *
 * Uses Ollama Cloud API models to analyze emails for spam.
 *
 * @package    QmailAiFilter\AI\Providers
 */

namespace QmailAiFilter\AI\Providers;

use QmailAiFilter\AI\AIProviderInterface;
use QmailAiFilter\Logging\Logger;

class OllamaCloudProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private Logger $logger;
    private int $timeout;

    public function __construct(string $apiKey, string $model = 'glm-5.1', Logger $logger = null, int $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->apiUrl = 'https://ollama.com/api/chat';
        $this->logger = $logger ?? new Logger();
        $this->timeout = $timeout;
    }

    public function analyzeEmail(array $emailData): array
    {
        if (!$this->isConfigured()) {
            return [
                'is_spam' => false,
                'confidence' => 0,
                'reason' => 'Ollama Cloud provider not configured',
                'error' => true,
            ];
        }

        try {
            $prompt = $this->buildSpamAnalysisPrompt($emailData);
            $response = $this->callOllamaCloudAPI($prompt);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Ollama Cloud API error: ' . $e->getMessage());
            return [
                'is_spam' => false,
                'confidence' => 0,
                'reason' => 'Error analyzing email',
                'error' => true,
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getProviderName(): string
    {
        return 'Ollama Cloud';
    }

    private function buildSpamAnalysisPrompt(array $emailData): string
    {
        $subject = $emailData['subject'] ?? '';
        $from = $emailData['from'] ?? '';
        $body = substr($emailData['body'] ?? '', 0, 2000);
        $hasAttachments = !empty($emailData['attachments']);
        $hasUrls = !empty($emailData['urls']);

        return <<<PROMPT
Analyze the following email for spam characteristics and respond with a JSON object only:

FROM: {$from}
SUBJECT: {$subject}
HAS_ATTACHMENTS: {$hasAttachments}
HAS_URLS: {$hasUrls}

EMAIL BODY:
{$body}

---

Analyze this email for spam indicators. Consider:
- Suspicious sender or domain
- Common spam keywords and patterns
- Urgency or threat-based language
- Phishing attempts
- Financial/banking scams
- Too-good-to-be-true offers
- Malicious links or attachments

Respond ONLY with valid JSON (no markdown, no code blocks):
{
  "is_spam": true/false,
  "confidence": 0.0-1.0,
  "reason": "brief explanation",
  "spam_type": "phishing|scam|malware|marketing|other|none"
}
PROMPT;
    }

    private function callOllamaCloudAPI(string $prompt): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a spam detection expert. Analyze emails and respond only with JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false,
            'options' => [
                'temperature' => 0.3,
            ],
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            $errorMsg = "Ollama Cloud API failed: HTTP {$httpCode}";
            if ($error) {
                $errorMsg .= ", cURL Error: {$error}";
            }
            if ($response) {
                $errorMsg .= ", Response: " . substr($response, 0, 200);
            }
            throw new \Exception($errorMsg);
        }

        return json_decode($response, true) ?? [];
    }

    private function parseResponse(array $response): array
    {
        try {
            $content = $response['message']['content']
                ?? ($response['choices'][0]['message']['content'] ?? '');

            $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
            $analysis = json_decode($content, true);

            if (!$analysis) {
                throw new \Exception('Invalid JSON response');
            }

            return [
                'is_spam' => (bool)($analysis['is_spam'] ?? false),
                'confidence' => (float)($analysis['confidence'] ?? 0.0),
                'reason' => $analysis['reason'] ?? 'No reason provided',
                'spam_type' => $analysis['spam_type'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse Ollama Cloud response: ' . $e->getMessage());
            return [
                'is_spam' => false,
                'confidence' => 0,
                'reason' => 'Failed to parse response',
                'error' => true,
            ];
        }
    }
}