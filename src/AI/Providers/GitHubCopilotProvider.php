<?php
/**
 * QMAIL AI Filter - GitHub Copilot Provider
 * 
 * Uses GitHub Copilot's latest models (2026+) to analyze emails for spam
 * Powered by advanced AI models with GitHub's optimizations
 * 
 * @package    QmailAiFilter\AI\Providers
 * @author     Jose Rodriguez Arroyo <jrpcone@gmail.com>
 * @copyright  2026 Jose Rodriguez Arroyo
 * @license    MIT License
 * @link       https://github.com/jorodriguezpr/qmail-ai-filter
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace QmailAiFilter\AI\Providers;

use QmailAiFilter\AI\AIProviderInterface;
use QmailAiFilter\Logging\Logger;

class GitHubCopilotProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private Logger $logger;
    private int $timeout;
    
    public function __construct(string $apiKey, string $model = 'gpt-4o', Logger $logger = null, int $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->apiUrl = 'https://models.inference.ai.azure.com/chat/completions';
        $this->logger = $logger ?? new Logger();
        $this->timeout = $timeout;
    }
    
    public function analyzeEmail(array $emailData): array
    {
        if (!$this->isConfigured()) {
            return [
                'is_spam' => false,
                'confidence' => 0,
                'reason' => 'GitHub Copilot provider not configured',
                'error' => true,
            ];
        }
        
        try {
            $this->logger->debug("Calling GitHub Models API", [
                'model' => $this->model,
                'endpoint' => $this->apiUrl
            ]);
            
            $prompt = $this->buildSpamAnalysisPrompt($emailData);
            $response = $this->callCopilotAPI($prompt);
            
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('GitHub Copilot API error: ' . $e->getMessage());
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
        return 'GitHub Copilot';
    }
    
    /**
     * Build prompt for spam analysis
     */
    private function buildSpamAnalysisPrompt(array $emailData): string
    {
        $subject = $emailData['subject'] ?? '';
        $from = $emailData['from'] ?? '';
        $body = substr($emailData['body'] ?? '', 0, 2000); // Limit body length
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
    
    /**
     * Call GitHub Copilot API
     */
    private function callCopilotAPI(string $prompt): array
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
            'temperature' => 0.3,
            'max_tokens' => 500,
        ];
        
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: QMAIL-AI-Filter/1.0',
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
            $errorMsg = "GitHub Copilot API failed: HTTP {$httpCode}";
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
    
    /**
     * Parse GitHub Copilot response
     */
    private function parseResponse(array $response): array
    {
        try {
            // Handle standard Chat Completions format
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                
                // Extract JSON from potential markdown code blocks
                if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
                    $content = $matches[1];
                } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
                    $content = $matches[1];
                }
                
                $parsed = json_decode($content, true);
                
                if (is_array($parsed)) {
                    return [
                        'is_spam' => (bool) ($parsed['is_spam'] ?? false),
                        'confidence' => (float) ($parsed['confidence'] ?? 0.5),
                        'reason' => (string) ($parsed['reason'] ?? 'Analysis complete'),
                        'spam_type' => (string) ($parsed['spam_type'] ?? 'unknown'),
                    ];
                }
            }
            
            // Fallback if response structure is unexpected
            return [
                'is_spam' => false,
                'confidence' => 0.5,
                'reason' => 'Unable to parse response',
                'error' => true,
            ];
        } catch (\Exception $e) {
            $this->logger->error('GitHub Copilot response parsing error: ' . $e->getMessage());
            return [
                'is_spam' => false,
                'confidence' => 0.5,
                'reason' => 'Response parsing error',
                'error' => true,
            ];
        }
    }
}
