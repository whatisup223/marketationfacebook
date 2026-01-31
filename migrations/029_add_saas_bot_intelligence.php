<?php
// migrations/029_add_saas_bot_intelligence.php
// Description: Add advanced SaaS intelligence columns and conversation state tracking

require_once __DIR__ . '/../includes/db_config.php';

try {
    // 1. Update fb_pages for Global Intelligence Settings
    $stmt = $pdo->query("SHOW COLUMNS FROM fb_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('bot_ai_sentiment_enabled', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_ai_sentiment_enabled TINYINT(1) DEFAULT 1 AFTER bot_exclude_keywords");
        echo "Added 'bot_ai_sentiment_enabled' to fb_pages.<br>";
    }

    if (!in_array('bot_anger_keywords', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_anger_keywords TEXT NULL AFTER bot_ai_sentiment_enabled");
        echo "Added 'bot_anger_keywords' to fb_pages.<br>";

        // Populate with some defaults
        $default_anger = "نصاب,نصابين,رد يا,فينكم,اشتريت,موصلش,عايز أكلم حد,شكوى,مدير,بني آدم,غالي,حرام";
        $pdo->prepare("UPDATE fb_pages SET bot_anger_keywords = ? WHERE bot_anger_keywords IS NULL")->execute([$default_anger]);
    }

    // 2. Update auto_reply_rules for Granular Control
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules");
    $rules_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('is_ai_safe', $rules_columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN is_ai_safe TINYINT(1) DEFAULT 1");
        echo "Added 'is_ai_safe' to auto_reply_rules.<br>";
    }

    if (!in_array('bypass_schedule', $rules_columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN bypass_schedule TINYINT(1) DEFAULT 0");
        echo "Added 'bypass_schedule' to auto_reply_rules.<br>";
    }

    if (!in_array('bypass_cooldown', $rules_columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN bypass_cooldown TINYINT(1) DEFAULT 0");
        echo "Added 'bypass_cooldown' to auto_reply_rules.<br>";
    }

    // 3. Create Conversation State Tracking Table (The Handover Protocol Memory)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_conversation_states (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id VARCHAR(100) NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        conversation_state ENUM('active', 'handover', 'resolved') DEFAULT 'active',
        last_bot_reply_text TEXT NULL,
        repeat_count INT DEFAULT 0,
        is_anger_detected TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY page_user (page_id, user_id),
        INDEX (conversation_state),
        INDEX (is_anger_detected)
    )");
    echo "Table 'bot_conversation_states' created/checked successfully.<br>";

    echo "Migration 029 (SaaS Bot Intelligence) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 029: " . $e->getMessage() . "<br>";
}
