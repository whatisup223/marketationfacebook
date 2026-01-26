# Database Migrations Guide

## 📝 How to Create a New Migration

### Step 1: Create Migration File

Create a new file in `migrations/` directory with format:
```
XXX_description.php
```

Where `XXX` is the next number (e.g., `003`, `004`, etc.)

### Step 2: Migration Template

```php
<?php
// migrations/003_your_description.php
// Migration: Brief description of what this does

require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Your description...\n";

try {
    // Your database changes here
    
    // Example: Add column
    try {
        $pdo->exec("ALTER TABLE table_name ADD COLUMN column_name VARCHAR(255) DEFAULT NULL");
        echo "✓ Added column_name column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ column_name already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Example: Create table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS new_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created new_table\n";
    
    echo "✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
```

### Step 3: Test Locally

```bash
php migrations/003_your_description.php
```

### Step 4: Commit to Git

```bash
git add migrations/003_your_description.php
git commit -m "Add migration: your description"
git push origin main
```

### Step 5: Run in Production

1. Go to **Admin Panel** → **System Update**
2. Click **"Pull Code"**
3. Click **"Run Migrations"**

## ✅ Best Practices

1. **Always check if exists:** Use `IF NOT EXISTS` for CREATE TABLE
2. **Handle duplicates:** Catch "Duplicate column" errors for ALTER TABLE
3. **Be idempotent:** Migration should be safe to run multiple times
4. **Add comments:** Explain what the migration does
5. **Test locally first:** Always test before pushing to production

## 🔄 Migration Naming Convention

```
001_add_batch_columns.php       ✓ Good
002_add_queue_columns.php       ✓ Good
add_columns.php                 ✗ Bad (no number)
003_fix.php                     ✗ Bad (not descriptive)
```

## 📊 Common Migration Patterns

### Add Column
```php
$pdo->exec("ALTER TABLE campaigns ADD COLUMN new_field VARCHAR(255) DEFAULT NULL");
```

### Add Index
```php
$pdo->exec("CREATE INDEX idx_campaign_status ON campaigns(status)");
```

### Create Table
```php
$pdo->exec("
    CREATE TABLE IF NOT EXISTS new_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    )
");
```

### Modify Column
```php
$pdo->exec("ALTER TABLE campaigns MODIFY COLUMN status VARCHAR(50) NOT NULL");
```

## ⚠️ Important Notes

- Migrations run in **alphabetical order** (001, 002, 003...)
- Always **backup database** before running migrations in production
- Migrations are **one-way** (no automatic rollback)
- Test thoroughly in development environment first
