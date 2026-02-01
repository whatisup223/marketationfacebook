# Database Migrations Guide

## 🚀 The Master Migration
All previous migrations (000-037) have been consolidated into:
`migrations/000_master_migration.php`

This file is **idempotent**, meaning it can be run safely multiple times. It checks for the existence of tables and columns before attempting to create or modify them.

## 📝 How to Add New Migrations
From now on, you have two choices:

### Option A: Append to Master (Recommended for small changes)
1. Open `migrations/master_migration.php`.
2. Add your new SQL logic at the end of the `try` block.
3. Use the helper functions `columnExists`, `tableExists`, and `indexExists` to ensure it stays idempotent.

### Option B: Create a New Numbered File (For major updates)
1. Create a new file (e.g., `038_new_feature.php`).
2. Follow the template:
```php
<?php
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

try {
    // Your logic here
    echo "✅ Migration XXX completed!\n";
} catch (Exception $e) {
    echo "❌ Migration XXX failed: " . $e->getMessage() . "\n";
}
```
3. After testing, you can later consolidate it into the master file.

## ✅ How to Run
1. Go to **Admin Panel** → **System Update**.
2. Click **"Run Migrations"**.
The system will automatically find and execute `master_migration.php` and any other `.php` files in this directory.

---
*Note: Always backup your database before running migrations in a production environment.*
