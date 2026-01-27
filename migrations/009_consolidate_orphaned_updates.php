<?php
// Migration: 009_consolidate_orphaned_updates
// Description: Integrates previous manual SQL updates into the official migration system.
// Includes: exchange table columns, currency table cleanup, and payment_method constraints.
// Created: 2024-01-27

require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;

    echo "Starting migration 009...<br>";

    // 1. Update 'exchanges' table (Payment methods columns)
    try {
        $pdo->query("SELECT payment_method_send_id FROM exchanges LIMIT 1");
        echo " - Columns already exist in 'exchanges' table.<br>";
    } catch (Exception $e) {
        $pdo->exec("
            ALTER TABLE exchanges 
            ADD COLUMN payment_method_send_id INT NULL AFTER currency_to_id,
            ADD COLUMN payment_method_receive_id INT NULL AFTER payment_method_send_id,
            ADD CONSTRAINT fk_payment_method_send FOREIGN KEY (payment_method_send_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_payment_method_receive FOREIGN KEY (payment_method_receive_id) REFERENCES payment_methods(id) ON DELETE SET NULL
        ");
        echo " - Updated 'exchanges' table with payment method columns. <span style='color:green'>Done</span><br>";
    }

    // 2. Cleanup 'currencies' table (Remove wallet_info)
    try {
        $pdo->query("SELECT wallet_info FROM currencies LIMIT 1");
        // Column exists, let's drop it (IF NOT EXISTS is not standard for DROP in all MySQL versions, so we use try/catch)
        $pdo->exec("ALTER TABLE currencies DROP COLUMN wallet_info");
        echo " - Removed 'wallet_info' column from 'currencies' table. <span style='color:green'>Done</span><br>";
    } catch (Exception $e) {
        echo " - Column 'wallet_info' already removed from 'currencies'.<br>";
    }

    // 3. Update 'payment_methods' table (Make wallet_info required)
    try {
        $pdo->exec("
            ALTER TABLE payment_methods 
            MODIFY COLUMN wallet_info TEXT NOT NULL 
            COMMENT 'Wallet address/account info where users send/receive payments'
        ");
        echo " - Updated 'payment_methods' to make wallet_info required. <span style='color:green'>Done</span><br>";
    } catch (Exception $e) {
        echo " - Error updating payment_methods: " . $e->getMessage() . "<br>";
    }

    echo "Migration 009 completed successfully.<br>";

} catch (Exception $e) {
    echo "<span style='color:red'>Error in Migration 009: " . $e->getMessage() . "</span><br>";
    throw $e;
}
?>