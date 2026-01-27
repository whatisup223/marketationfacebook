<?php
// user/db_update_tool.php
// Safe tool to add missing columns for retry logic

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db_config.php';

echo "<h2>🔧 Database Schema Updater</h2>";
echo "<p>Checking 'campaign_queue' table for missing retry columns...</p>";

try {
    // 1. Check/Add attempts_count
    echo "Checking 'attempts_count'... ";
    try {
        $pdo->query("SELECT attempts_count FROM campaign_queue LIMIT 1");
        echo "<span style='color:green'>Exists</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:orange'>Missing. Adding... </span>";
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN attempts_count INT DEFAULT 0");
        echo "<span style='color:green'>Added!</span><br>";
    }

    // 2. Check/Add next_retry_at
    echo "Checking 'next_retry_at'... ";
    try {
        $pdo->query("SELECT next_retry_at FROM campaign_queue LIMIT 1");
        echo "<span style='color:green'>Exists</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:orange'>Missing. Adding... </span>";
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN next_retry_at DATETIME DEFAULT NULL");
        echo "<span style='color:green'>Added!</span><br>";
    }

    // 3. Check/Add max_retries to campaigns table (optional but good)
    echo "Checking 'retry_count' in campaigns... ";
    try {
        $pdo->query("SELECT retry_count FROM campaigns LIMIT 1");
        echo "<span style='color:green'>Exists</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:orange'>Missing. Adding... </span>";
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN retry_count INT DEFAULT 1");
        echo "<span style='color:green'>Added!</span><br>";
    }

    echo "<hr><h3 style='color:green'>✅ Database is ready for Retry Logic!</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>