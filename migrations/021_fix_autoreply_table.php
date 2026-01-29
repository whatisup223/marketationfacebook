<?php
// Migration: 021_fix_autoreply_table.php
// Description: Fix auto_reply_rules columns to match AJAX logic and add hide_comment

$pdo = getDB();

try {
    // Check current columns
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 1. Add 'trigger_type' if missing
    if (!in_array('trigger_type', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN trigger_type ENUM('keyword', 'default') DEFAULT 'keyword' AFTER page_id");
        echo "Added trigger_type column.<br>";
    }

    // 2. Add 'keywords' if missing (migrate from 'keyword')
    if (!in_array('keywords', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN keywords TEXT AFTER trigger_type");
        echo "Added keywords column.<br>";

        if (in_array('keyword', $columns)) {
            $pdo->exec("UPDATE auto_reply_rules SET keywords = keyword");
            echo "Migrated data from keyword to keywords.<br>";
        }
    }

    // 3. Add 'reply_message' if missing (migrate from 'reply_text')
    if (!in_array('reply_message', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN reply_message TEXT AFTER keywords");
        echo "Added reply_message column.<br>";

        if (in_array('reply_text', $columns)) {
            $pdo->exec("UPDATE auto_reply_rules SET reply_message = reply_text");
            echo "Migrated data from reply_text to reply_message.<br>";
        }
    }

    // 4. Add 'hide_comment'
    if (!in_array('hide_comment', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN hide_comment TINYINT(1) DEFAULT 0 AFTER reply_message");
        echo "Added hide_comment column.<br>";
    }

    // 5. Cleanup old columns
    if (in_array('keyword', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules DROP COLUMN keyword");
        echo "Dropped old column: keyword.<br>";
    }
    if (in_array('reply_text', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules DROP COLUMN reply_text");
        echo "Dropped old column: reply_text.<br>";
    }
    if (in_array('match_type', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules DROP COLUMN match_type");
        echo "Dropped old column: match_type.<br>";
    }

    // Ensure is_active exists
    if (!in_array('is_active', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }

    echo "Auto Reply table schema fixed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 021: " . $e->getMessage() . "<br>";
}
?>