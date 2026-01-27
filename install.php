<?php
session_start();

// --- Language & Translation Logic ---
$lang = isset($_GET['lang']) && $_GET['lang'] == 'en' ? 'en' : 'ar';
$dir = $lang == 'ar' ? 'rtl' : 'ltr';

$trans = [
    'ar' => [
        'page_title' => 'تثبيت منصة ماركتيشن',
        'header_title' => 'تثبيت منصة ماركتيشن',
        'header_subtitle' => 'الحل المتكامل للتسويق الإلكتروني وإدارة الحملات',
        'db_settings' => 'إعدادات قاعدة البيانات',
        'db_host' => 'عنوان الخادم (Host)',
        'db_name' => 'اسم قاعدة البيانات',
        'db_user' => 'اسم مستخدم قاعدة البيانات',
        'db_pass' => 'كلمة مرور قاعدة البيانات',
        'site_settings' => 'إعدادات الموقع',
        'site_url' => 'رابط الموقع',
        'site_url_hint' => 'مثال: https://yourdomain.com',
        'admin_settings' => 'حساب المدير العام',
        'admin_email' => 'البريد الإلكتروني',
        'admin_pass' => 'كلمة المرور',
        'install_btn' => 'بدء التثبيت الآن',
        'install_success' => 'تم التثبيت بنجاح!',
        'install_error' => 'فشل التثبيت: ',
        'example' => 'مثال',
        'connection_error' => 'خطأ: لا يمكن الاتصال بقاعدة البيانات. ',
        'footer_copyright' => 'جميع الحقوق محفوظة © ' . date('Y') . ' لـ <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition-colors">ماركتيشن</a> للتسويق الإلكتروني والحلول البرمجيه'
    ],
    'en' => [
        'page_title' => 'Install Marketation Platform',
        'header_title' => 'Install Marketation',
        'header_subtitle' => 'The Integrated Digital Marketing & Campaign Solution',
        'db_settings' => 'Database Settings',
        'db_host' => 'Database Host',
        'db_name' => 'Database Name',
        'db_user' => 'Database User',
        'db_pass' => 'Database Password',
        'site_settings' => 'Site Settings',
        'site_url' => 'Site URL',
        'site_url_hint' => 'Example: https://yourdomain.com',
        'admin_settings' => 'Admin Account',
        'admin_email' => 'Email Address',
        'admin_pass' => 'Password',
        'install_btn' => 'Start Installation',
        'install_success' => 'Installation Successful!',
        'install_error' => 'Installation Failed: ',
        'example' => 'Example',
        'connection_error' => 'ERROR: Could not connect. ',
        'footer_copyright' => 'All rights reserved © ' . date('Y') . ' <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition-colors">Marketation</a> for digital marketing and programming solutions'
    ]
];

function __t($key)
{
    global $trans, $lang;
    return $trans[$lang][$key] ?? $key;
}

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];

    $admin_email = $_POST['admin_email'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
    $site_url = rtrim($_POST['site_url'], '/');

    try {
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // 1. Plans Table (New)
        $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_en VARCHAR(100) NOT NULL,
            name_ar VARCHAR(100) NOT NULL,
            price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            duration_days INT NOT NULL DEFAULT 30,
            messages_limit INT DEFAULT 0, /* 0 = unlimited or handled by logic */
            pages_limit INT DEFAULT 1,
            description_en TEXT,
            description_ar TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Users Table (Updated)
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            avatar VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            preferences TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. User Subscriptions (New)
        $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
            payment_status ENUM('paid', 'pending', 'free') DEFAULT 'paid',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES plans(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Facebook Accounts (Token Based)
        $pdo->exec("CREATE TABLE IF NOT EXISTS fb_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            fb_name VARCHAR(100) NOT NULL,
            fb_id VARCHAR(50) NOT NULL,
            access_token TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Facebook Pages
        $pdo->exec("CREATE TABLE IF NOT EXISTS fb_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            page_name VARCHAR(255) NOT NULL,
            page_id VARCHAR(50) NOT NULL,
            page_access_token TEXT NOT NULL,
            picture_url TEXT,
            category VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES fb_accounts(id) ON DELETE CASCADE,
            UNIQUE KEY `unique_page_id` (`page_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Facebook Leads (Customers who messaged the page)
        $pdo->exec("CREATE TABLE IF NOT EXISTS fb_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL, /* Internal ID from fb_pages */
            fb_user_id VARCHAR(50) NOT NULL, /* Scoped User ID (PSID) */
            fb_user_name VARCHAR(150),
            last_interaction DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            /* A user can be a lead in multiple pages */
            FOREIGN KEY (page_id) REFERENCES fb_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Campaigns
        $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            page_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            message_text TEXT NOT NULL,
            image_url VARCHAR(255) DEFAULT NULL,
            status ENUM('draft', 'scheduled', 'running', 'completed', 'paused') DEFAULT 'draft',
            total_leads INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            failed_count INT DEFAULT 0,
            scheduled_at DATETIME DEFAULT NULL,
            waiting_interval INT DEFAULT 30,
            retry_count INT DEFAULT 1,
            retry_delay INT DEFAULT 10,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id) REFERENCES fb_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 8. Extraction Tasks (Added)
        $pdo->exec("CREATE TABLE IF NOT EXISTS extraction_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_name VARCHAR(255) NOT NULL,
            target_url VARCHAR(255) NOT NULL,
            target_type ENUM('page_messages', 'post_comments', 'group_members') DEFAULT 'page_messages',
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            progress INT DEFAULT 0,
            total_leads INT DEFAULT 0,
            file_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 9. Campaign Queue (For sending logs)
        $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            lead_id INT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            sent_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (lead_id) REFERENCES fb_leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 9. Notifications Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT '#',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 10. Settings Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 11. Support Tickets Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status ENUM('open', 'answered', 'closed', 'pending', 'solved') DEFAULT 'open',
            is_read_admin TINYINT(1) DEFAULT 0,
            is_read_user TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 12. Ticket Messages Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 13. Testimonials Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_en VARCHAR(100) NOT NULL,
            name_ar VARCHAR(100) NOT NULL,
            review_en TEXT,
            review_ar TEXT,
            stars INT DEFAULT 5,
            image VARCHAR(255),
            user_type_en VARCHAR(50),
            user_type_ar VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 14. FAQs Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_en TEXT NOT NULL,
            question_ar TEXT NOT NULL,
            answer_en TEXT NOT NULL,
            answer_ar TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 15. Password Resets Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // --- Data Seeding ---

        // Create Admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$admin_email]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute(['Administrator', $admin_email, $admin_pass]);
        }

        // Seeding Default Plans
        $stmt = $pdo->query("SELECT COUNT(*) FROM plans");
        if ($stmt->fetchColumn() == 0) {
            $plans = [
                [
                    'Free Trial',
                    'تجربة مجانية',
                    0.00,
                    7,
                    50,
                    1,
                    'Perfect for testing features. 50 Messages limit.',
                    'مناسبة لتجربة المميزات. حد 50 رسالة.'
                ],
                [
                    'Starter',
                    'البداية',
                    29.00,
                    30,
                    5000,
                    3,
                    'Good for small businesses. 5,000 Messages/mo.',
                    'جيدة للأعمال الصغيرة. 5,000 رسالة شهرياً.'
                ],
                [
                    'Pro',
                    'احترافي',
                    69.00,
                    30,
                    20000,
                    10,
                    'For growing agencies. 20,000 Messages/mo.',
                    'للوكالات المتنامية. 20,000 رسالة شهرياً.'
                ]
            ];
            $insertPlan = $pdo->prepare("INSERT INTO plans (name_en, name_ar, price, duration_days, messages_limit, pages_limit, description_en, description_ar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($plans as $p) {
                $insertPlan->execute($p);
            }
        }

        // Give Admin a Subscription (Unlimited for testing)
        // Check if admin user exists (from above)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$admin_email]);
        $admin_id = $stmt->fetchColumn();

        if ($admin_id) {
            $checkSub = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
            $checkSub->execute([$admin_id]);
            if ($checkSub->fetchColumn() == 0) {
                // Get highest plan
                $plan_id = $pdo->query("SELECT id FROM plans ORDER BY price DESC LIMIT 1")->fetchColumn();
                if ($plan_id) {
                    $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status, payment_status) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 'paid')");
                    $stmt->execute([$admin_id, $plan_id]);
                }
            }
        }

        // Default Settings
        $default_settings = [
            'site_name_en' => 'Marketation',
            'site_name_ar' => 'ماركتيشن',
            'site_url' => $site_url,
            'contact_email' => $admin_email,
            'maintenance_mode' => '0',
            'fb_app_id' => '', // To be filled by admin
            'fb_app_secret' => '' // To be filled by admin
        ];

        $insertSetting = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($default_settings as $key => $val) {
            $insertSetting->execute([$key, $val]);
        }

        // Example FAQs
        $stmt = $pdo->query("SELECT COUNT(*) FROM faqs");
        if ($stmt->fetchColumn() == 0) {
            $faqs = [
                [
                    'Do I need a Facebook App?',
                    'هل أحتاج إلى تطبيق فيسبوك؟',
                    'Yes, you will need to link your own Facebook App or use our verified app system to connect your pages.',
                    'نعم، ستحتاج لربط تطبيق فيسبوك الخاص بك أو استخدام تطبيقنا الموثق لربط الصفحات.'
                ],
                [
                    'Is messaging safe?',
                    'هل المراسلة آمنة؟',
                    'We strictly follow Facebook Message Tags policies to ensure safety. However, always ensure your content is compliant.',
                    'نحن نتبع سياسات وسوم رسائل فيسبوك بدقة. ومع ذلك، تأكد دائماً من أن محتواك متوافق مع المعايير.'
                ]
            ];
            $insertFaq = $pdo->prepare("INSERT INTO faqs (question_en, question_ar, answer_en, answer_ar, sort_order) VALUES (?, ?, ?, ?, 0)");
            foreach ($faqs as $arr) {
                $insertFaq->execute($arr);
            }
        }

        // Create Uploads Directory
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        if (!is_dir('uploads/avatars')) {
            mkdir('uploads/avatars', 0755, true);
        }

        // Create Config File
        $config_content = "<?php\n";
        $config_content .= "define('DB_HOST', '$db_host');\n";
        $config_content .= "define('DB_USER', '$db_user');\n";
        $config_content .= "define('DB_PASS', '$db_pass');\n";
        $config_content .= "define('DB_NAME', '$db_name');\n";
        $config_content .= "\ntry {\n";
        $config_content .= "    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS, [\n";
        $config_content .= "        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\"\n";
        $config_content .= "    ]);\n";
        $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $config_content .= "} catch(PDOException \$e) {\n";
        $config_content .= "    // If connection fails and install.php exists, redirect to installation\n";
        $config_content .= "    if (file_exists(__DIR__ . '/../install.php')) {\n";
        $config_content .= "        \$script_path = str_replace('\\\\', '/', \$_SERVER['PHP_SELF']);\n";
        $config_content .= "        \$base_dir = dirname(dirname(\$script_path));\n";
        $config_content .= "        // Simple heuristic for redirection\n";
        $config_content .= "        if (file_exists('install.php')) { header(\"Location: install.php\"); exit; }\n";
        $config_content .= "        if (file_exists('../install.php')) { header(\"Location: ../install.php\"); exit; }\n";
        $config_content .= "    }\n";
        $config_content .= "    die(\"ERROR: Could not connect. \" . \$e->getMessage());\n";
        $config_content .= "}\n";

        file_put_contents('includes/db_config.php', $config_content);

        // Redirect to success page
        header("Location: install_success.php");
        exit;

    } catch (PDOException $e) {
        $message = __t('install_error') . $e->getMessage();
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('page_title'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;700&family=Outfit:wght@300;400;500;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: '<?php echo $lang == 'ar' ? 'IBM Plex Sans Arabic' : 'Outfit'; ?>', sans-serif;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .gradient-text {
            background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Blob Animation */
        @keyframes blob {
            0% {
                transform: translate(0px, 0px) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }

            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }

        .animate-blob {
            animation: blob 7s infinite;
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }

        .animation-delay-4000 {
            animation-delay: 4s;
        }
    </style>
</head>

<body class="bg-slate-900 text-white min-h-screen py-10 relative overflow-x-hidden">
    <!-- Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div
            class="absolute top-0 -left-4 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
        </div>
        <div
            class="absolute top-0 -right-4 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
        </div>
        <div
            class="absolute -bottom-8 left-20 w-96 h-96 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000">
        </div>
    </div>

    <div class="container mx-auto px-4 relative z-10 max-w-3xl">

        <!-- Language Switcher -->
        <div class="absolute top-0 <?php echo $lang == 'ar' ? 'left-0' : 'right-0'; ?> p-4">
            <a href="?lang=<?php echo $lang == 'ar' ? 'en' : 'ar'; ?>"
                class="glass-card px-4 py-2 rounded-full text-sm font-bold hover:bg-white/10 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5h12M9 3v2m1.048 9.5A9.956 9.956 0 013 13.917m12.353-5.533C14.373 5.066 12.062 1.639 9 2.5a4.12 4.12 0 00-3 3">
                    </path>
                </svg>
                <?php echo $lang == 'ar' ? 'English' : 'العربية'; ?>
            </a>
        </div>

        <!-- Header -->
        <div class="text-center mb-10 pt-10">
            <div
                class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-indigo-500/30 transform rotate-3 hover:rotate-6 transition-all duration-300">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold mb-3"><?php echo __t('header_title'); ?></h1>
            <p class="text-indigo-200 text-lg"><?php echo __t('header_subtitle'); ?></p>
        </div>

        <div class="glass-card rounded-[2rem] p-8 md:p-10 shadow-2xl border border-white/10">
            <?php if ($message): ?>
                <div
                    class="mb-8 p-4 rounded-2xl text-sm <?php echo $message_type == 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex-shrink-0 w-8 h-8 rounded-full <?php echo $message_type == 'success' ? 'bg-green-500/20' : 'bg-red-500/20'; ?> flex items-center justify-center">
                            <?php if ($message_type == 'error'): ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                    </path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <span class="font-medium"><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message_type != 'success'): ?>
                <form method="POST" class="space-y-8">
                    <!-- Database Settings -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-white/5">
                            <div
                                class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white"><?php echo __t('db_settings'); ?></h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('db_host'); ?></label>
                                <input type="text" name="db_host" value="localhost"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                    required>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('db_name'); ?></label>
                                <input type="text" name="db_name" value="marketation_db"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                    required>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('db_user'); ?></label>
                                <input type="text" name="db_user" value="root"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                    required>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('db_pass'); ?></label>
                                <input type="password" name="db_pass" placeholder="********"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600">
                            </div>
                        </div>
                    </div>

                    <!-- Site Settings -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-white/5">
                            <div
                                class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center text-purple-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white"><?php echo __t('site_settings'); ?></h3>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('site_url'); ?></label>
                            <input type="url" name="site_url"
                                value="<?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>"
                                class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                required>
                            <p class="text-xs text-gray-500"><?php echo __t('site_url_hint'); ?></p>
                        </div>
                    </div>

                    <!-- Admin Settings -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-white/5">
                            <div class="w-10 h-10 rounded-xl bg-pink-500/20 flex items-center justify-center text-pink-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white"><?php echo __t('admin_settings'); ?></h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('admin_email'); ?></label>
                                <input type="email" name="admin_email"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                    required>
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="block text-sm font-medium text-gray-400 ml-1 rtl:mr-1"><?php echo __t('admin_pass'); ?></label>
                                <input type="password" name="admin_pass"
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all placeholder-gray-600"
                                    required>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full group bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 hover:-translate-y-1 relative overflow-hidden">
                        <div
                            class="absolute inset-0 bg-white/20 blur-xl group-hover:opacity-100 opacity-0 transition-opacity duration-500">
                        </div>
                        <span class="flex items-center justify-center gap-2 relative z-10">
                            <?php echo __t('install_btn'); ?>
                            <svg class="w-5 h-5 <?php echo $lang == 'ar' ? 'transform rotate-180' : ''; ?>" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <p class="text-center text-slate-500 mt-8 text-sm">
            <?php echo __t('footer_copyright'); ?>
        </p>
    </div>
</body>

</html>