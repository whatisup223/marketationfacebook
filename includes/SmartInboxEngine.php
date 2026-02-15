<?php

require_once __DIR__ . '/functions.php';

class SmartInboxEngine
{
    private $pdo;
    private $apiKey;
    private $model = "gemini-1.5-flash"; // Fast & Cheap

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        // Fetch API Key from settings (assuming it's stored globally or per user)
        $this->apiKey = getSetting('gemini_api_key');
    }

    /**
     * Analyze a conversation thread and generate insights + replies.
     */
    public function analyzeCreateReply($conversationId, $userId)
    {
        // 1. Get Conversation History (Last 10 messages)
        $stmt = $this->pdo->prepare("SELECT * FROM unified_messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$conversationId]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        if (empty($messages)) {
            return ['error' => 'No messages found to analyze.'];
        }

        // 2. Get User Business Context
        $context = $this->getBusinessContext($userId);
        if (!$context) {
            return ['error' => 'Business context not set. Please configure AI settings first.'];
        }

        // 3. Construct Prompt
        $prompt = $this->buildPrompt($messages, $context);

        // 4. Call Gemini API
        $response = $this->callGemini($prompt);

        if (isset($response['error'])) {
            return $response;
        }

        // 5. Parse JSON Output
        $analysis = json_decode($response['candidates'][0]['content']['parts'][0]['text'], true);

        if (!$analysis) {
            // Fallback if JSON is malformed (sometimes models chatter)
            // Try to extract JSON block
            preg_match('/\{.*\}/s', $response['candidates'][0]['content']['parts'][0]['text'], $matches);
            if (isset($matches[0])) {
                $analysis = json_decode($matches[0], true);
            }
        }

        if ($analysis) {
            // 6. Save results to DB
            $this->saveAnalysis($conversationId, $analysis);
            return $analysis;
        }

        return ['error' => 'Failed to parse AI response.'];
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

        $systemInstruction = "You are an AI Sales & Support Assistant for a business called '{$context['business_name']}'.
        
        **Business Info:**
        - Description: {$context['business_description']}
        - Products/Services: {$context['products_services']}
        - Tone: {$context['tone_of_voice']}
        - Custom Instructions: {$context['custom_instructions']}

        **Task:**
        Analyze the conversation below and return a JSON object ONLY. Do not include markdown formatting.
        The JSON must strictly follow this schema:
        {
            \"sentiment\": \"positive\" | \"neutral\" | \"negative\" | \"angry\",
            \"intent\": \"Brief 2-3 word summary of user intent (e.g., Price Inquiry, Complaint, Greeting)\",
            \"summary\": \"One sentence summary of the situation.\",
            \"next_best_action\": \"Specific advice for the human agent on what to do next.\",
            \"suggested_replies\": [\"Reply 1 (Short)\", \"Reply 2 (Helpful)\", \"Reply 3 (Closing)\"]
        }

        **Conversation History:**
        $historyText
        
        **Output JSON:**";

        return $systemInstruction;
    }

    private function callGemini($prompt)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 800,
                "responseMimeType" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['error' => "API Error ($httpCode): " . $result];
        }

        return json_decode($result, true);
    }

    private function saveAnalysis($conversationId, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE unified_conversations SET 
            ai_sentiment = ?, 
            ai_intent = ?, 
            ai_summary = ?, 
            ai_next_best_action = ?, 
            ai_suggested_replies = ?,
            last_analyzed_at = NOW()
            WHERE id = ?");

        $stmt->execute([
            $data['sentiment'] ?? 'neutral',
            $data['intent'] ?? 'General',
            $data['summary'] ?? '',
            $data['next_best_action'] ?? '',
            json_encode($data['suggested_replies'] ?? []),
            $conversationId
        ]);
    }
}
?>