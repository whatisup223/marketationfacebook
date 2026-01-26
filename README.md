# Marketation Facebook - Campaign Management System

## 🚀 Features

- ✅ Automated Facebook campaign management
- ✅ Background processing with cron jobs
- ✅ Scheduled campaign launches
- ✅ Batch processing with configurable intervals
- ✅ Real-time campaign monitoring
- ✅ One-click system updates
- ✅ Safe database migrations

## 📦 Installation

1. Upload files to your hosting
2. Import `database.sql` to create tables
3. Configure `includes/db_config.php` with your database credentials
4. Set up cron job (see below)

## ⚙️ Cron Job Setup

Add this to your cPanel Cron Jobs (every 5 minutes):

```bash
*/5 * * * * /usr/local/bin/php /home/YOUR_USERNAME/public_html/user/cron_emulator.php >/dev/null 2>&1
```

Replace `YOUR_USERNAME` with your actual cPanel username.

## 🔄 System Updates

### For Admins:

1. Go to **Admin Panel** → **System Update**
2. Click **"Check Updates"** to see if new version is available
3. Click **"Pull Code"** to download latest code from GitHub
4. Click **"Run Migrations"** to update database structure

### Manual Update (via SSH):

```bash
cd /path/to/marketationfacebook
git pull origin main
php migrations/run_all.php
```

## 📁 Project Structure

```
marketationfacebook/
├── admin/              # Admin panel
│   └── system_update.php
├── includes/           # Core files
│   ├── db_config.php
│   ├── functions.php
│   └── facebook_api.php
├── user/               # User interface
│   ├── campaign_runner.php
│   ├── cron_emulator.php
│   └── ...
├── migrations/         # Database migrations
│   ├── 001_add_batch_columns.php
│   └── 002_add_queue_columns.php
└── version.txt         # Current version
```

## 🗄️ Database Migrations

Migrations are automatically run when you click "Run Migrations" in the admin panel.

### Creating New Migration:

1. Create file: `migrations/003_your_migration_name.php`
2. Follow this template:

```php
<?php
require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Your description...\n";

try {
    // Your SQL here
    $pdo->exec("ALTER TABLE ...");
    echo "✅ Migration completed!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
```

## 🔒 Security

- Database credentials are in `includes/db_config.php` (not in git)
- Admin panel requires authentication
- All user inputs are sanitized
- CSRF protection on forms

## 📝 Version History

### v1.0.0 (Current)
- Initial release
- Background campaign processing
- Cron job support
- System update manager

## 🆘 Support

For issues or questions, contact the development team.

## 📄 License

Proprietary - All rights reserved
