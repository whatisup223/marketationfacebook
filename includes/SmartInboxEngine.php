<?php
require_once __DIR__ . '/functions.php';

class SmartInboxEngine
{
    private $pdo;
    private $geminiKey;
    private $openaiKey;
    private $openaiModel;
    private $provider = 'gemini';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->geminiKey = getSetting('gemini_api_key');
        $this->openaiKey = getSetting('openai_api_key');
        $this->openaiModel = getSetting('openai_model') ?: 'gpt-3.5-turbo';

        // Auto-select provider: OpenAI > Gemini
        if (!empty($this->openaiKey)) {
            $this->provider = 'openai';
        } elseif (!empty($this->geminiKey)) {
            $this->provider = 'gemini';
        }
    }

    public function analyzeCreateReply($conversationId, $userId)
    {
        // 1. Get History
        $stmt = $this->pdo->prepare("SELECT * FROM unified_messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$conversationId]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        if (empty($messages))
            return ['error' => 'No messages found.'];

        // 2. Get Context
        $context = $this->getBusinessContext($userId);
        if (!$context)
            return ['error' => 'Missing Business Context (AI Settings).'];

        // 3. Build Prompt
        $prompt = $this->buildPrompt($messages, $context);

        // 4. Call API
        if ($this->provider === 'openai') {
            $response = $this->callOpenAI($prompt);
        } else {
            $response = $this->callGemini($prompt);
        }

        if (isset($response['error']))
            return $response;

        // 5. Parse
        $jsonStr = $this->extractJson($response);
        $analysis = json_decode($jsonStr, true);

        if ($analysis) {
            $this->saveAnalysis($conversationId, $analysis);
            return $analysis;
        }

        return ['error' => 'Failed to parse AI Analysis.'];
    }

    private function getBusinessContext($userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ai_advisor_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function buildPrompt($messages, $context)
    {
        $historyText = "";
        foreach ($messages as $msg) {
            $role = ($msg['sender'] === 'user') ? "Client" : "Agent";
            $historyText .= "$role: " . $msg['message_text'] . "\n";
        }

        return "You are an AI Sales Assistant for '{$context['business_name']}'.
        Context: {$context['business_description']}
        Tone: {$context['tone_of_voice']}
        Rules: {$context['custom_instructions']}

        Analyze this conversation strictly returning VALID JSON only. 
        IMPORTANT: ALL VALUES MUST BE IN ARABIC (except keys).
        
        {
            \"sentiment\": \"positive|neutral|negative|angry\",
            \"intent\": \"short intent in arabic\",
            \"summary\": \"1 sentence summary in arabic\",
            \"next_best_action\": \"advice for agent in arabic\",
            \"suggested_replies\": [\"reply1 in arabic\", \"reply2 in arabic\"]
        }

        Conversation:
        $historyText";
    }

    private function callOpenAI($prompt)
    {
        $url = "https://api.openai.com/v1/chat/completions";
        $data = [
            "model" => $this->openaiModel,
            "messages" => [
                ["role" => "system", "content" => "You are a helpful JSON API assistant."],
                ["role" => "user", "content" => $prompt]
            ],
            "response_format" => ["type" => "json_object"]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->openaiKey}"
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200)
            return ['error' => "OpenAI Error ($httpCode): $result"];

        $decoded = json_decode($result, true);
        return $decoded['choices'][0]['message']['content'] ?? ['error' => 'Invalid OpenAI response'];
    }

    private function callGemini($prompt)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200)
            return ['error' => "Gemini Error ($httpCode): $result"];

        $decoded = json_decode($result, true);
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? ['error' => 'Invalid Gemini response'];
    }

    private function extractJson($raw)
    {
        if (is_array($raw))
            return json_encode($raw);
        if (preg_match('/\{.*\}/s', $raw, $matches))
            return $matches[0];
        return $raw;
    }

    private function saveAnalysis($convId, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE unified_conversations SET ai_sentiment=?, ai_intent=?, ai_summary=?, ai_next_best_action=?, ai_suggested_replies=?, last_analyzed_at=NOW() WHERE id=?");
        $stmt->execute([
            $data['sentiment'] ?? 'neutral',
            $data['intent'] ?? '',
            $data['summary'] ?? '',
            $data['next_best_action'] ?? '',
            json_encode($data['suggested_replies'] ?? []),
            $convId
        ]);
    }
}
?>