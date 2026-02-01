<?php
// migrations/036_create_compliance_tables.php
// Description: Create bot_audience and bot_optouts tables for ComplianceEngine

require_once __DIR__ . '/../includes/db_config.php';

try {
    // 1. Create bot_audience table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_audience (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id VARCHAR(100) NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        last_interaction_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        source ENUM('comment', 'message') DEFAULT 'message',
        is_window_open TINYINT(1) DEFAULT 1,
        UNIQUE KEY page_user (page_id, user_id),
        INDEX (page_id),
        INDEX (user_id)
    )");
    echo "Table 'bot_audience' checked/created successfully.<br>";

    // 2. Create bot_optouts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_optouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id VARCHAR(100) NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY page_user_opt (page_id, user_id),
        INDEX (page_id),
        INDEX (user_id)
    )");
    echo "Table 'bot_optouts' checked/created successfully.<br>";

    echo "Migration 036 (Compliance Tables) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 036: " . $e->getMessage() . "<br>";
}
