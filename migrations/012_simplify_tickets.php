<?php
// migrations/012_simplify_tickets.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;
    echo "Simplifying support tickets system...<br>";

    // Remove all relational columns to keep it clean for future subscription system
    try {
        $pdo->exec("ALTER TABLE support_tickets DROP FOREIGN KEY IF EXISTS fk_ticket_campaign");
        $pdo->exec("ALTER TABLE support_tickets DROP COLUMN IF EXISTS campaign_id");
        $pdo->exec("ALTER TABLE support_tickets DROP COLUMN IF EXISTS exchange_id");
        echo " - Removed all old relations (exchange_id, campaign_id) from support_tickets.<br>";
    } catch (Exception $e) {
        echo " - Note: Some columns already removed or not found. <br>";
    }

    echo "✅ Support tickets system simplified successfully!<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>