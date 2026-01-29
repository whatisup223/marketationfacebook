<?php
require_once 'includes/functions.php';
$page_title = __('privacy_policy');
require_once 'includes/header.php';

$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');
?>

<div class="relative min-h-screen bg-gray-900 pt-24 pb-12 overflow-hidden">
    <!-- Background Elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute top-0 right-0 w-1/2 h-1/2 bg-indigo-500/10 rounded-full blur-3xl transform translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-purple-500/10 rounded-full blur-3xl transform -translate-x-1/2 translate-y-1/2"></div>
    </div>

    <div class="relative z-10 container mx-auto px-6 max-w-4xl">
        <div class="glass-panel p-8 md:p-12 rounded-3xl border border-white/10 bg-gray-900/60 backdrop-blur-xl shadow-2xl">
            
            <h1 class="text-3xl md:text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400 mb-8 text-center">
                <?php echo ($lang == 'ar') ? 'سياسة الخصوصية' : 'Privacy Policy'; ?>
            </h1>
            
            <div class="prose prose-lg prose-invert max-w-none <?php echo $is_rtl ? 'text-right' : 'text-left'; ?>">
                
                <?php if($lang == 'ar'): ?>
                <!-- Arabic Content -->
                <p class="text-gray-300 leading-relaxed mb-6">
                    مرحباً بكم في <strong>ماركتيشن (Marketation)</strong>. نحن نأخذ خصوصيتك على محمل الجد. توضح سياسة الخصوصية هذه كيفية جمعنا واستخدامنا وحمايتنا لمعلوماتك عند استخدام تطبيقنا وخدماتنا المرتبطة بالتسويق عبر فيسبوك وواتساب وإدارة الصفحات.
                </p>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">1. المعلومات التي نجمعها</h3>
                <p class="text-gray-400 mb-4">عند استخدامك لتطبيقنا، قد نجمع المعلومات التالية:</p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                    <li><strong>معلومات الحساب:</strong> الاسم، عنوان البريد الإلكتروني، وصورة الملف الشخصي (عبر تسجيل الدخول بفيسبوك).</li>
                    <li><strong>بيانات الصفحات والمجموعات:</strong> أسماء الصفحات التي تديرها، الرموز المميزة للوصول (Access Tokens)، ومعرفات الصفحات لغرض إدارة الردود والحملات.</li>
                    <li><strong>بيانات العملاء (Leads):</strong> نقوم باستخراج وتخزين البيانات العامة للمستخدمين الذين راسلوا صفحاتك (مثل الاسم ومعرف PSID) لتمكينك من إعادة استهدافهم بحملات رسائل، وذلك بناءً على طلبك وتصريحك.</li>
                    <li><strong>بيانات واتساب:</strong> أرقام الهواتف والرسائل التي يتم إدارتها عبر أدوات واتساب الخاصة بنا لغرض الارسال الجماعي.</li>
                    <li><strong>البيانات التفاعلية:</strong> التعليقات والرسائل التي يتم معالجتها عبر نظام الرد الآلي.</li>
                </ul>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">2. كيف نستخدم معلوماتك</h3>
                <p class="text-gray-400 mb-4">نستخدم المعلومات للأغراض التالية:</p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                    <li>تقديم خدماتنا الأساسية: إدارة الحملات، الرد الآلي على التعليقات، استخراج بيانات المراسلين، والإرسال الجماعي عبر واتساب وماسنجر.</li>
                    <li>تمكينك من إدارة علاقات العملاء (CRM) واستهدافهم برسائل ترويجية.</li>
                    <li>تحسين تجربة المستخدم وجودة الخدمة.</li>
                    <li>التواصل معك بخصوص التحديثات أو الدعم الفني.</li>
                </ul>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">3. بيانات فيسبوك</h3>
                <p class="text-gray-400 mb-6">
                    تطبيقنا يلتزم تماماً بسياسات منصة فيسبوك وشروط الخدمة. نحن نطلب فقط الأذونات الضرورية لعمل التطبيق (مثل `pages_manage_posts`, `pages_messaging`, `pages_read_engagement`). نحن لا نشارك بيانات المستخدم الخاصة بفيسبوك مع أي أطراف ثالثة خارجية، ولا نستخدمها لأي غرض غير معلن عنه في هذه السياسة.
                </p>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">4. حذف البيانات</h3>
                <p class="text-gray-400 mb-6">
                    لديك الحق في طلب حذف بياناتك المسجلة لدينا في أي وقت.
                    <br><br>
                    <strong>كيفية حذف البيانات:</strong>
                    <br>1- اذهب إلى إعدادات حساب فيسبوك الخاص بك > "التطبيقات ومواقع الويب".
                    <br>2- اختر تطبيق "Marketation" واضغط على "إزالة".
                    <br>3- أو يمكنك التواصل معنا مباشرة عبر صفحة "الدعم الفني" في الموقع لحذف حسابك وكل البيانات المرتبطة به نهائياً من خوادمنا.
                </p>

                <?php else: ?>
                <!-- English Content -->
                <p class="text-gray-300 leading-relaxed mb-6">
                    Welcome to <strong>Marketation</strong>. We take your privacy seriously. This Privacy Policy describes how we collect, use, and protect your information when you use our application and services related to Facebook & WhatsApp marketing and page management.
                </p>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">1. Information We Collect</h3>
                <p class="text-gray-400 mb-4">When you use our application, we may collect the following information:</p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                    <li><strong>Account Information:</strong> Name, email address, and profile picture (via Facebook Login).</li>
                    <li><strong>Pages Data:</strong> Names of pages you manage, Access Tokens, and Page IDs for the purpose of managing replies and campaigns.</li>
                    <li><strong>Leads Data:</strong> We extract and store public data of users who messaged your pages (e.g., Name, PSID) to enable you to retarget them with message campaigns, strictly based on your request and authorization.</li>
                    <li><strong>WhatsApp Data:</strong> Phone numbers and messages managed via our WhatsApp tools for bulk sending purposes.</li>
                    <li><strong>Interaction Data:</strong> Comments and messages processed via our auto-reply system.</li>
                </ul>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">2. How We Use Your Information</h3>
                <p class="text-gray-400 mb-4">We use the information for the following purposes:</p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 mb-6">
                    <li>Providing core services: Campaign management, Auto-reply, Messenger Leads Extraction, and WhatsApp/Messenger Bulk Sending.</li>
                    <li>Enabling Customer Relationship Management (CRM) and targeted promotional messaging.</li>
                    <li>Improving user experience and service quality.</li>
                    <li>Communicating with you regarding updates or technical support.</li>
                </ul>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">3. Facebook Data</h3>
                <p class="text-gray-400 mb-6">
                    Our application fully complies with Facebook Platform Policies. We only request permissions necessary for the app to function (e.g., `pages_manage_posts`, `pages_messaging`, `pages_read_engagement`). We do not share Facebook user data with any third parties, nor do we use it for any purpose other than those stated in this policy.
                </p>

                <h3 class="text-xl font-bold text-white mb-4 mt-8">4. Data Deletion Instructions</h3>
                <p class="text-gray-400 mb-6">
                    You have the right to request the deletion of your data stored with us at any time.
                    <br><br>
                    <strong>How to delete your data:</strong>
                    <br>1. Go to your Facebook Account Settings > "Apps and Websites".
                    <br>2. Select "Marketation" app and click "Remove".
                    <br>3. Alternatively, you can contact us directly via the "Support" page on our website to permanently delete your account and all associated data from our servers.
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