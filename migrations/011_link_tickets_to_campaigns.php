<?php
// migrations/011_link_tickets_to_campaigns.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;
    echo "Updating support tickets system...<br>";

    // 1. Remove exchange_id (if exists) and add campaign_id
    try {
        $pdo->exec("ALTER TABLE support_tickets DROP COLUMN IF EXISTS exchange_id");
        echo " - Removed column 'exchange_id' from support_tickets.<br>";
    } catch (Exception $e) {
    }

    try {
        $pdo->query("SELECT campaign_id FROM support_tickets LIMIT 1");
        echo " - Column 'campaign_id' already exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE support_tickets ADD COLUMN campaign_id INT NULL AFTER user_id");
        $pdo->exec("ALTER TABLE support_tickets ADD CONSTRAINT fk_ticket_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL");
        echo " - Added column 'campaign_id' to support_tickets and linked to campaigns table.<br>";
    }

    echo "✅ Support tickets system updated successfully!<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>