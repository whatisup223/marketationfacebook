<?php
// Migration: 008_add_retry_logic_columns
// Description: Adds attempts_count, next_retry_at to campaign_queue and retry_count to campaigns
// Created: 2024-01-27

require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;

    echo "Starting migration 008...<br>";

    // 1. Add 'attempts_count' to 'campaign_queue'
    try {
        $pdo->query("SELECT attempts_count FROM campaign_queue LIMIT 1");
        echo " - Column 'attempts_count' already exists in campaign_queue.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN attempts_count INT DEFAULT 0");
        echo " - Added column 'attempts_count' to campaign_queue. <span style='color:green'>Done</span><br>";
    }

    // 2. Add 'next_retry_at' to 'campaign_queue'
    try {
        $pdo->query("SELECT next_retry_at FROM campaign_queue LIMIT 1");
        echo " - Column 'next_retry_at' already exists in campaign_queue.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN next_retry_at DATETIME DEFAULT NULL");
        echo " - Added column 'next_retry_at' to campaign_queue. <span style='color:green'>Done</span><br>";
    }

    // 3. Add 'retry_count' to 'campaigns'
    try {
        $pdo->query("SELECT retry_count FROM campaigns LIMIT 1");
        echo " - Column 'retry_count' already exists in campaigns.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN retry_count INT DEFAULT 1");
        echo " - Added column 'retry_count' to campaigns. <span style='color:green'>Done</span><br>";
    }

    // 4. Add 'retry_delay' to 'campaigns' (Fixing previous omission if any)
    try {
        $pdo->query("SELECT retry_delay FROM campaigns LIMIT 1");
        echo " - Column 'retry_delay' already exists in campaigns.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN retry_delay INT DEFAULT 10");
        echo " - Added column 'retry_delay' to campaigns. <span style='color:green'>Done</span><br>";
    }

    echo "Migration 008 completed successfully.<br>";

} catch (Exception $e) {
    echo "<span style='color:red'>Error in Migration 008: " . $e->getMessage() . "</span><br>";
    throw $e;
}
?>