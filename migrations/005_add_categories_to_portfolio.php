<?php
// migrations/005_add_categories_to_portfolio.php
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();
try {
    $pdo->exec("ALTER TABLE portfolio_items ADD COLUMN category_ar VARCHAR(255) AFTER description_en, ADD COLUMN category_en VARCHAR(255) AFTER category_ar;");
    echo "Columns added successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
