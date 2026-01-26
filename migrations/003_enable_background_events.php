<?php
// migrations/003_enable_background_events.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    // $pdo is already created in db_config.php
    global $pdo;

    // 1. Enable Event Scheduler
    try {
        $pdo->exec("SET GLOBAL event_scheduler = ON;");
        echo "Attempted to set GLOBAL event_scheduler = ON.\n";
    } catch (PDOException $e) {
        echo "Notice: Could not set GLOBAL event_scheduler (Check permissions). Continuing...\n";
    }

    // 2. Add last_heartbeat
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM fb_campaigns LIKE 'last_heartbeat'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE fb_campaigns ADD COLUMN last_heartbeat DATETIME NULL AFTER status");
            echo "Added 'last_heartbeat' column.\n";
        } else {
            echo "Column 'last_heartbeat' already exists.\n";
        }
    } catch (PDOException $e) {
        // Table fb_campaigns might not exist yet if fresh install, but checking columns is safer
        echo "Warning checking column: " . $e->getMessage() . "\n";
    }

    // 3. Create Event
    $sql = "
    CREATE EVENT IF NOT EXISTS `AutoFixStuckCampaigns`
    ON SCHEDULE EVERY 1 MINUTE
    DO
    BEGIN
        UPDATE fb_campaigns 
        SET status = 'paused', 
            note = CONCAT(IFNULL(note, ''), ' [System: Auto-paused due to inactivity]')
        WHERE status = 'processing' 
        AND last_heartbeat < (NOW() - INTERVAL 5 MINUTE);
    END;
    ";

    $pdo->exec($sql);
    echo "✅ Event 'AutoFixStuckCampaigns' created successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
