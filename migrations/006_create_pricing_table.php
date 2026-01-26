<?php
// migrations/006_create_pricing_table.php
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pricing_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_name_ar VARCHAR(255) NOT NULL,
            plan_name_en VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            currency_ar VARCHAR(50) DEFAULT 'ريال',
            currency_en VARCHAR(50) DEFAULT 'SAR',
            billing_period_ar VARCHAR(100) DEFAULT 'شهرياً',
            billing_period_en VARCHAR(100) DEFAULT 'Monthly',
            description_ar TEXT,
            description_en TEXT,
            features TEXT,
            is_featured BOOLEAN DEFAULT 0,
            button_text_ar VARCHAR(100) DEFAULT 'اشترك الآن',
            button_text_en VARCHAR(100) DEFAULT 'Subscribe Now',
            button_url VARCHAR(500),
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Pricing plans table created successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
