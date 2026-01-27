<?php
date_default_timezone_set('Africa/Cairo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Language Handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar';
}

$lang = $_SESSION['lang'];

// Translations
$translations = [
    'ar' => [
        'site_name' => 'ماركتيشن - صائد العملاء',
        'home' => 'الرئيسية',
        'login' => 'تسجيل الدخول',
        'register' => 'حساب جديد',
        'dashboard' => 'لوحة القيادة',
        'user_panel' => 'لوحة المستخدم',
        'admin_panel' => 'لوحة الإدارة',
        'logout' => 'تسجيل خروج',
        'hero_title' => 'استخرج عملاء فيسبوك بدقة عالية',
        'hero_subtitle' => 'استهدف العملاء المهتمين من صفحات المنافسين، الجروبات، والمنشورات، وقم بزيادة مبيعاتك فوراً.',
        'start_now' => 'ابدأ الاستخراج الآن',
        'view_pricing' => 'باقات الاشتراك',
        'footer_desc' => 'أقوى أداة لاستخراج واستهداف بيانات العملاء من فيسبوك.',
        'copyright' => 'جميع الحقوق محفوظة',
        'verified' => 'موثق',

        // Statuses
        'status_pending' => 'قيد الانتظار',
        'status_processing' => 'جاري العمل',
        'status_completed' => 'مكتمل',
        'status_failed' => 'فشل',
        'status_scheduled' => 'مجدولة',
        'status_running' => 'جارية',
        'status_open' => 'مفتوحة',
        'status_answered' => 'تم الرد',
        'status_solved' => 'تم الحل',
        'status_closed' => 'مغلقة',
        'status_active' => 'نشط',
        'status_inactive' => 'غير نشط',

        // Common
        'welcome' => 'مرحباً',
        'profile' => 'الملف الشخصي',
        'settings' => 'الإعدادات',
        'overview' => 'نظرة عامة',
        'users' => 'المستخدمين',
        'manage_users' => 'إدارة المستخدمين',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'save' => 'حفظ',
        'submit' => 'إرسال',
        'cancel' => 'إلغاء',
        'view' => 'عرض',
        'actions' => 'إجراءات',
        'download_csv' => 'تحميل CSV',
        'no_data' => 'لا توجد بيانات.',
        'confirm_delete' => 'هل أنت متأكد من الحذف؟ لا يمكن التراجع عن هذا الإجراء.',
        'id' => 'المعرف',
        'role_admin' => 'مدير',
        'role_user' => 'مستخدم',
        'close' => 'إغلاق',

        // Dashboard & Sidebar
        'fb_accounts' => 'إدارة الصفحات',
        'manage_messages' => 'إدارة الرسائل',
        'setup_campaign' => 'إعداد الحملات',
        'campaign_reports' => 'تقارير الحملات',
        'active_linked_accounts' => 'الحسابات المربوطة النشطة',
        'account_status' => 'حالة الحساب',
        'active_membership' => 'اشتراك نشط',
        'unread_responses' => 'ردود غير مقروءة',

        // Landing & Hero
        'features_title' => 'لماذا تستخدم أداتنا؟',
        'feature_1_title' => 'استهداف دقيق',
        'feature_1_desc' => 'نصل إلى المستخدمين المتفاعلين بالفعل مع صفحات ومناشير منافسيك.',
        'hero_badge' => 'الأداة رقم #1 للتسويق',
        'hero_feature' => 'دقة تصل إلى 99% في استخراج بيانات العملاء المهتمين فعلياً.',
        'fast_secure' => 'سريع وآمن',
        'happy_users' => '+5000 مسوق',
        'hero_image_settings' => 'إعدادات صورة الهيرو',
        'hero_image_desc' => 'اختر صورة تعبر عن خدماتك بشكل احترافي لتظهر في قسم الهيرو بجانب العناوين الرئيسية.',
        'upload_image' => 'رفع صورة جديد',
        'uploading' => 'جاري الرفع',
        'floating_badge' => 'الأيقونة العائمة',
        'sim_title' => 'محاكي الأرباح',
        'sim_desc' => 'توقع نتائج حملتك التسويقية',
        'sim_followers' => 'عدد متابعي الهدف',
        'sim_engagement' => 'نسبة التفاعل المتوقعة',
        'sim_total_leads' => 'إجمالي العملاء المستهدفين',
        'sim_accuracy' => 'دقة استخراج عالية',
        'sim_btn' => 'ابدأ الاستخراج الآن',

        // Extraction & Campaigns
        'campaigns' => 'الحملات الإعلانية',
        'create_campaign' => 'إنشاء حملة',
        'campaign_name' => 'اسم الحملة',
        'message_text' => 'نص الرسالة',
        'message_preview' => 'معاينة الرسالة',
        'target_leads' => 'العملاء المستهدفين',
        'sent' => 'تم الإرسال',
        'failed' => 'فشل',
        'progress' => 'التقدم',
        'total_leads' => 'إجمالي العملاء',
        'total_found' => 'العدد المستخرج',
        'no_campaigns_found' => 'لم يتم العثور على حملات حتى الآن.',
        'messenger' => 'ماسينجر',
        'floating_button' => 'أيقونة عائمة',
        'export_csv' => 'تصدير CSV',
        'campaign_deleted' => 'تم حذف الحملة بنجاح.',

        // Support
        'support_tickets' => 'الدعم الفني',
        'create_ticket' => 'تذكرة جديدة',
        'ticket_id' => 'رقم التذكرة',
        'no_tickets' => 'لا توجد تذاكر حالياً.',
        'view_details' => 'عرض التفاصيل',
        'subject' => 'الموضوع',
        'message_placeholder' => 'اشرح مشكلتك بالتفصيل هنا...',

        // Contact
        'contact_us' => 'اتصل بنا',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني',
        'msg_success' => 'تم إرسال رسالتك بنجاح!',

        // Notifs & Misc
        'notifications' => 'الإشعارات',
        'mark_all_read' => 'تحديد الكل كمقروء',
        'no_notifications' => 'لا توجد إشعارات',
        'api_status' => 'حالة الاتصال',
        'connected' => 'متصل',
        'not_connected' => 'غير متصل',
    ],
    'en' => [
        'site_name' => 'Marketation Extractor',
        'home' => 'Home',
        'login' => 'Login',
        'register' => 'Register',
        'dashboard' => 'Dashboard',
        'user_panel' => 'User Panel',
        'admin_panel' => 'Admin Panel',
        'logout' => 'Logout',
        'hero_title' => 'Extract High Quality FB Leads',
        'hero_subtitle' => 'Target interested customers from competitor pages, groups, and posts instantly.',
        'start_now' => 'Start Extraction',
        'view_pricing' => 'Pricing',
        'footer_desc' => 'The most powerful tool for extracting customer data from Facebook.',
        'copyright' => 'All Rights Reserved',
        'verified' => 'Verified',

        // Statuses
        'status_pending' => 'Pending',
        'status_processing' => 'Processing',
        'status_completed' => 'Completed',
        'status_failed' => 'Failed',
        'status_scheduled' => 'Scheduled',
        'status_running' => 'Running',
        'status_open' => 'Open',
        'status_answered' => 'Answered',
        'status_solved' => 'Solved',
        'status_closed' => 'Closed',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',

        // Common
        'welcome' => 'Welcome',
        'profile' => 'Profile',
        'settings' => 'Settings',
        'overview' => 'Overview',
        'users' => 'Users',
        'manage_users' => 'Manage Users',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
        'view' => 'View',
        'actions' => 'Actions',
        'download_csv' => 'Download CSV',
        'no_data' => 'No data found.',
        'confirm_delete' => 'Are you sure you want to delete this item? This cannot be undone.',
        'id' => 'ID',
        'role_admin' => 'Admin',
        'role_user' => 'User',
        'close' => 'Close',

        // Dashboard & Sidebar
        'fb_accounts' => 'FB Accounts',
        'manage_messages' => 'Manage Messages',
        'setup_campaign' => 'Setup Campaign',
        'campaign_reports' => 'Campaign Reports',
        'active_linked_accounts' => 'Active Linked Accounts',
        'account_status' => 'Account Status',
        'active_membership' => 'Active Membership',
        'unread_responses' => 'Unread Responses',

        // Landing & Hero
        'features_title' => 'Why Choose Us?',
        'hero_badge' => 'The #1 Tool for Marketing',
        'hero_feature' => '99% Accuracy in extracting genuinely interested leads.',
        'fast_secure' => 'Fast & Secure',
        'happy_users' => '+5000 Marketers',
        'hero_image_settings' => 'Hero Image Settings',
        'hero_image_desc' => 'Choose a professional image to represent your services in the hero section.',
        'upload_image' => 'Upload New Image',
        'uploading' => 'Uploading',
        'floating_badge' => 'Floating Badge',
        'sim_title' => 'Profit Simulator',
        'sim_desc' => 'Estimate your marketing ROI',
        'sim_followers' => 'Target Followers',
        'sim_engagement' => 'Expected Engagement',
        'sim_total_leads' => 'Total Targeted Leads',
        'sim_accuracy' => 'High extraction accuracy',
        'sim_btn' => 'Start Extracting Now',

        // Extraction & Campaigns
        'campaigns' => 'Campaigns',
        'create_campaign' => 'Create Campaign',
        'campaign_name' => 'Campaign Name',
        'message_text' => 'Message Text',
        'message_preview' => 'Message Preview',
        'target_leads' => 'Target Leads',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'progress' => 'Progress',
        'total_leads' => 'Total Leads',
        'no_campaigns_found' => 'No campaigns found yet.',
        'messenger' => 'Messenger',
        'floating_button' => 'Floating Icon',
        'export_csv' => 'Export CSV',
        'campaign_deleted' => 'Campaign deleted successfully.',

        // Support
        'support_tickets' => 'Support Tickets',
        'create_ticket' => 'New Ticket',
        'ticket_id' => 'Ticket ID',
        'no_tickets' => 'No tickets found.',
        'view_details' => 'View Details',
        'subject' => 'Subject',
        'message_placeholder' => 'Explain your issue in detail here...',

        // Contact
        'contact_us' => 'Contact Us',
        'phone' => 'Phone',
        'email' => 'Email',
        'msg_success' => 'Your message has been sent successfully!',

        // Notifs & Misc
        'notifications' => 'Notifications',
        'mark_all_read' => 'Mark All Read',
        'no_notifications' => 'No notifications',
        'api_status' => 'API Status',
        'connected' => 'Connected',
        'not_connected' => 'Not Connected',
    ],
];

function addNotification($user_id, $title, $message, $link = '#')
{
    $pdo = getDB();
    if (!$pdo)
        return false;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $link]);
}

function getUnreadNotifications($user_id, $limit = 5)
{
    $pdo = getDB();
    if (!$pdo)
        return [];
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadCount($user_id)
{
    $pdo = getDB();
    if (!$pdo)
        return 0;

    $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $prefs = json_decode($stmt->fetchColumn() ?: '{}', true);

    if (!empty($prefs['notifications_muted'])) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getAdminTicketUnreadCount()
{
    $pdo = getDB();
    if (!$pdo)
        return 0;
    $stmt = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE is_read_admin = 0");
    return (int) $stmt->fetchColumn();
}

function getUserTicketUnreadCount($user_id)
{
    $pdo = getDB();
    if (!$pdo)
        return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND is_read_user = 0");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

function markNotificationAsRead($notification_id, $user_id)
{
    $pdo = getDB();
    if (!$pdo)
        return false;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

function notifyAdmins($title, $message, $link = '#')
{
    $pdo = getDB();
    if (!$pdo)
        return;
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        addNotification($row['id'], $title, $message, $link);
    }
}

function getSetting($key, $default = null)
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        $pdo = getDB();
        if ($pdo) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

function __($key, $force_lang = null)
{
    global $translations, $lang;
    $current_lang = $force_lang ?? $lang;

    // Check if there's a dynamic setting for this key + current lang
    $dynamicKey = $key . '_' . $current_lang;
    $settingValue = getSetting($dynamicKey);
    if ($settingValue)
        return $settingValue;

    // Special case for site_name
    if ($key === 'site_name') {
        $settingValue = getSetting('site_name_' . $current_lang);
        if ($settingValue)
            return $settingValue;
    }

    return isset($translations[$current_lang][$key]) ? $translations[$current_lang][$key] : $key;
}

function getDB()
{
    global $pdo;
    if (isset($pdo))
        return $pdo;
    if (file_exists(__DIR__ . '/db_config.php')) {
        require_once __DIR__ . '/db_config.php';
        return $pdo;
    }
    return null;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>