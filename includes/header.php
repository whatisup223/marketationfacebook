<?php
require_once __DIR__ . '/functions.php';

// Maintenance Mode Check
$m_mode = getSetting('maintenance_mode');
$script_name = basename($_SERVER['PHP_SELF']);
// Allowed pages during maintenance
$allowed_pages = ['login.php', 'maintenance.php', 'logout.php'];

// Check if maintenance is ON
if ($m_mode == '1' && !isset($_GET['skip_maintenance'])) {

    // Allow Admin to bypass
    if (isLoggedIn() && isAdmin()) {
        // Admin is logged in, let them proceed (maybe show banner later)
    } else {
        // Not logged in or not admin
        // Check if current page is allowed
        if (!in_array($script_name, $allowed_pages) && strpos($_SERVER['REQUEST_URI'], '/admin/') === false) {
            // If trying to access admin login, allow it
            if (strpos($_SERVER['REQUEST_URI'], 'admin/login.php') !== false) {
                // Allow
            } else {
                $redirect_path = 'maintenance.php';
                if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false) {
                    $redirect_path = '../maintenance.php';
                }
                header("Location: " . $redirect_path);
                exit;
            }
        }
    }
}

$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$font = $lang === 'ar' ? "'IBM Plex Sans Arabic', sans-serif" : "'Outfit', sans-serif";

// Determine path prefix for links
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$prefix = '';
if (strpos($script_dir, '/admin') !== false || strpos($script_dir, '/user') !== false) {
    $prefix = '../';
}

$current_user = null;
if (isLoggedIn()) {
    $pdo = getDB();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_user) {
            // User was deleted but session persists
            session_destroy();
            header("Location: " . $prefix . "login.php");
            exit;
        }

        // --- AUTO-TRIGGER LOGIC START ---
        // This makes the site 'alive'. Every page load checks for due campaigns.
        try {
            date_default_timezone_set('Africa/Cairo'); // Ensure consistent timezone
            $now_trigger = date('Y-m-d H:i:s');
            // Flip 'Scheduled' -> 'Running' if time passed
            $pdo->query("UPDATE campaigns SET status = 'running' WHERE status = 'scheduled' AND scheduled_at <= '$now_trigger'");
        } catch (Exception $e) { /* silent fail */
        }
        // --- AUTO-TRIGGER LOGIC END ---
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>" class="scroll-smooth dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $site_name = __('site_name');
    $site_desc = __('footer_description');

    // Fallback if footer_description is not set in settings but exists in translations as footer_desc
    if ($site_desc === 'footer_description') {
        $site_desc = __('footer_desc');
    }

    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $site_logo = getSetting('site_logo');
    $site_favicon = getSetting('site_favicon');
    $hero_image = getSetting('hero_image');

    // Get protocol and host
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];

    // Get the base path
    $script_path = $_SERVER['SCRIPT_NAME'];
    $base_path = preg_replace('~/(admin|user)/.*$~', '/', $script_path);
    if ($base_path === $script_path) {
        $base_path = dirname($script_path);
    }
    $base_url = $protocol . "://" . $host . rtrim($base_path, '/') . '/';

    // Image for social sharing
    $share_image = '';
    if ($hero_image) {
        $share_image = $base_url . 'uploads/' . $hero_image;
    } elseif ($site_logo) {
        $share_image = $base_url . 'uploads/' . $site_logo;
    }
    ?>
    <title><?php echo $site_name; ?></title>
    <meta name="description" content="<?php echo $site_desc; ?>">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $current_url; ?>">
    <meta property="og:title" content="<?php echo $site_name; ?>">
    <meta property="og:description" content="<?php echo $site_desc; ?>">
    <meta property="og:site_name" content="<?php echo $site_name; ?>">
    <?php if ($share_image): ?>
        <meta property="og:image" content="<?php echo $share_image; ?>">
        <meta property="og:image:secure_url" content="<?php echo $share_image; ?>">
        <meta property="og:image:type" content="image/png">
        <meta itemprop="image" content="<?php echo $share_image; ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo $current_url; ?>">
    <meta property="twitter:title" content="<?php echo $site_name; ?>">
    <meta property="twitter:description" content="<?php echo $site_desc; ?>">
    <?php if ($share_image): ?>
        <meta property="twitter:image" content="<?php echo $share_image; ?>">
    <?php endif; ?>

    <!-- Additional Meta Tags -->
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?php echo $site_name; ?>">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;700&family=Outfit:wght@300;400;500;700&display=swap"
        rel="stylesheet">

    <?php if (getSetting('site_favicon')): ?>
        <link rel="icon" type="image/png" href="<?php echo $prefix; ?>uploads/<?php echo getSetting('site_favicon'); ?>">
    <?php endif; ?>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        <?php if ($lang === 'ar'): ?>
                                                                                                                                                                        sans: ['IBM Plex Sans Arabic', 'sans-serif'],
                        <?php else: ?>
                                                                                                                                                                        sans: ['Outfit', 'sans-serif'],
                        <?php endif; ?>
                    },
                    colors: {
                        primary: '#6366f1',
                        secondary: '#ec4899',
                        dark: '#0f172a',
                    }
                }
            }
        }
    </script>
    <style>
        /* Base (Dark Mode Always) */
        body {
            font-family:
                <?php echo $font; ?>
            ;
            background-color: #0f172a;
            color: #fff;
        }

        /* Scroll behavior and offset for sticky header */
        html {
            scroll-behavior: smooth;
        }

        section[id] {
            scroll-margin-top: 5rem;
        }

        /* Glassmorphism Utilities - Dark Mode Only */
        .glass {
            background: rgba(30, 41, 59, 0.6);
            /* slate-800/60 */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .text-glow {
            text-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
        }

        /* Animated Background */
        .gradient-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -10;
            overflow: hidden;
            /* Adaptive background handles via Tailwind classes on body */
        }

        .g-blob {
            position: absolute;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            animation: move 25s infinite alternate;
            opacity: 0.4;
            /* Slightly lower for light mode */
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .dark .g-blob {
            opacity: 0.6;
            /* Higher for dark mode */
        }

        .g-blob:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -100px;
            left: -100px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(0, 0, 0, 0) 70%);
        }

        .g-blob:nth-child(2) {
            width: 500px;
            height: 500px;
            bottom: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.3) 0%, rgba(0, 0, 0, 0) 70%);
            animation-duration: 35s;
        }

        .g-blob:nth-child(3) {
            width: 400px;
            height: 400px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, rgba(0, 0, 0, 0) 70%);
            animation-duration: 45s;
        }

        @keyframes move {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(50px, 50px) scale(1.1);
            }
        }

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

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }

        .perspective-1000 {
            perspective: 1000px;
        }

        @keyframes floating {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .animate-float {
            animation: floating 6s ease-in-out infinite;
        }
    </style>
</head>

<body class="text-gray-100 antialiased min-h-screen flex flex-col bg-slate-900 transition-colors duration-300 dark"
    x-data="{ 
        scrolled: false, 
        mobileMenu: false
     }" :class="{ 'overflow-hidden': mobileMenu }" @keydown.escape.window="mobileMenu = false"
    @scroll.window="scrolled = (window.pageYOffset > 20)">
    <!-- Background -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="g-blob"></div>
        <div class="g-blob"></div>
        <div class="g-blob"></div>
    </div>

    <!-- Navigation -->
    <nav class="glass sticky top-0 z-50 w-full transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center flex-shrink-0">
                    <a href="<?php echo $prefix; ?>index.php"
                        class="flex items-center space-x-2 rtl:space-x-reverse whitespace-nowrap">
                        <?php if (getSetting('site_logo')): ?>
                            <img src="<?php echo $prefix; ?>uploads/<?php echo getSetting('site_logo'); ?>"
                                class="h-10 w-auto flex-shrink-0" alt="Logo">
                        <?php else: ?>
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-secondary flex-shrink-0 flex items-center justify-center text-white font-bold text-xl shadow-lg">
                                <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-2xl font-bold tracking-tight text-white whitespace-nowrap">
                            <?php echo __('site_name'); ?>
                        </span>
                    </a>
                </div>

                <!-- Desktop Menu (Adjusted for many items) -->
                <div
                    class="hidden lg:flex flex-1 items-center justify-center rtl:space-x-reverse space-x-1 2xl:space-x-4">
                    <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
                        <a href="<?php echo $prefix; ?>index.php"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_home_' . $lang, __('home')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#about-us"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_about_' . $lang, __('about_us')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#features"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_features_' . $lang, __('features')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#services"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_services_' . $lang, __('services_section')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#how-it-works"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_how_' . $lang, __('how_it_works')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#portfolio"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_portfolio_' . $lang, __('portfolio_section')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#tool-showcase"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_tools_' . $lang, __('extraction_tools')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#pricing"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_pricing_' . $lang, __('pricing')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#testimonials"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_testimonials_' . $lang, __('testimonials')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#faqs"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_faqs_' . $lang, __('faqs')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                        <a href="<?php echo $prefix; ?>index.php#contact"
                            class="text-[12px] 2xl:text-sm font-medium text-gray-300 hover:text-white transition-colors relative group whitespace-nowrap px-1">
                            <?php echo getSetting('nav_contact_' . $lang, __('contact')); ?>
                            <span
                                class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-500 transition-all group-hover:w-full"></span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $prefix; ?>index.php"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl shadow-lg shadow-indigo-500/30 transition-all text-sm font-bold flex items-center space-x-2 rtl:space-x-reverse hover:-translate-y-0.5 transform duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span><?php echo getSetting('nav_home_' . $lang, __('home')); ?></span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-4 rtl:space-x-reverse">
                    <!-- Global Persistent Icons -->
                    <div class="flex items-center space-x-1.5 sm:space-x-2 rtl:space-x-reverse">
                        <!-- Language Switcher -->
                        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => ($lang === 'ar' ? 'en' : 'ar')]))); ?>"
                            title="<?php echo $lang === 'ar' ? 'English' : 'عربي'; ?>"
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-indigo-600 hover:border-indigo-500 transition-all duration-300 flex-shrink-0">
                            <svg class="w-5 h-5 transition-transform duration-300 group-hover:scale-110" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129">
                                </path>
                            </svg>
                        </a>

                        <?php if (isLoggedIn() && $prefix !== ''): ?>
                            <!-- Notifications Dropdown -->
                            <?php
                            $notifications_unread_count = getUnreadCount($_SESSION['user_id']);
                            $notifications_list = getUnreadNotifications($_SESSION['user_id'], 5);
                            $h_pdo = getDB();
                            $h_stmt = $h_pdo->prepare("SELECT preferences FROM users WHERE id = ?");
                            $h_stmt->execute([$_SESSION['user_id']]);
                            $h_prefs = json_decode($h_stmt->fetchColumn() ?: '{}', true);
                            $header_is_muted = $h_prefs['notifications_muted'] ?? false;
                            ?>
                            <div class="relative" x-data="{ openNotifications: false }">
                                <button @click="openNotifications = !openNotifications"
                                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-indigo-600 hover:border-indigo-500 transition-all duration-300 relative">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                        </path>
                                    </svg>
                                    <?php if ($notifications_unread_count > 0): ?>
                                        <span
                                            class="absolute top-1.5 right-1.5 rtl:left-1.5 rtl:right-auto bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full border border-gray-900 leading-none">
                                            <?php echo $notifications_unread_count > 9 ? '9+' : $notifications_unread_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </button>

                                <!-- Dropdown -->
                                <div x-show="openNotifications" @click.away="openNotifications = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 translate-y-2"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 translate-y-2"
                                    class="absolute right-0 rtl:left-0 rtl:right-auto mt-2 w-80 bg-gray-900 border border-gray-700 rounded-xl shadow-2xl z-50 overflow-hidden"
                                    style="display: none;">

                                    <div class="px-4 py-3 border-b border-gray-800 flex justify-between items-center">
                                        <h3 class="text-sm font-bold text-white"><?php echo __('notifications'); ?></h3>
                                        <?php if ($notifications_unread_count > 0): ?>
                                            <div class="flex items-center gap-3">
                                                <span
                                                    class="text-xs text-indigo-400 font-bold bg-indigo-500/10 px-2 py-0.5 rounded-full border border-indigo-500/20">
                                                    <?php echo $notifications_unread_count > 9 ? '9+' : $notifications_unread_count; ?>
                                                </span>
                                                <button
                                                    @click="fetch('<?php echo $prefix; ?>includes/api/mark_all_notifications_read.php', {method: 'POST'}).then(() => window.location.reload());"
                                                    title="<?php echo __('mark_all_read'); ?>"
                                                    class="text-gray-500 hover:text-white transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 13l4 4L19 7M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Mute Toggle -->
                                        <button
                                            @click="fetch('<?php echo $prefix; ?>includes/api/toggle_notifications_mute.php', {method: 'POST'}).then(() => window.location.reload());"
                                            title="<?php echo $header_is_muted ? __('unmute_notifications') : __('mute_notifications'); ?>"
                                            class="<?php echo $header_is_muted ? 'text-red-400 hover:text-red-300' : 'text-gray-500 hover:text-white'; ?> transition-colors ml-2 rtl:mr-2 rtl:ml-0">
                                            <?php if ($header_is_muted): ?>
                                                <svg class="w-4 h-4" fill="none" notification-muted stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                                                        clip-rule="evenodd" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                                    </path>
                                                </svg>
                                            <?php endif; ?>
                                        </button>
                                    </div>

                                    <div class="max-h-64 overflow-y-auto">
                                        <?php if (count($notifications_list) > 0): ?>
                                            <?php foreach ($notifications_list as $notif): ?>
                                                <div
                                                    class="border-b border-gray-800 last:border-0 hover:bg-gray-800 transition-colors relative group">
                                                    <a href="<?php echo $prefix . $notif['link']; ?>" @click="fetch('<?php echo $prefix; ?>includes/api/mark_notification_read.php', {
                                                            method: 'POST',
                                                            headers: {'Content-Type': 'application/json'},
                                                            body: JSON.stringify({id: <?php echo $notif['id']; ?>})
                                                        });" class="block px-4 py-3">
                                                        <p class="text-xs text-indigo-400 font-bold mb-1">
                                                            <?php echo htmlspecialchars(__($notif['title'])); ?>
                                                        </p>
                                                        <p class="text-sm text-gray-300 line-clamp-2 leading-snug">
                                                            <?php
                                                            $msgText = $notif['message'];
                                                            $decoded = json_decode($msgText, true);
                                                            if ($decoded && is_array($decoded) && isset($decoded['key'])) {
                                                                $params = $decoded['params'] ?? [];
                                                                if (isset($decoded['param_keys']) && is_array($decoded['param_keys'])) {
                                                                    foreach ($decoded['param_keys'] as $idx) {
                                                                        if (isset($params[$idx])) {
                                                                            $params[$idx] = __($params[$idx]);
                                                                        }
                                                                    }
                                                                }
                                                                $msgText = vsprintf(__($decoded['key']), $params);
                                                            } else {
                                                                $msgText = __($msgText);
                                                            }
                                                            echo htmlspecialchars($msgText);
                                                            ?>
                                                        </p>
                                                        <p class="text-[10px] text-gray-500 mt-2">
                                                            <?php echo date('M d, H:i', strtotime($notif['created_at'])); ?>
                                                        </p>
                                                    </a>
                                                    <button title="<?php echo __('mark_read'); ?>" @click.stop="fetch('<?php echo $prefix; ?>includes/api/mark_notification_read.php', {
                                                            method: 'POST',
                                                            headers: {'Content-Type': 'application/json'},
                                                            body: JSON.stringify({id: <?php echo $notif['id']; ?>})
                                                        }).then(() => $el.closest('div').remove());"
                                                        class="absolute top-3 right-3 rtl:left-3 rtl:right-auto text-gray-600 hover:text-white opacity-0 group-hover:opacity-100 transition-opacity p-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="px-4 py-8 text-center text-gray-500 text-sm">
                                                <?php echo __('no_notifications'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-gray-800/50 p-2 text-center border-t border-gray-800">
                                        <a href="<?php echo isAdmin() ? $prefix . 'admin/notifications.php' : $prefix . 'user/notifications.php'; ?>"
                                            class="block text-center text-xs text-indigo-400 hover:text-indigo-300 font-bold py-1 transition-colors">
                                            <?php echo __('view_all_notifications'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile Icon (Always Visible) -->
                            <a href="<?php echo isAdmin() ? $prefix . 'admin/profile.php' : $prefix . 'user/profile.php'; ?>"
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-indigo-600 hover:border-indigo-500 transition-all duration-300 overflow-hidden group"
                                title="<?php echo __('profile'); ?>">
                                <?php if (isset($current_user) && $current_user['avatar']): ?>
                                    <img src="<?php echo $prefix . $current_user['avatar']; ?>"
                                        class="w-full h-full object-cover transition-transform group-hover:scale-110">
                                <?php else: ?>
                                    <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                <?php endif; ?>
                            </a>

                        <?php endif; ?>
                    </div>

                    <!-- Desktop Only Text Links -->
                    <div class="hidden lg:flex items-center space-x-4 rtl:space-x-reverse">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?php echo isAdmin() ? $prefix . 'admin/dashboard.php' : $prefix . 'user/dashboard.php'; ?>"
                                class="bg-gradient-to-r from-indigo-600/20 to-purple-600/20 hover:from-indigo-600/40 hover:to-purple-600/40 text-indigo-300 hover:text-white border border-indigo-500/30 px-5 py-2.5 rounded-xl transition-all duration-300 shadow-xl shadow-indigo-500/10 text-sm font-bold whitespace-nowrap flex items-center gap-2 group">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 group-hover:animate-pulse"></span>
                                <?php echo isAdmin() ? ($lang === 'ar' ? 'لوحة الإدارة' : 'Admin Panel') : ($lang === 'ar' ? 'لوحة التحكم' : 'Dashboard'); ?>
                            </a>
                            <a href="<?php echo $prefix; ?>logout.php"
                                class="bg-white/10 text-white hover:bg-white/20 px-4 py-2 rounded-lg transition-all border border-white/10 text-sm font-bold flex-shrink-0">
                                <?php echo __('logout'); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo $prefix; ?>login.php"
                                class="text-sm font-bold text-white bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-all shadow-md whitespace-nowrap flex-shrink-0">
                                <?php echo __('login'); ?>
                            </a>
                            <a href="<?php echo $prefix; ?>register.php"
                                class="text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg shadow-lg shadow-indigo-500/30 transition-all whitespace-nowrap flex-shrink-0">
                                <?php echo __('register'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile Sidebar Trigger -->
                    <div class="flex lg:hidden items-center">
                        <button @click="mobileMenu = !mobileMenu" type="button"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700/50 focus:outline-none transition-colors border border-white/5 bg-white/5">
                            <span class="sr-only">Open main menu</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': mobileMenu, 'inline-flex': !mobileMenu }" class="inline-flex"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': !mobileMenu, 'inline-flex': mobileMenu }" class="hidden"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>


    </nav>

    <!-- Mobile Side Drawer -->
    <div class="relative z-[60] lg:hidden" role="dialog" aria-modal="true" x-show="mobileMenu" style="display: none;">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" x-show="mobileMenu"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="mobileMenu = false">
        </div>

        <!-- Drawer -->
        <div class="fixed inset-y-0 right-0 z-[60] w-full overflow-y-auto bg-gray-900 px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-white/10 rtl:right-auto rtl:left-0 transition-transform"
            x-show="mobileMenu" x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:enter-start="translate-x-full rtl:-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full rtl:-translate-x-full">

            <div class="flex items-center justify-between">
                <a href="<?php echo $prefix; ?>index.php"
                    class="-m-1.5 p-1.5 flex items-center space-x-2 rtl:space-x-reverse">
                    <?php if (getSetting('site_logo')): ?>
                        <img src="<?php echo $prefix; ?>uploads/<?php echo getSetting('site_logo'); ?>" class="h-8 w-auto"
                            alt="Logo">
                    <?php else: ?>
                        <div
                            class="w-8 h-8 rounded-lg bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white font-bold shadow-lg">
                            <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-xl font-bold text-white"><?php echo __('site_name'); ?></span>
                </a>
                <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-400 hover:text-white"
                    @click="mobileMenu = false">
                    <span class="sr-only"><?php echo __('close_menu'); ?></span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Language Switcher (Always Visible) -->
            <div class="mt-4 mb-2 <?php echo isset($current_user) ? '' : 'pt-4 border-t border-white/10'; ?>">
                <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => ($lang === 'ar' ? 'en' : 'ar')]))); ?>"
                    class="flex items-center justify-center w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded-xl transition-all border border-gray-700 group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-white transition-colors <?php echo $lang === 'ar' ? 'ml-2' : 'mr-2'; ?>"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                    </svg>
                    <span><?php echo $lang === 'ar' ? 'English' : 'عربي'; ?></span>
                </a>
            </div>

            <?php if (isset($current_user) && $prefix !== ''): ?>
                <a href="<?php echo isAdmin() ? $prefix . 'admin/profile.php' : $prefix . 'user/profile.php'; ?>"
                    class="mt-4 flex items-center space-x-3 rtl:space-x-reverse p-3 bg-white/5 rounded-2xl border border-white/10 hover:bg-white/10 transition-all group">
                    <div class="flex-shrink-0">
                        <?php if ($current_user['avatar']): ?>
                            <img src="<?php echo $prefix; ?><?php echo $current_user['avatar']; ?>"
                                class="w-10 h-10 rounded-xl object-cover border border-indigo-500/30 group-hover:scale-105 transition-transform">
                        <?php else: ?>
                            <div
                                class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30 group-hover:scale-105 transition-transform">
                                <span
                                    class="text-indigo-400 font-bold"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white truncate group-hover:text-indigo-400 transition-colors">
                            <?php echo htmlspecialchars($current_user['name']); ?>
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            <span dir="ltr">
                                <?php
                                if (!empty($current_user['username'])) {
                                    echo '@' . htmlspecialchars($current_user['username']);
                                } else {
                                    echo htmlspecialchars($current_user['email']);
                                }
                                ?>
                            </span>
                        </p>
                    </div>
                </a>
            <?php endif; ?>

            <div class="mt-6 flow-root">
                <div class="-my-6 divide-y divide-gray-500/25">
                    <div class="space-y-2 py-6">
                        <?php
                        $is_frontend = (basename($_SERVER['PHP_SELF']) == 'index.php');
                        if ($is_frontend): ?>
                            <!-- Frontend Links (Synced with Landing Page) -->
                            <a href="<?php echo $prefix; ?>index.php" @click="mobileMenu = false"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-white hover:bg-gray-800"><?php echo getSetting('nav_home_' . $lang, __('home')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#about-us"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('about-us'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#about-us'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_about_' . $lang, __('about_us')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#features"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('features'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#features'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_features_' . $lang, __('features')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#services"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('services'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#services'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_services_' . $lang, __('services_section')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#how-it-works"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('how-it-works'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#how-it-works'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_how_' . $lang, __('how_it_works')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#portfolio"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('portfolio'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#portfolio'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_portfolio_' . $lang, __('portfolio_section')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#tool-showcase"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('tool-showcase'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#tool-showcase'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_tools_' . $lang, __('extraction_tools')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#pricing"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('pricing'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#pricing'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_pricing_' . $lang, __('pricing')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#testimonials"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('testimonials'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#testimonials'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_testimonials_' . $lang, __('testimonials')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#faqs"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('faqs'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#faqs'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_faqs_' . $lang, __('faqs')); ?></a>

                            <a href="<?php echo $prefix; ?>index.php#contact"
                                @click.prevent="mobileMenu = false; $nextTick(() => { const el = document.getElementById('contact'); if(el) { el.scrollIntoView({behavior: 'smooth'}); } else { window.location.href = '<?php echo $prefix; ?>index.php#contact'; } })"
                                class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-300 hover:text-white hover:bg-gray-800"><?php echo getSetting('nav_contact_' . $lang, __('contact')); ?></a>

                            <?php if (isLoggedIn()): ?>
                                <div class="mt-4 pt-4 border-t border-gray-800">
                                    <a href="<?php echo isAdmin() ? $prefix . 'admin/dashboard.php' : $prefix . 'user/dashboard.php'; ?>"
                                        class="mx-0 block rounded-lg px-3 py-2.5 text-base font-bold text-center text-white bg-indigo-600 shadow-lg hover:bg-indigo-500 transition-all">
                                        <?php echo isAdmin() ? ($lang === 'ar' ? 'لوحة الإدارة' : 'Admin Panel') : ($lang === 'ar' ? 'لوحة التحكم' : 'Control Panel'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Dashboard Links (When inside Admin/User panel) -->
                            <div class="mb-6">
                                <a href="<?php echo $prefix; ?>index.php"
                                    class="mx-0 block rounded-lg px-3 py-2.5 text-base font-bold text-center text-white bg-indigo-600 shadow-lg hover:bg-indigo-500 transition-all">
                                    ← <?php echo __('home'); ?>
                                </a>
                            </div>

                            <?php if (isAdmin()): ?>
                                <h3
                                    class="text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-800 pb-2 mb-4 pl-3 rtl:pr-3">
                                    <?php echo $lang === 'ar' ? 'لوحة الإدارة' : 'Admin Panel'; ?>
                                </h3>

                                <div class="space-y-1">
                                    <!-- Overview -->
                                    <a href="<?php echo $prefix; ?>admin/dashboard.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?>">
                                        <?php echo __('overview'); ?>
                                    </a>

                                    <!-- Accounts Group -->
                                    <div
                                        x-data="{ mAdmAcc: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['fb_accounts.php', 'users.php']) ? 'true' : 'false'; ?> }">
                                        <button @click="mAdmAcc = !mAdmAcc"
                                            class="-mx-3 w-full flex items-center justify-between rounded-xl px-4 py-3 text-base font-semibold text-gray-300 hover:bg-gray-800 transition-colors group">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                                <span><?php echo __('accounts_management'); ?></span>
                                            </div>
                                            <svg class="w-4 h-4 transition-transform duration-200"
                                                :class="mAdmAcc ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="mAdmAcc" x-transition class="pl-4 rtl:pr-4 space-y-1 mt-1">
                                            <a href="<?php echo $prefix; ?>admin/users.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('users'); ?></a>
                                            <a href="<?php echo $prefix; ?>admin/fb_accounts.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('fb_accounts'); ?></a>
                                        </div>
                                    </div>

                                    <!-- System Group -->
                                    <div
                                        x-data="{ mAdmSys: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'backup.php', 'system_update.php']) ? 'true' : 'false'; ?> }">
                                        <button @click="mAdmSys = !mAdmSys"
                                            class="-mx-3 w-full flex items-center justify-between rounded-xl px-4 py-3 text-base font-semibold text-gray-300 hover:bg-gray-800 transition-colors group">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span><?php echo __('system_management'); ?></span>
                                            </div>
                                            <svg class="w-4 h-4 transition-transform duration-200"
                                                :class="mAdmSys ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="mAdmSys" x-transition class="pl-4 rtl:pr-4 space-y-1 mt-1">
                                            <a href="<?php echo $prefix; ?>admin/settings.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('settings'); ?></a>
                                            <a href="<?php echo $prefix; ?>admin/backup.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('backup_restore'); ?></a>
                                            <a href="<?php echo $prefix; ?>admin/system_update.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'system_update.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                <?php echo __('system_update'); ?>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Notifications -->
                                    <a href="<?php echo $prefix; ?>admin/notifications.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?> flex justify-between items-center group">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                            <span><?php echo __('notifications'); ?></span>
                                        </div>
                                        <?php if ($notifications_unread_count > 0): ?>
                                            <span
                                                class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                <?php echo $notifications_unread_count > 9 ? '9+' : $notifications_unread_count; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>

                                    <!-- Support Tickets -->
                                    <a href="<?php echo $prefix; ?>admin/support.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?> flex justify-between items-center group">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                            </svg>
                                            <span><?php echo __('support_tickets'); ?></span>
                                        </div>
                                        <?php
                                        $m_admin_ticket_unread = getAdminTicketUnreadCount();
                                        if ($m_admin_ticket_unread > 0):
                                            ?>
                                            <span
                                                class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                <?php echo $m_admin_ticket_unread > 9 ? '9+' : $m_admin_ticket_unread; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>

                                    <!-- Profile -->
                                    <a href="<?php echo $prefix; ?>admin/profile.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?> flex items-center gap-3 group">
                                        <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span><?php echo __('profile'); ?></span>
                                    </a>
                                </div>

                            <?php else: ?>
                                <h3
                                    class="text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-800 pb-2 mb-4 pl-3 rtl:pr-3">
                                    <?php echo isAdmin() ? ($lang === 'ar' ? 'لوحة الإدارة' : 'Admin Panel') : ($lang === 'ar' ? 'لوحة التحكم' : 'Control Panel'); ?>
                                </h3>

                                <div class="space-y-1">
                                    <!-- Overview -->
                                    <a href="<?php echo $prefix; ?>user/dashboard.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?>">
                                        <?php echo __('overview'); ?>
                                    </a>

                                    <!-- Facebook Dropdown -->
                                    <div
                                        x-data="{ mFbOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['fb_accounts.php', 'page_inbox.php', 'create_campaign.php', 'campaign_reports.php', 'page_auto_reply.php', 'fb_scheduler.php']) ? 'true' : 'false'; ?> }">
                                        <button @click="mFbOpen = !mFbOpen"
                                            class="-mx-3 w-full flex items-center justify-between rounded-xl px-4 py-3 text-base font-semibold leading-7 text-gray-300 hover:bg-gray-800 transition-colors group">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400 transition-colors"
                                                    fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                                </svg>
                                                <span><?php echo __('facebook'); ?></span>
                                            </div>
                                            <svg class="w-4 h-4 transition-transform duration-200"
                                                :class="mFbOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="mFbOpen" x-transition class="pl-4 rtl:pr-4 space-y-1 mt-1">
                                            <a href="<?php echo $prefix; ?>user/fb_accounts.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('fb_accounts'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/page_inbox.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'page_inbox.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('manage_messages'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/create_campaign.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'create_campaign.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('setup_campaign'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/page_auto_reply.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'page_auto_reply.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('auto_reply'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/fb_scheduler.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'fb_scheduler.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('post_scheduler'); ?></a>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Dropdown -->
                                    <div
                                        x-data="{ mWaOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['wa_accounts.php', 'wa_bulk_send.php', 'wa_settings.php']) ? 'true' : 'false'; ?> }">
                                        <button @click="mWaOpen = !mWaOpen"
                                            class="-mx-3 w-full flex items-center justify-between rounded-xl px-4 py-3 text-base font-semibold leading-7 text-gray-300 hover:bg-gray-800 transition-colors group">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-green-400 transition-colors"
                                                    fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                                </svg>
                                                <span><?php echo __('whatsapp'); ?></span>
                                            </div>
                                            <svg class="w-4 h-4 transition-transform duration-200"
                                                :class="mWaOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="mWaOpen" x-transition class="pl-4 rtl:pr-4 space-y-1 mt-1">
                                            <a href="<?php echo $prefix; ?>user/wa_accounts.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'wa_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('wa_accounts'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/wa_bulk_send.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'wa_bulk_send.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('wa_bulk_send'); ?></a>
                                            <a href="<?php echo $prefix; ?>user/wa_settings.php"
                                                class="block rounded-lg px-4 py-2.5 text-sm font-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'wa_settings.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> transition-colors"><?php echo __('wa_settings'); ?></a>
                                        </div>
                                    </div>

                                    <!-- Campaign Reports -->
                                    <a href="<?php echo $prefix; ?>user/campaign_reports.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'campaign_reports.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?>">
                                        <?php echo __('campaign_reports'); ?>
                                    </a>

                                    <!-- Profile -->
                                    <a href="<?php echo $prefix; ?>user/profile.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?>">
                                        <?php echo __('profile'); ?>
                                    </a>

                                    <!-- Notifications -->
                                    <a href="<?php echo $prefix; ?>user/notifications.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?> flex justify-between items-center group">
                                        <div class="flex items-center gap-3">
                                            <span><?php echo __('notifications'); ?></span>
                                        </div>
                                        <?php if ($notifications_unread_count > 0): ?>
                                            <span
                                                class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                <?php echo $notifications_unread_count > 9 ? '9+' : $notifications_unread_count; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>

                                    <!-- Support Tickets -->
                                    <a href="<?php echo $prefix; ?>user/support.php"
                                        class="-mx-3 block rounded-xl px-4 py-3 text-base font-semibold leading-7 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'create_ticket.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'text-indigo-400 bg-indigo-600/10 border border-indigo-500/20' : 'text-gray-300 hover:bg-gray-800 transition-colors'; ?> flex justify-between items-center group">
                                        <span><?php echo __('support_tickets'); ?></span>
                                        <?php
                                        $m_user_ticket_unread = 0;
                                        if (isset($_SESSION['user_id'])) {
                                            $m_user_ticket_unread = getUserTicketUnreadCount($_SESSION['user_id']);
                                        }
                                        if ($m_user_ticket_unread > 0):
                                            ?>
                                            <span
                                                class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                <?php echo $m_user_ticket_unread > 9 ? '9+' : $m_user_ticket_unread; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!isLoggedIn()): ?>
                            <div class="mt-4 flex flex-col space-y-3">
                                <a href="<?php echo $prefix; ?>login.php"
                                    class="w-full text-center rounded-lg px-3 py-2.5 text-base font-bold text-white bg-gray-700 hover:bg-gray-600 shadow-md">
                                    <?php echo __('login'); ?>
                                </a>
                                <a href="<?php echo $prefix; ?>register.php"
                                    class="w-full text-center rounded-lg px-3 py-2.5 text-base font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/30">
                                    <?php echo __('register'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="py-6">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?php echo $prefix; ?>logout.php"
                                class="-mx-3 block rounded-lg px-3 py-2.5 text-base font-semibold leading-7 text-red-400 hover:bg-gray-800 border border-red-500/20 text-center"><?php echo __('logout'); ?></a>
                        <?php endif; ?>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-grow">