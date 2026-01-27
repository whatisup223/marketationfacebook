<?php
// Migration: 010_remove_legacy_exchange_system
// Description: FINAL REMOVAL of exchange-related tables.
// Tables: exchanges, currencies, payment_methods, pricing
// Created: 2026-01-27

require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;

    echo "Starting FINAL CLEANUP of Legacy Exchange System...<br>";

    $tablesToDrop = [
        'exchanges',
        'currencies',
        'payment_methods',
        'pricing'
    ];

    foreach ($tablesToDrop as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table` ");
            echo " - Table '$table' deleted permanently. <span style='color:green'>Done</span><br>";
        } catch (Exception $e) {
            echo " - Error deleting '$table': " . $e->getMessage() . "<br>";
        }
    }

    echo "<b>Step 1/2: Database Cleanup Completed.</b><br>";

} catch (Exception $e) {
    echo "<span style='color:red'>Critical Error in Migration 010: " . $e->getMessage() . "</span><br>";
    throw $e;
}
?>