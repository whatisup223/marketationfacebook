<?php
require_once 'includes/functions.php';
$page_title = __('terms_of_service');
require_once 'includes/header.php';

$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');
?>

<div class="relative min-h-screen bg-gray-900 pt-24 pb-12 overflow-hidden">
    <!-- Background Elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div
            class="absolute top-0 right-0 w-1/2 h-1/2 bg-indigo-500/10 rounded-full blur-3xl transform translate-x-1/2 -translate-y-1/2">
        </div>
        <div
            class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-purple-500/10 rounded-full blur-3xl transform -translate-x-1/2 translate-y-1/2">
        </div>
    </div>

    <div class="relative z-10 container mx-auto px-6 max-w-4xl">
        <div
            class="glass-panel p-8 md:p-12 rounded-3xl border border-white/10 bg-gray-900/60 backdrop-blur-xl shadow-2xl">

            <h1
                class="text-3xl md:text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400 mb-8 text-center">
                <?php echo ($lang == 'ar') ? 'شروط الخدمة' : 'Terms of Service'; ?>
            </h1>

            <div class="prose prose-lg prose-invert max-w-none <?php echo $is_rtl ? 'text-right' : 'text-left'; ?>">

                <?php if ($lang == 'ar'): ?>
                    <!-- Arabic Content -->
                    <p class="text-gray-300 leading-relaxed mb-6">
                        مرحباً بك في ماركتيشن. يرجى قراءة شروط الخدمة هذه بعناية قبل استخدام موقعنا وتطبيقنا. باستخدامك
                        للخدمة، فإنك توافق على الالتزام بهذه الشروط.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">1. قبول الشروط</h3>
                    <p class="text-gray-400 mb-6">
                        من خلال الوصول إلى أو استخدام الخدمة، فإنك تقر بأنك قرأت وفهمت ووافقت على الالتزام بهذه الشروط. إذا
                        كنت لا توافق على هذه الشروط، فلا يجوز لك استخدام الخدمة.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">2. وصف الخدمة</h3>
                    <p class="text-gray-400 mb-6">
                        توفر ماركتيشن مجموعة متكاملة من أدوات التسويق الرقمي وإدارة علاقات العملاء، وتشمل:
                    </p>
                    <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                        <li><strong>الرد الآلي (Auto Reply):</strong> الرد التلقائي على تعليقات ورسائل الصفحات.</li>
                        <li><strong>استخراج البيانات (Leads Extraction):</strong> استخراج القوائم العامة لمراسلي الصفحات
                            التي تمتلك صلاحية إدارتها لغرض إعادة الاستهداف.</li>
                        <li><strong>الحملات (Campaigns):</strong> إرسال رسائل ترويجية جماعية عبر فيسبوك ماسنجر وواتساب.</li>
                        <li><strong>إدارة الحسابات:</strong> ربط وإدارة حسابات وصفحات متعددة من واجهة واحدة.</li>
                    </ul>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">3. سياسات الاستخدام (فيسبوك وواتساب)</h3>
                    <p class="text-gray-400 mb-4">عند استخدام خدماتنا، أنت توافق وتلتزم بما يلي:</p>
                    <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                        <li>الالتزام بشروط خدمة وسياسات منصة فيسبوك (Facebook Platform Terms).</li>
                        <li>الالتزام بسياسات واتساب التجارية (WhatsApp Business Policy).</li>
                        <li>عدم استخدام الخدمة لإرسال رسائل عشوائية (Spam) أو محتوى احتيالي أو مسيء.</li>
                        <li>أنت تتحمل المسؤولية الكاملة عن الحصول على موافقة العملاء قبل مراسلتهم (Opt-in) وفقاً للقوانين
                            المعمول بها.</li>
                    </ul>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">4. الدفع والاشتراكات</h3>
                    <p class="text-gray-400 mb-6">
                        بعض ميزات الخدمة قد تكون مدفوعة. أنت توافق على دفع جميع الرسوم المرتبطة باشتراكك. جميع المدفوعات غير
                        قابلة للاسترداد ما لم ينص القانون على خلاف ذلك.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">5. إخلاء المسؤولية</h3>
                    <p class="text-gray-400 mb-6">
                        يتم تقديم الخدمة "كما هي" و "كما هي متاحة". لا تقدم ماركتيشن أي ضمانات بأن الخدمة ستكون خالية من
                        الأخطاء أو عدم التوفر. نحن لسنا مسؤولين عن أي إجراءات تتخذها فيسبوك أو واتساب ضد حساباتك نتيجة
                        لمخالفة سياساتهم.
                    </p>

                <?php else: ?>
                    <!-- English Content -->
                    <p class="text-gray-300 leading-relaxed mb-6">
                        Welcome to Marketation. Please read these Terms of Service carefully before using our website and
                        application. By using the Service, you agree to be bound by these terms.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">1. Acceptance of Terms</h3>
                    <p class="text-gray-400 mb-6">
                        By accessing or using the Service, you acknowledge that you have read, understood, and agree to be
                        bound by these Terms. If you do not agree to these terms, you may not use the Service.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">2. Description of Service</h3>
                    <p class="text-gray-400 mb-6">
                        Marketation provides a comprehensive suite of digital marketing and CRM tools, including:
                    </p>
                    <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                        <li><strong>Auto Reply:</strong> Automated responses to page comments and messages.</li>
                        <li><strong>Leads Extraction:</strong> Extracting public lists of users who messaged your managed
                            pages for retargeting purposes.</li>
                        <li><strong>Campaigns:</strong> Sending bulk promotional messages via Facebook Messenger and
                            WhatsApp.</li>
                        <li><strong>Account Management:</strong> Linking and managing multiple accounts and pages from a
                            single interface.</li>
                    </ul>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">3. Usage Policies (Facebook & WhatsApp)</h3>
                    <p class="text-gray-400 mb-4">When using our services, you agree and adhere to the following:</p>
                    <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                        <li>Compliance with Facebook Platform Terms and Policies.</li>
                        <li>Compliance with WhatsApp Business Policy.</li>
                        <li>Not using the Service to send spam, fraudulent, or abusive content.</li>
                        <li>You are solely responsible for obtaining customer consent (Opt-in) prior to messaging them in
                            accordance with applicable laws.</li>
                    </ul>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">4. Payment and Subscriptions</h3>
                    <p class="text-gray-400 mb-6">
                        Some features of the Service may be paid. You agree to pay all fees associated with your
                        subscription. All payments are non-refundable unless otherwise required by law.
                    </p>

                    <h3 class="text-xl font-bold text-white mb-4 mt-8">5. Disclaimer</h3>
                    <p class="text-gray-400 mb-6">
                        The Service is provided "AS IS" and "AS AVAILABLE". Marketation makes no warranties that the service
                        will be error-free or uninterrupted. We are not liable for any actions taken by Facebook or WhatsApp
                        against your accounts due to policy violations.
                    </p>
                <?php endif; ?>

                <div class="border-t border-gray-700 mt-12 pt-8 text-center text-gray-500 text-sm">
                    <?php if ($lang == 'ar'): ?>
                        آخر تحديث: <?php echo date('Y/m'); ?> | شركة ماركتيشن
                    <?php else: ?>
                        Last Updated: <?php echo date('F Y'); ?> | Marketation Inc.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>