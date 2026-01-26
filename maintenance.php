<?php
require_once __DIR__ . '/includes/functions.php';

// If maintenance is OFF, redirect to home
if (getSetting('maintenance_mode') != '1') {
    header("Location: index.php");
    exit;
}

// If Admin, they shouldn't be here forcefully, but if they are, let them go back
if (isAdmin()) {
    // Optionally redirect admin to dashboard
    // header("Location: admin/dashboard.php");
    // exit;
}

$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$font = $lang === 'ar' ? "'IBM Plex Sans Arabic', sans-serif" : "'Outfit', sans-serif";
$message = $lang === 'ar' ? getSetting('maintenance_message_ar') : getSetting('maintenance_message_en');

// Fallback message if empty
if (empty($message)) {
    $message = $lang === 'ar' ? "الموقع تحت الصيانة حالياً. سنعود قريباً." : "The site is currently under maintenance. We will be back soon.";
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo __('site_name'); ?> -
        <?php echo __('maintenance_mode'); ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;700&family=Outfit:wght@300;400;500;700&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: [<?php echo $font; ?>],
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
        body {
            font-family:
                <?php echo $font; ?>
            ;
            background-color: #0f172a;
            color: #fff;
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .g-blob {
            position: absolute;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            animation: move 25s infinite alternate;
            opacity: 0.6;
            z-index: -1;
        }

        .g-blob:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -100px;
            left: -100px;
        }

        .g-blob:nth-child(2) {
            width: 500px;
            height: 500px;
            bottom: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.3) 0%, rgba(0, 0, 0, 0) 70%);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Background Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="g-blob"></div>
        <div class="g-blob"></div>
    </div>

    <div class="glass-card max-w-lg w-full rounded-2xl p-8 text-center relative z-10 border-t-4 border-yellow-500">
        <!-- Language Switcher -->
        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['lang' => ($lang === 'ar' ? 'en' : 'ar')]))); ?>"
            class="absolute top-4 left-4 rtl:left-auto rtl:right-4 text-xs font-bold text-gray-500 hover:text-white transition-colors border border-gray-700 rounded-lg px-2 py-1">
            <?php echo $lang === 'ar' ? 'English' : 'عربي'; ?>
        </a>

        <!-- Logo or Icon -->
        <?php $logo = getSetting('site_logo'); ?>
        <div class="mb-6 flex justify-center">
            <?php if ($logo): ?>
                <img src="uploads/<?php echo $logo; ?>" class="h-20 w-auto rounded-lg shadow-lg">
            <?php else: ?>
                <div
                    class="w-20 h-20 bg-yellow-500/10 rounded-full flex items-center justify-center mx-auto ring-4 ring-yellow-500/5">
                    <svg class="w-10 h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z">
                        </path>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <h1 class="text-3xl font-bold text-white mb-2">
            <?php
            $dynamic_site_name = getSetting('site_name_' . $lang, __('site_name'));
            echo htmlspecialchars($dynamic_site_name);
            ?>
        </h1>
        <p class="text-yellow-500 font-medium text-sm mb-4 uppercase tracking-widest">
            <?php echo __('maintenance_mode'); ?></p>

        <div class="h-1 w-16 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full mx-auto mb-6"></div>

        <p class="text-gray-300 text-lg leading-relaxed mb-8">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </p>

        <?php if (isAdmin()): ?>
            <div class="bg-indigo-500/10 border border-indigo-500/20 rounded-xl p-4 mb-6">
                <p class="text-indigo-300 text-sm font-bold mb-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 mr-2"></span>
                    <?php echo $lang == 'ar' ? 'أنت مسجل كمدير' : 'You are logged in as Admin'; ?>
                </p>
                <a href="admin/dashboard.php"
                    class="block w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition-colors">
                    <?php echo $lang == 'ar' ? 'الذهاب للوحة التحكم' : 'Go to Dashboard'; ?>
                </a>
            </div>
        <?php else: ?>
            <div class="flex justify-center gap-4">
                <a href="login.php"
                    class="text-sm text-gray-500 hover:text-white transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                    <?php echo __('login'); ?>
                </a>
                <a href="mailto:<?php echo getSetting('contact_email'); ?>"
                    class="text-sm text-gray-500 hover:text-white transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                    <?php echo __('contact_us'); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-gray-700/50 text-xs text-gray-600">
            &copy;
            <?php echo date('Y'); ?>
            <?php echo __('site_name'); ?>.
            <?php echo __('copyright'); ?>.
        </div>
    </div>
</body>

</html>