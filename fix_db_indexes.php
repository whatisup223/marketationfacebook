<?php
// fix_db_indexes.php
require_once __DIR__ . '/includes/db_config.php';

try {
    $pdo = getDB();
    echo "Checking and applying indexes...\n";

    // 1. Index on campaign_queue(campaign_id)
    try {
        $pdo->exec("CREATE INDEX idx_queue_campaign_id ON campaign_queue(campaign_id)");
        echo "✅ Added index: idx_queue_campaign_id\n";
    } catch (PDOException $e) {
        // If error contains "Duplicate" or "already exists", it's fine
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ Index idx_queue_campaign_id already exists.\n";
        } else {
            echo "⚠️ Note on idx_queue_campaign_id: " . $e->getMessage() . "\n";
        }
    }

    // 2. Index on campaign_queue(status)
    try {
        $pdo->exec("CREATE INDEX idx_queue_status ON campaign_queue(status)");
        echo "✅ Added index: idx_queue_status\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ Index idx_queue_status already exists.\n";
        } else {
            echo "⚠️ Note on idx_queue_status: " . $e->getMessage() . "\n";
        }
    }

    // 3. Composite Index for faster lookup in batch handler
    // WHERE campaign_id = ? AND status = 'pending'
    try {
        $pdo->exec("CREATE INDEX idx_queue_campaign_status ON campaign_queue(campaign_id, status)");
        echo "✅ Added composite index: idx_queue_campaign_status\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ Index idx_queue_campaign_status already exists.\n";
        } else {
            echo "⚠️ Note on idx_queue_campaign_status: " . $e->getMessage() . "\n";
        }
    }

    // 4. Index for Next Retry
    try {
        $pdo->exec("CREATE INDEX idx_queue_retry ON campaign_queue(next_retry_at)");
        echo "✅ Added index: idx_queue_retry\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ Index idx_queue_retry already exists.\n";
        } else {
            echo "⚠️ Note on idx_queue_retry: " . $e->getMessage() . "\n";
        }
    }

    echo "\n--------------------------------\n";
    echo "Done! Database is optimized for high volume.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
