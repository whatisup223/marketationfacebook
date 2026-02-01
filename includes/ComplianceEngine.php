<?php
/**
 * ComplianceEngine.php
 * 
 * Centralized Guardian for ensuring policy compliance, spam protection, and smart restrictions.
 * Acts as a middleware before sending any message or replying to comments.
 */

class ComplianceEngine
{
    private $pdo;
    private $page_id;
    private $access_token;

    public function __construct($pdo, $page_id, $access_token = '')
    {
        $this->pdo = $pdo;
        $this->page_id = $page_id;
        $this->access_token = $access_token;
    }

    /**
     * MAIN GATEKEEPER: Can we send a message to this user?
     * @param string $recipient_id (PSID)
     * @param string $message_type (RESPONSE, UPDATE, MESSAGE_TAG, etc.)
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function canSendMessage($recipient_id, $message_type = 'RESPONSE')
    {
        // 1. Check if user has blocked the bot (Opt-out)
        if ($this->isUserOptedOut($recipient_id)) {
            return ['allowed' => false, 'reason' => 'User opted out (STOP/UNSUBSCRIBE)'];
        }

        // 2. Check 24-Hour Window
        $windowStatus = $this->check24HourWindow($recipient_id);
        if (!$windowStatus['is_open'] && $message_type === 'RESPONSE') {
            // If window is closed, we can ONLY send specific tags, not standard responses
            // Unless it's a specific "tag" type message allowed outside window
            return ['allowed' => false, 'reason' => 'Outside 24-hour window. Window closed ' . $windowStatus['closed_ago']];
        }

        // 3. Rate Limiting (Anti-Spam)
        if ($this->isRateLimited($recipient_id)) {
            return ['allowed' => false, 'reason' => 'Rate limit exceeded (Too many messages in short time)'];
        }

        return ['allowed' => true, 'reason' => 'OK'];
    }

    /**
     * CONTENT GATEKEEPER: Is this content safe to send/publish?
     * @param string $text
     * @return array ['safe' => bool, 'reason' => string, 'sanitized_text' => string]
     */
    public function checkContentSafety($text)
    {
        $blocked = $this->getBlockedKeywords();

        foreach ($blocked as $badword) {
            if (mb_stripos($text, $badword) !== false) {
                return ['safe' => false, 'reason' => "Contains banned keyword: $badword", 'sanitized_text' => $text];
            }
        }

        // TODO: Add Link Phishing checks here later

        return ['safe' => true, 'reason' => 'OK', 'sanitized_text' => $text];
    }

    /**
     * Updates the user's interaction time (Opens the 24h Window)
     * Should be called WHENEVER a user sends a message or interacts.
     */
    public function refreshLastInteraction($recipient_id, $source = 'MESSAGE')
    {
        // Update or Insert into a specialized tracking table
        // For now, we use a simple logic, but ideally this goes into 'bot_audience' table
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_audience (page_id, user_id, last_interaction_at, source, is_window_open)
            VALUES (?, ?, NOW(), ?, 1)
            ON DUPLICATE KEY UPDATE 
                last_interaction_at = NOW(),
                is_window_open = 1,
                source = VALUES(source)
        ");
        $stmt->execute([$this->page_id, $recipient_id, $source]);
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPER METHODS
    // -------------------------------------------------------------------------

    private function check24HourWindow($recipient_id)
    {
        // Look up last interaction
        $stmt = $this->pdo->prepare("SELECT last_interaction_at FROM bot_audience WHERE page_id = ? AND user_id = ?");
        $stmt->execute([$this->page_id, $recipient_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // New user or untracked? Assume window CLOSED for safety unless it's a direct reply to a retrieved webhook event
            // But if we are sending proactive, we assume closed.
            return ['is_open' => false, 'closed_ago' => 'Never interacted'];
        }

        $last_interact = strtotime($row['last_interaction_at']);
        $diff = time() - $last_interact;
        $window = 24 * 60 * 60; // 24 Hours in seconds

        if ($diff <= $window) {
            return ['is_open' => true, 'closed_ago' => null];
        }

        return ['is_open' => false, 'closed_ago' => $this->timeAgo($diff)];
    }

    private function isRateLimited($recipient_id)
    {
        // Simple logic: Max 10 messages per 1 minute to the same user
        // This requires a log table. For MVP, we use 'bot_sent_messages'
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM bot_sent_messages 
            WHERE page_id = ? AND user_id = ? 
            AND created_at > (NOW() - INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$this->page_id, $recipient_id]);
        $count = $stmt->fetchColumn();

        return ($count >= 10);
    }

    private function isUserOptedOut($recipient_id)
    {
        // Check if user is in 'bot_optouts'
        $stmt = $this->pdo->prepare("SELECT id FROM bot_optouts WHERE page_id = ? AND user_id = ?");
        $stmt->execute([$this->page_id, $recipient_id]);
        return (bool) $stmt->fetch();
    }

    private function getBlockedKeywords()
    {
        // Fetch from Page Settings
        // We can cache this, but for now query directly
        $stmt = $this->pdo->prepare("SELECT bot_exclude_keywords FROM fb_pages WHERE page_id = ?");
        $stmt->execute([$this->page_id]);
        $kws = $stmt->fetchColumn();

        if (!$kws)
            return [];

        return array_map('trim', explode(',', $kws));
    }

    private function timeAgo($seconds)
    {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        return "$hours hours, $mins mins";
    }
}
