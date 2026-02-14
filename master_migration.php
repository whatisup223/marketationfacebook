<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Master Migration Script for Marketation
// Adds all missing columns for Instagram/Facebook Auto-Reply & Bot features
// Usage: Run this script once in the browser or command line.

// Manual connection to bypass potential issues in db_config.php
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'marketation_db';

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

echo "<h1>Starting Master Database Migration...</h1>\n";
echo "<pre>\n";

function addColumnIfNeeded($pdo, $table, $column, $definition)
{
    try {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            $pdo->exec($sql);
            echo "[SUCCESS] Added column '$column' to table '$table'\n";
        } else {
            echo "[INFO] Column '$column' already exists in '$table'\n";
        }
    } catch (PDOException $e) {
        echo "[ERROR] Failed to add '$column' to '$table': " . $e->getMessage() . "\n";
    }
}

function createTableIfNeeded($pdo, $table, $sql_create)
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $pdo->exec($sql_create);
            echo "[SUCCESS] Created table '$table'\n";
        } else {
            echo "[INFO] Table '$table' already exists\n";
        }
    } catch (PDOException $e) {
        echo "[ERROR] Failed to create '$table': " . $e->getMessage() . "\n";
    }
}

// --------------------------------------------------------------------------
// 1. Table: fb_pages
// --------------------------------------------------------------------------
// Core Instagram & Bot Settings
addColumnIfNeeded($pdo, 'fb_pages', 'ig_business_id', "VARCHAR(100) NULL");
addColumnIfNeeded($pdo, 'fb_pages', 'ig_username', "VARCHAR(100) NULL");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_cooldown_seconds', "INT DEFAULT 0");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_schedule_enabled', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_schedule_start', "TIME NULL");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_schedule_end', "TIME NULL");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_exclude_keywords', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_ai_sentiment_enabled', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_anger_keywords', "TEXT NULL");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_repetition_threshold', "INT DEFAULT 3");
addColumnIfNeeded($pdo, 'fb_pages', 'bot_handover_reply', "TEXT NULL");

// --------------------------------------------------------------------------
// 2. Table: auto_reply_rules
// --------------------------------------------------------------------------
// Enhanced Logic & Platform Support
addColumnIfNeeded($pdo, 'auto_reply_rules', 'platform', "ENUM('facebook', 'instagram') DEFAULT 'facebook'");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'auto_like_comment', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'private_reply_enabled', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'private_reply_text', "TEXT NULL");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'is_ai_safe', "TINYINT(1) DEFAULT 1");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'bypass_schedule', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'bypass_cooldown', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'usage_count', "INT DEFAULT 0");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'reply_image_url', "TEXT NULL");
addColumnIfNeeded($pdo, 'auto_reply_rules', 'reply_buttons', "TEXT NULL"); // Stores JSON
addColumnIfNeeded($pdo, 'auto_reply_rules', 'trigger_type', "ENUM('keyword', 'default', 'ai') DEFAULT 'keyword'");

// --------------------------------------------------------------------------
// 3. Table: fb_moderation_rules
// --------------------------------------------------------------------------
createTableIfNeeded($pdo, 'fb_moderation_rules', "
CREATE TABLE `fb_moderation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `page_id` varchar(100) NOT NULL,
  `hide_phones` tinyint(1) DEFAULT 0,
  `hide_links` tinyint(1) DEFAULT 0,
  `banned_keywords` text DEFAULT NULL,
  `action_type` enum('hide','delete') DEFAULT 'hide',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
addColumnIfNeeded($pdo, 'fb_moderation_rules', 'ig_business_id', "VARCHAR(100) NULL");
addColumnIfNeeded($pdo, 'fb_moderation_rules', 'platform', "ENUM('facebook', 'instagram') DEFAULT 'facebook'");

// --------------------------------------------------------------------------
// 4. Table: bot_conversation_states
// --------------------------------------------------------------------------
// Tracks repetitive AI behavior and anger
createTableIfNeeded($pdo, 'bot_conversation_states', "
CREATE TABLE `bot_conversation_states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` varchar(100) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `conversation_state` enum('active','handover','done') DEFAULT 'active',
  `last_user_message` text DEFAULT NULL,
  `last_user_message_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conv` (`page_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
addColumnIfNeeded($pdo, 'bot_conversation_states', 'platform', "ENUM('facebook', 'instagram') DEFAULT 'facebook'");
addColumnIfNeeded($pdo, 'bot_conversation_states', 'reply_source', "VARCHAR(50) DEFAULT 'message'");
addColumnIfNeeded($pdo, 'bot_conversation_states', 'is_anger_detected', "TINYINT(1) DEFAULT 0");
addColumnIfNeeded($pdo, 'bot_conversation_states', 'repeat_count', "INT DEFAULT 0");
addColumnIfNeeded($pdo, 'bot_conversation_states', 'last_bot_reply_text', "TEXT NULL");

// --------------------------------------------------------------------------
// 5. Table: bot_sent_messages
// --------------------------------------------------------------------------
createTableIfNeeded($pdo, 'bot_sent_messages', "
CREATE TABLE `bot_sent_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` varchar(100) NOT NULL,
  `page_id` varchar(100) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `msg_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
addColumnIfNeeded($pdo, 'bot_sent_messages', 'rule_id', "INT NULL");
addColumnIfNeeded($pdo, 'bot_sent_messages', 'reply_source', "VARCHAR(50) NULL");
addColumnIfNeeded($pdo, 'bot_sent_messages', 'user_id', "VARCHAR(100) NULL");

echo "\n-------------------------------------------------------\n";
echo "Migration Completed Successfully. You can now rest your mind.";
echo "</pre>";
?>