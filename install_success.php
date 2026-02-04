<?php
// Default Language
$lang = isset($_GET['lang']) && $_GET['lang'] == 'en' ? 'en' : 'ar';
$dir = $lang == 'ar' ? 'rtl' : 'ltr';

$trans = [
    'ar' => [
        'page_title' => 'تم التثبيت بنجاح - ماركتيشن',
        'success_title' => 'تم التثبيت بنجاح!',
        'success_subtitle' => 'منصة ماركتيشن جاهزة للعمل الآن',
        'congrats' => 'تهانينا! 🎊',
        'congrats_desc' => 'تم تثبيت النظام بنجاح وإنشاء قاعدة البيانات وجميع الجداول المطلوبة.',
        'warning_title' => 'تنبيه أمني مهم',
        'warning_desc' => 'يرجى حذف ملف <code class="bg-red-500/20 px-2 py-1 rounded text-red-300">install.php</code> فوراً من الخادم لأسباب أمنية!',
        'next_steps' => 'الخطوات التالية',
        'step_1' => 'قم بتسجيل الدخول إلى لوحة التحكم باستخدام البريد الإلكتروني وكلمة المرور التي أدخلتها',
        'step_2' => 'قم بضبط إعدادات فيسبوك وإنشاء التطبيق الخاص بك',
        'step_3' => 'ابدأ بإنشاء الخطط والباقات للمشتركين',
        'step_4' => 'احذف ملف <code class="bg-slate-700 px-2 py-0.5 rounded text-red-300">install.php</code> من الخادم',
        'login_btn' => 'لوحة التحكم',
        'home_btn' => 'الصفحة الرئيسية',
        'thank_you' => 'شكراً لاختيارك <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 font-bold hover:underline">ماركتيشن</a> - الحل المتكامل للتسويق الإلكتروني',
        'support_note' => 'إذا واجهت أي مشاكل، يرجى التواصل مع <a href="https://wa.me/201022035190" target="_blank" class="text-indigo-400 font-bold hover:underline">الدعم الفني</a>',
        'footer_copyright' => 'جميع الحقوق محفوظة © ' . date('Y') . ' لـ <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition-colors">ماركتيشن</a> للتسويق الإلكتروني والحلول البرمجيه'
    ],
    'en' => [
        'page_title' => 'Installation Successful - Marketation',
        'success_title' => 'Installation Successful!',
        'success_subtitle' => 'Marketation Platform is ready to use',
        'congrats' => 'Congratulations! 🎊',
        'congrats_desc' => 'System installed successfully. Database and tables have been created.',
        'warning_title' => 'Important Security Notice',
        'warning_desc' => 'Please delete <code class="bg-red-500/20 px-2 py-1 rounded text-red-300">install.php</code> file immediately from your server!',
        'next_steps' => 'Next Steps',
        'step_1' => 'Login to the dashboard using the email and password you provided',
        'step_2' => 'Configure Facebook settings and create your App',
        'step_3' => 'Start creating subscription plans',
        'step_4' => 'Delete <code class="bg-slate-700 px-2 py-0.5 rounded text-red-300">install.php</code> from server',
        'login_btn' => 'Dashboard',
        'home_btn' => 'Home Page',
        'thank_you' => 'Thank you for choosing <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 font-bold hover:underline">Marketation</a> - The integrated marketing solution',
        'support_note' => 'If you face any issues, please contact <a href="https://wa.me/201022035190" target="_blank" class="text-indigo-400 font-bold hover:underline">support</a>',
        'footer_copyright' => 'All rights reserved © ' . date('Y') . ' <a href="https://facebook.com/marketati0n/" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition-colors">Marketation</a> for digital marketing and programming solutions'
    ]
];

function __t($key)
{
    global $trans, $lang;
    return $trans[$lang][$key] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo __t('page_title'); ?>
    </title>
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

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }

            100% {
                stroke-dashoffset: 0;
            }
        }

        .checkmark-animation {
            stroke-dasharray: 100;
            animation: checkmark 0.8s ease-in-out forwards;
        }

        .delay-1 {
            animation-delay: 0.2s;
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards 0.2s;
        }

        .delay-2 {
            animation-delay: 0.4s;
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards 0.4s;
        }

        .delay-3 {
            animation-delay: 0.6s;
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards 0.6s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

    <!-- Language Switcher -->
    <div class="absolute top-0 <?php echo $lang == 'ar' ? 'left-0' : 'right-0'; ?> p-4 z-20">
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

    <div class="max-w-3xl w-full mx-auto px-4 relative z-10 my-10">
        <!-- Success Icon -->
        <div class="text-center mb-10 float-animation">
            <div class="inline-block relative">
                <div
                    class="w-32 h-32 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center shadow-2xl shadow-green-500/50 relative z-10">
                    <svg class="w-20 h-20 text-white checkmark-animation" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="absolute inset-0 bg-green-500/40 rounded-full blur-2xl animate-pulse"></div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="glass-card rounded-[2.5rem] shadow-2xl overflow-hidden border border-green-500/20">
            <!-- Header -->
            <div
                class="bg-gradient-to-r from-green-600/80 to-emerald-600/80 p-10 text-center relative overflow-hidden backdrop-blur-md">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="relative z-10">
                    <h1 class="text-4xl font-bold mb-2 text-white">
                        <?php echo __t('success_title'); ?>
                    </h1>
                    <p class="text-green-50 text-lg opacity-90">
                        <?php echo __t('success_subtitle'); ?>
                    </p>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8 md:p-10 space-y-8">
                <!-- Success Message -->
                <div class="bg-green-500/10 border border-green-500/20 rounded-2xl p-6 text-center delay-1">
                    <p class="text-xl text-green-400 font-bold mb-2">
                        <?php echo __t('congrats'); ?>
                    </p>
                    <p class="text-gray-300">
                        <?php echo __t('congrats_desc'); ?>
                    </p>
                </div>

                <!-- Important Notice -->
                <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-6 delay-2 flex items-start gap-4">
                    <div class="bg-red-500/20 p-2 rounded-lg flex-shrink-0">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-red-400 font-bold mb-1 text-lg">
                            <?php echo __t('warning_title'); ?>
                        </h3>
                        <p class="text-gray-300 text-sm leading-relaxed">
                            <?php echo __t('warning_desc'); ?>
                        </p>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="bg-slate-800/40 border border-slate-700/50 rounded-2xl p-8 delay-3">
                    <h3 class="text-xl font-bold mb-6 flex items-center gap-3 text-white">
                        <div class="p-2 bg-indigo-500/20 rounded-lg">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                                </path>
                            </svg>
                        </div>
                        <?php echo __t('next_steps'); ?>
                    </h3>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-4">
                            <span
                                class="bg-indigo-500 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold shadow-lg shadow-indigo-500/30">1</span>
                            <span class="text-gray-300 leading-tight">
                                <?php echo __t('step_1'); ?>
                            </span>
                        </li>
                        <li class="flex items-start gap-4">
                            <span
                                class="bg-indigo-500 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold shadow-lg shadow-indigo-500/30">2</span>
                            <span class="text-gray-300 leading-tight">
                                <?php echo __t('step_2'); ?>
                            </span>
                        </li>
                        <li class="flex items-start gap-4">
                            <span
                                class="bg-indigo-500 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold shadow-lg shadow-indigo-500/30">3</span>
                            <span class="text-gray-300 leading-tight">
                                <?php echo __t('step_3'); ?>
                            </span>
                        </li>
                        <li class="flex items-start gap-4">
                            <span
                                class="bg-indigo-500 text-white w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold shadow-lg shadow-indigo-500/30">4</span>
                            <span class="text-gray-300 leading-tight">
                                <?php echo __t('step_4'); ?>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <a href="login.php"
                        class="relative group overflow-hidden bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-600/30 hover:scale-[1.02] transition-all duration-300 text-center flex items-center justify-center gap-2">
                        <div
                            class="absolute inset-0 bg-white/20 blur-xl group-hover:opacity-100 opacity-0 transition-opacity duration-500">
                        </div>
                        <svg class="w-5 h-5 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                            </path>
                        </svg>
                        <span class="relative z-10">
                            <?php echo __t('login_btn'); ?>
                        </span>
                    </a>

                    <a href="index.php"
                        class="relative group overflow-hidden bg-white/5 hover:bg-white/10 text-white font-bold py-4 rounded-xl border border-white/10 hover:border-white/20 transition-all duration-300 text-center flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg>
                        <span>
                            <?php echo __t('home_btn'); ?>
                        </span>
                    </a>
                </div>

                <!-- Footer Note -->
                <div class="text-center pt-6 border-t border-white/5">
                    <p class="text-sm text-gray-400">
                        <?php echo __t('thank_you'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-8 text-center">
            <p class="text-gray-500 text-sm">
                <?php echo __t('support_note'); ?>
            </p>
            <p class="text-center text-slate-500 mt-4 text-xs">
                <?php echo __t('footer_copyright'); ?>
            </p>
        </div>
    </div>
</body>

</html>