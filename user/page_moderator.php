<?php
// user/page_moderator.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Fetch Pages
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id IN (
    SELECT MIN(p.id) 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? 
    GROUP BY p.page_id
) ORDER BY page_name ASC");
$stmt->execute([$user_id]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .messenger-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .messenger-scrollbar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 10px;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.2);
        border-radius: 10px;
        transition: all 0.3s;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(99, 102, 241, 0.4);
    }

    [x-cloak] {
        display: none !important;
    }
</style>

<div class="flex min-h-screen bg-[#0b0e14] text-gray-200 font-sans" x-data="autoModerator()">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <main class="flex-1 flex flex-col bg-[#0b0e14]/50 backdrop-blur-md relative p-6 overflow-hidden">
        <div class="max-w-full mx-auto w-full space-y-10">

            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <h1
                        class="text-4xl font-black bg-clip-text text-transparent bg-gradient-to-r from-white via-indigo-200 to-indigo-500 tracking-tight">
                        <?php echo __('auto_moderator'); ?>
                    </h1>
                    <p class="text-gray-500 mt-2 font-medium"><?php echo __('auto_moderator_desc'); ?></p>
                </div>

                <template x-if="selectedPageId">
                    <div
                        class="flex items-center gap-4 p-4 bg-white/5 rounded-[2rem] border border-white/10 backdrop-blur-xl shadow-2xl">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full animate-pulse"
                                :class="debugInfo?.valid ? 'bg-green-500' : 'bg-red-500'"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400"
                                x-text="debugInfo?.valid ? 'ACTIVE' : 'INACTIVE'"></span>
                        </div>
                        <div class="h-6 w-px bg-white/10"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-2xl bg-indigo-600/20 border border-indigo-500/20 flex items-center justify-center font-black text-indigo-400 text-sm"
                                x-text="selectedPageName ? selectedPageName.charAt(0).toUpperCase() : ''"></div>
                            <span class="text-sm font-bold text-white tracking-wide" x-text="selectedPageName"></span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Top Section: Setup & Webhook -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Page Selector -->
                <div class="lg:col-span-4">
                    <div
                        class="glass-panel p-8 rounded-[2.5rem] border border-white/10 bg-gray-800/20 backdrop-blur-3xl shadow-3xl h-full relative overflow-hidden group">
                        <div class="relative z-10 space-y-6">
                            <div>
                                <label
                                    class="block text-[10px] font-black text-indigo-400 uppercase tracking-[0.25em] mb-4"><?php echo __('select_page'); ?></label>
                                <div class="relative group/sel">
                                    <select x-model="selectedPageId"
                                        @change="updatePageName($event); loadRules(); fetchTokenDebug();"
                                        class="w-full bg-black/60 border border-white/10 text-white text-sm rounded-2xl focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-500 block p-5 pr-12 appearance-none transition-all hover:bg-black/80 backdrop-blur-md outline-none">
                                        <option value=""><?php echo __('select_page'); ?>...</option>
                                        <?php foreach ($pages as $page): ?>
                                            <option value="<?php echo htmlspecialchars($page['page_id']); ?>">
                                                <?php echo htmlspecialchars($page['page_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div
                                        class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500 group-hover/sel:text-indigo-400 transition-colors">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <!-- Activation Buttons -->
                            <template x-if="selectedPageId">
                                <div class="flex gap-4">
                                    <button @click="subscribePage()" :disabled="subscribing"
                                        class="flex-1 flex items-center justify-center gap-3 px-6 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white rounded-2xl transition-all shadow-xl shadow-indigo-600/20 font-black text-[11px] tracking-widest uppercase disabled:opacity-50 group/act">
                                        <svg x-show="!subscribing" class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span
                                            x-text="subscribing ? '...' : '<?php echo __('activate_protection'); ?>'"></span>
                                    </button>
                                    <button @click="stopProtection()"
                                        class="px-4 py-4 bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/10 rounded-2xl transition-all"><svg
                                            class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z">
                                            </path>
                                        </svg></button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Webhook Infrastructure -->
                <div class="lg:col-span-8">
                    <div
                        class="glass-panel p-8 rounded-[2.5rem] border border-white/10 bg-gray-800/20 backdrop-blur-3xl shadow-3xl h-full relative overflow-hidden group">
                        <div class="flex items-center justify-between mb-6">
                            <h4
                                class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                WEBHOOK INFRASTRUCTURE
                            </h4>
                        </div>
                        <!-- Webhook Warning Message -->
                        <div
                            class="mb-8 p-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-start gap-4">
                            <div
                                class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-500 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-xs font-black text-amber-400 uppercase tracking-widest mb-1">هام جداً
                                </h5>
                                <p class="text-[10px] font-bold text-amber-200/60 leading-relaxed">
                                    يجب إضافة رابط الـ Webhook وكلمة التحقق في إعدادات تطبيق فيسبوك الخاص بك (Webhooks
                                    -> Page -> feed) لضمان وصول التعليقات للنظام وحمايتها لحظياً.
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center px-1">
                                    <span
                                        class="text-[9px] font-bold text-gray-500 uppercase"><?php echo __('callback_url'); ?></span>
                                    <button @click="copyToClipboard(webhookUrl)"
                                        class="text-[9px] font-black text-indigo-400 hover:text-indigo-300">COPY</button>
                                </div>
                                <div class="p-4 bg-black/40 rounded-xl border border-white/5 text-[10px] font-mono text-indigo-200/60 truncate"
                                    x-text="webhookUrl"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center px-1">
                                    <span
                                        class="text-[9px] font-bold text-gray-500 uppercase"><?php echo __('verify_token'); ?></span>
                                    <button @click="copyToClipboard(verifyToken)"
                                        class="text-[9px] font-black text-indigo-400 hover:text-indigo-300">COPY</button>
                                </div>
                                <div class="p-4 bg-black/40 rounded-xl border border-white/5 text-[10px] font-mono text-indigo-200/60 truncate"
                                    x-text="verifyToken"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder (Visible when no page selected) -->
            <template x-if="!selectedPageId">
                <div
                    class="glass-panel p-20 rounded-[3rem] border border-white/5 border-dashed flex flex-col items-center justify-center text-center group transition-all hover:bg-white/5">
                    <div
                        class="w-24 h-24 rounded-[2.5rem] bg-gray-800/50 flex items-center justify-center mb-8 border border-white/5 group-hover:scale-110 group-hover:rotate-6 transition-all duration-500">
                        <svg class="w-12 h-12 text-gray-600 group-hover:text-indigo-500 transition-colors" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-black text-white mb-3"><?php echo __('select_page_to_configure'); ?></h2>
                    <p class="text-gray-500 max-w-sm font-medium"><?php echo __('unselected_page_hint'); ?></p>
                </div>
            </template>

            <!-- Middle Row: Preview & Rules (Visible when page selected) -->
            <div x-show="selectedPageId" x-transition.opacity
                class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
                <!-- Simulation & Preview Card -->
                <div class="h-fit sticky top-6">
                    <div
                        class="glass-panel p-1 rounded-[3.5rem] border border-white/5 bg-gray-900/40 shadow-3xl relative">
                        <div class="bg-[#18191a] rounded-[3rem] overflow-hidden flex flex-col shadow-inner">
                            <!-- Preview Top Bar -->
                            <div
                                class="px-8 py-5 bg-[#242526] border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-3.5 h-3.5 rounded-full bg-[#ff5f56]"></div>
                                    <div class="w-3.5 h-3.5 rounded-full bg-[#ffbd2e]"></div>
                                    <div class="w-3.5 h-3.5 rounded-full bg-[#27c93f]"></div>
                                </div>
                                <span
                                    class="text-[10px] font-black text-gray-500 uppercase tracking-[0.3em]"><?php echo __('moderation_preview'); ?></span>
                                <div class="w-10"></div>
                            </div>

                            <div class="p-8 flex-1 flex flex-col space-y-8">
                                <!-- FB Post Head -->
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-14 h-14 rounded-3xl bg-gradient-to-tr from-indigo-600 to-purple-600 p-[2px] shadow-lg shadow-indigo-600/20">
                                        <div class="w-full h-full bg-[#18191a] rounded-[22px] flex items-center justify-center font-black text-white text-2xl"
                                            x-text="selectedPageName ? selectedPageName.charAt(0).toUpperCase() : 'M'">
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-100 text-lg leading-tight"
                                            x-text="selectedPageName || 'Marketation - ماركتيشن'"></div>
                                        <div class="text-[11px] text-gray-500 flex items-center gap-1.5 mt-1 font-bold">
                                            Sponsored · <svg class="w-4 h-4 text-gray-500" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9v4a1 1 0 102 0V9a1 1 0 10-2 0zm0-2a1 1 0 102 0 1 1 0 10-2 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- Post Content Simulation -->
                                <div class="space-y-3 px-2 opacity-10">
                                    <div class="w-full h-3 bg-gray-500 rounded-full"></div>
                                    <div class="w-4/5 h-3 bg-gray-500 rounded-full"></div>
                                </div>

                                <div class="h-px bg-white/5 mx-2"></div>

                                <!-- Interactive Comment -->
                                <div class="flex-1 min-h-[250px] relative">
                                    <div class="flex gap-4">
                                        <div
                                            class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-600/20 border border-white/5 flex-shrink-0 flex items-center justify-center text-indigo-400">
                                            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1 space-y-2">
                                            <div
                                                class="bg-[#3a3b3c] rounded-[2rem] px-7 py-5 relative group/comment shadow-2xl transition-all border border-indigo-500/10">
                                                <div
                                                    class="text-[11px] font-black text-indigo-300 uppercase tracking-widest mb-1.5">
                                                    <?php echo __('customer_name_sample'); ?>
                                                </div>
                                                <input type="text" x-model="testComment"
                                                    placeholder="أدخل تعليقاً لاختبار القواعد..."
                                                    class="w-full bg-transparent border-none p-0 focus:ring-0 text-[15px] font-medium text-gray-200 placeholder-gray-600">

                                                <!-- Overlay Action -->
                                                <template x-if="moderationResult.violated">
                                                    <div
                                                        class="absolute inset-x-1 inset-y-1 bg-[#18191a]/98 rounded-[1.8rem] border-2 border-red-500/50 flex items-center justify-center backdrop-blur-3xl animate-in zoom-in slide-in-from-bottom-2 duration-300 px-8">
                                                        <div class="flex flex-col items-center gap-3 text-center">
                                                            <div
                                                                class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center text-red-500">
                                                                <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2.5"
                                                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                                </svg>
                                                            </div>
                                                            <div>
                                                                <div class="text-[11px] font-black text-red-400 uppercase tracking-[0.2em] mb-1"
                                                                    x-text="rules.action_type === 'hide' ? 'HIDDEN BY BOT' : 'PERMANENTLY DELETED'">
                                                                </div>
                                                                <div class="text-sm font-bold text-white leading-tight"
                                                                    x-text="moderationResult.reason"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                            <div
                                                class="flex gap-6 mt-3 ml-6 text-[11px] font-black text-gray-500 uppercase tracking-widest">
                                                <span class="cursor-pointer hover:text-indigo-400">Like</span>
                                                <span class="cursor-pointer hover:text-indigo-400">Reply</span>
                                                <span>Just now</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-indigo-600/5 rounded-3xl border border-indigo-500/10 p-6 text-center">
                                    <p class="text-xs font-bold text-indigo-300 leading-relaxed">
                                        <svg class="w-4 h-4 inline mr-2 mb-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        جرب كتابة رقم هاتفك أو كلمة مثل "سعر" لاختبار محاكي الحماية في الوقت الفعلي.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Detailed Rules Panel (Middle Right) -->
                    <div>
                        <div
                            class="glass-panel p-10 rounded-[3.5rem] border border-white/10 bg-gray-800/40 backdrop-blur-3xl shadow-3xl relative overflow-hidden group">
                            <div class="absolute top-0 left-0 w-2 h-full bg-indigo-600"></div>

                            <div class="flex items-center justify-between mb-12">
                                <div>
                                    <h3 class="text-2xl font-black text-white">
                                        <?php echo __('moderation_rules'); ?>
                                    </h3>
                                    <p class="text-gray-500 text-sm mt-1">تحديد القواعد والمعايير التي يقوم
                                        النظام عليها بالفحص والفلترة.</p>
                                </div>
                                <div
                                    class="flex items-center gap-4 px-6 py-4 bg-black/40 rounded-2xl border border-white/5">
                                    <span
                                        class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?php echo __('active'); ?></span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" x-model="rules.is_active" class="sr-only peer">
                                        <div
                                            class="w-12 h-7 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[22px] after:w-[22px] after:transition-all peer-checked:bg-indigo-600 shadow-inner">
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                                <!-- Column 1: Keywords -->
                                <div class="space-y-8">
                                    <div class="space-y-4">
                                        <label
                                            class="flex items-center gap-2 text-xs font-black text-gray-500 uppercase tracking-widest px-1">
                                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                            <?php echo __('banned_keywords'); ?>
                                        </label>
                                        <textarea x-model="rules.banned_keywords" @input="updateModerationResult()"
                                            rows="6"
                                            class="w-full bg-black/60 border border-white/5 rounded-[2rem] p-7 text-white placeholder-gray-700 focus:ring-8 focus:ring-indigo-500/10 outline-none transition-all resize-none text-[15px] leading-relaxed shadow-inner"
                                            placeholder="أدخل الكلمات المحظورة هنا، افصل بينها بفاصلة (،) أو (,)"></textarea>
                                        <div class="flex items-center gap-2 px-3 opacity-40">
                                            <div class="w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                                            <p class="text-[10px] font-bold text-gray-100 italic">مثال: سعر،
                                                بكام، منافس، كلمة مسيئة، رقم، رابط...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Column 2: Specific Filters -->
                                <div class="space-y-8 text-right">
                                    <div class="mb-2">
                                        <label
                                            class="block text-xs font-black text-indigo-400 uppercase tracking-widest px-1">خيارات
                                            الحماية الذكية</label>
                                        <p class="text-[10px] text-gray-500 mt-1 font-bold">تفعيل مرشحات الفحص التلقائي
                                            للبيانات الحساسة.</p>
                                    </div>

                                    <div class="space-y-4">
                                        <div @click="rules.hide_phones = !rules.hide_phones; updateModerationResult()"
                                            class="group/item flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/5 cursor-pointer hover:bg-white/10 hover:border-indigo-500/40 hover:translate-x-[-4px] transition-all">
                                            <div class="flex items-center gap-5">
                                                <div
                                                    class="w-12 h-12 rounded-2xl bg-red-500/10 border border-red-500/10 flex items-center justify-center text-red-500 group-hover/item:scale-110 transition-transform">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2.5"
                                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                    </svg>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-black text-white">
                                                        <?php echo __('hide_phones'); ?>
                                                    </div>
                                                    <div class="text-[10px] font-bold text-gray-500 mt-0.5">
                                                        اكتشاف أرقام الجوال تلقائياً</div>
                                                </div>
                                            </div>
                                            <div class="w-7 h-7 rounded-xl border-2 transition-all flex items-center justify-center"
                                                :class="rules.hide_phones ? 'bg-indigo-600 border-indigo-600 shadow-xl shadow-indigo-600/30' : 'border-white/10'">
                                                <svg x-show="rules.hide_phones" class="w-5 h-5 text-white" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>

                                        <div @click="rules.hide_links = !rules.hide_links; updateModerationResult()"
                                            class="group/item flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/5 cursor-pointer hover:bg-white/10 hover:border-indigo-500/40 hover:translate-x-[-4px] transition-all">
                                            <div class="flex items-center gap-5">
                                                <div
                                                    class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/10 flex items-center justify-center text-blue-500 group-hover/item:scale-110 transition-transform">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2.5"
                                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826L10.242 9.172a4 4 0 015.656 0l4 4a4 4 0 01-5.656 5.656l-1.102 1.101" />
                                                    </svg>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-black text-white">
                                                        <?php echo __('hide_links'); ?>
                                                    </div>
                                                    <div class="text-[10px] font-bold text-gray-500 mt-0.5">منع
                                                        الروابط والمواقع (URL)</div>
                                                </div>
                                            </div>
                                            <div class="w-7 h-7 rounded-xl border-2 transition-all flex items-center justify-center"
                                                :class="rules.hide_links ? 'bg-indigo-600 border-indigo-600 shadow-xl shadow-indigo-600/30' : 'border-white/10'">
                                                <svg x-show="rules.hide_links" class="w-5 h-5 text-white" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Type Choice -->
                                    <div class="space-y-4 pt-4">
                                        <label
                                            class="block text-xs font-black text-gray-500 uppercase tracking-widest px-1">الإجراء
                                            المتخذ</label>
                                        <div class="flex gap-4">
                                            <button @click="rules.action_type = 'hide'; updateModerationResult()"
                                                class="flex-1 p-6 rounded-3xl border-2 transition-all flex flex-col items-center gap-3 relative group/act"
                                                :class="rules.action_type === 'hide' ? 'bg-indigo-600/20 border-indigo-600 shadow-2xl shadow-indigo-600/20' : 'bg-black/40 border-white/5 opacity-50 grayscale hover:grayscale-0 hover:opacity-100'">
                                                <div
                                                    class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center transition-transform group-hover/act:scale-110">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29m-1.725-5.115l-1.854 4.811" />
                                                    </svg>
                                                </div>
                                                <span
                                                    class="text-[11px] font-black uppercase text-white tracking-widest leading-none"><?php echo __('hide_action'); ?></span>
                                                <template x-if="rules.action_type === 'hide'">
                                                    <div
                                                        class="absolute -top-2 -right-2 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center shadow-lg">
                                                        <svg class="w-3.5 h-3.5 text-white" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="4" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </div>
                                                </template>
                                            </button>
                                            <button @click="rules.action_type = 'delete'; updateModerationResult()"
                                                class="flex-1 p-6 rounded-3xl border-2 transition-all flex flex-col items-center gap-3 relative group/act"
                                                :class="rules.action_type === 'delete' ? 'bg-red-600/20 border-red-600 shadow-2xl shadow-red-600/20' : 'bg-black/40 border-white/5 opacity-50 grayscale hover:grayscale-0 hover:opacity-100'">
                                                <div
                                                    class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center transition-transform group-hover/act:scale-110">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2.5"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </div>
                                                <span
                                                    class="text-[11px] font-black uppercase text-white tracking-widest leading-none"><?php echo __('delete_action'); ?></span>
                                                <template x-if="rules.action_type === 'delete'">
                                                    <div
                                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-600 rounded-full flex items-center justify-center shadow-lg">
                                                        <svg class="w-3.5 h-3.5 text-white" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="4" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </div>
                                                </template>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Save -->
                            <div class="mt-12">
                                <button @click="saveRules()" :disabled="saving"
                                    class="w-full py-6 bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700 hover:from-indigo-500 hover:via-indigo-600 hover:to-purple-600 text-white rounded-[2.5rem] font-black text-xl shadow-[0_25px_50px_-12px_rgba(79,70,229,0.5)] transition-all transform active:scale-[0.97] flex items-center justify-center gap-4 group/save">
                                    <svg x-show="!saving"
                                        class="w-7 h-7 group-hover/save:rotate-12 transition-transform" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    <svg x-show="saving" class="animate-spin h-7 w-7" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    <span x-text="saving ? '...' : '<?php echo __('save_settings'); ?>'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Logs (Full Width) -->
                <div x-show="selectedPageId" x-transition.opacity class="mt-10 overflow-hidden">
                    <div
                        class="glass-panel p-9 rounded-[3.5rem] border border-white/5 bg-white/2 backdrop-blur-3xl shadow-3xl flex flex-col group">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-white"><?php echo __('moderation_logs'); ?>
                            </h3>
                            <button @click="loadLogs()"
                                class="p-3 bg-white/5 hover:bg-white/10 rounded-2xl border border-white/5 text-indigo-400 transition-all hover:rotate-180">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto pr-2 messenger-scrollbar min-h-[400px]">
                            <template x-for="log in logs.slice(0, 10)" :key="log.id">
                                <div
                                    class="p-5 bg-black/40 rounded-[2rem] border border-white/5 space-y-3 relative group/log hover:border-indigo-500/30 transition-all">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 flex items-center justify-center text-[10px] font-black text-indigo-400 border border-indigo-500/10"
                                                x-text="(log.sender_name || 'A').charAt(0)"></div>
                                            <div class="font-bold text-gray-100 text-xs truncate max-w-[100px]"
                                                x-text="log.sender_name || 'Anonymous'"></div>
                                        </div>
                                        <div class="flex gap-2">
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-tighter"
                                                :class="log.action_taken === 'hide' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-red-500/20 text-red-400'"
                                                x-text="log.action_taken"></span>
                                            <button @click="confirmDelete(log)"
                                                class="text-gray-600 hover:text-red-500 transition-colors"><svg
                                                    class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg></button>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-500 italic line-clamp-2" x-text="log.comment_text">
                                    </p>
                                    <div class="flex justify-between items-center pt-2 border-t border-white/5">
                                        <span class="text-[9px] font-black text-indigo-400/50 uppercase tracking-widest"
                                            x-text="log.reason"></span>
                                        <span class="text-[9px] text-gray-700 font-mono"
                                            x-text="formatDate(log.created_at).split(',')[0]"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="logs.length === 0">
                                <div class="h-full flex flex-col items-center justify-center gap-4 opacity-20 py-20">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <p class="text-sm font-black uppercase tracking-[0.2em]">
                                        <?php echo __('no_moderation_logs'); ?>
                                    </p>
                                </div>
                            </template>
                        </div>

                        <button @click="loadLogs()"
                            class="w-full mt-6 py-4 bg-white/5 hover:bg-white/10 rounded-[1.5rem] border border-white/10 text-[10px] font-black uppercase tracking-widest text-gray-400 group-hover:text-indigo-400 transition-all">
                            <?php echo __('view_all_activity'); ?>
                        </button>
                    </div>
                </div>
            </div>
    </main>
    <!-- Delete Confirmation Modal -->
    <template x-if="showDeleteModal">
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            x-transition>
            <div @click.away="showDeleteModal = false"
                class="bg-gray-900 border border-white/10 rounded-[2rem] p-8 max-w-sm w-full shadow-2xl">
                <div
                    class="w-16 h-16 bg-red-500/20 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white text-center mb-2"><?php echo __('confirm_delete'); ?></h3>
                <p class="text-gray-400 text-center text-sm mb-8 leading-relaxed">
                    سيتم حذف التعليق نهائياً من <span class="text-white font-bold">فيسبوك ومن سجلات النظام</span>. هذا
                    الإجراء
                    غير قابل للتراجع.
                </p>
                <div class="flex gap-4">
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-3 px-6 bg-white/5 hover:bg-white/10 text-gray-300 rounded-xl font-bold transition-all">
                        <?php echo __('cancel'); ?>
                    </button>
                    <button @click="deleteLog()" :disabled="deleting"
                        class="flex-1 py-3 px-6 bg-red-600 hover:bg-red-500 text-white rounded-xl font-bold shadow-lg shadow-red-600/20 transition-all flex items-center justify-center gap-2">
                        <template x-if="deleting">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </template>
                        <span x-text="deleting ? '...' : '<?php echo __('confirm'); ?>'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function autoModerator() {
        return {
            selectedPageId: '',
            selectedPageName: '',
            saving: false,
            loading: false,
            rules: {
                banned_keywords: '',
                hide_phones: false,
                hide_links: false,
                action_type: 'hide',
                is_active: false
            },
            logs: [],
            debugInfo: null,
            showDeleteModal: false,
            logToDelete: null,
            deleting: false,
            subscribing: false,
            stopping: false,
            webhookUrl: '',
            verifyToken: '',
            testComment: '',

            moderationResult: { violated: false },

            init() {
                this.loadLogs();
                this.fetchWebhookInfo();

                // Real-time reactivity for simulation
                this.$watch('testComment', () => this.updateModerationResult());
                this.$watch('rules', () => {
                    console.log('Rules changed, updating result...');
                    this.updateModerationResult();
                }, { deep: true });
            },

            updateModerationResult() {
                if (!this.testComment) {
                    this.moderationResult = { violated: false };
                    return;
                }

                // Check Keywords
                if (this.rules.banned_keywords) {
                    const keywords = this.rules.banned_keywords.split(/[،,]/).map(k => k.trim()).filter(k => k);
                    for (let k of keywords) {
                        if (this.testComment.toLowerCase().includes(k.toLowerCase())) {
                            this.moderationResult = { violated: true, reason: 'كلمة محظورة: ' + k };
                            return;
                        }
                    }
                }

                // Check Phones
                if (this.rules.hide_phones) {
                    const phoneRegex = /(\+?\d{1,4}?[-.\s]?\(?\d{1,3}?\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,9})/g;
                    if (phoneRegex.test(this.testComment)) {
                        this.moderationResult = { violated: true, reason: 'رقم هاتف' };
                        return;
                    }
                }

                // Check Links
                if (this.rules.hide_links) {
                    const linkRegex = /(https?:\/\/[^\s]+)|(www\.[^\s]+)/gi;
                    if (linkRegex.test(this.testComment)) {
                        this.moderationResult = { violated: true, reason: 'رابط (URL)' };
                        return;
                    }
                }

                this.moderationResult = { violated: false };
            },

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => { alert('<?php echo __('copied'); ?>'); });
            },

            async fetchWebhookInfo() {
                try {
                    const res = await fetch('ajax_moderator.php?action=get_webhook_info');
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.webhookUrl = data.webhook_url;
                        this.verifyToken = data.verify_token;
                    }
                } catch (e) { console.error(e); }
            },

            async subscribePage() {
                if (!this.selectedPageId) return;
                this.subscribing = true;
                try {
                    const formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    const res = await fetch('ajax_moderator.php?action=subscribe_page', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message);
                    this.fetchTokenDebug();
                } catch (e) {
                    alert('Error subscribing');
                } finally {
                    this.subscribing = false;
                }
            },

            async stopProtection() {
                if (!this.selectedPageId) return;
                if (!confirm('هل أنت متأكد من إيقاف حماية هذه الصفحة؟')) return;
                this.stopping = true;
                try {
                    const formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    const res = await fetch('ajax_moderator.php?action=unsubscribe_page', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message);
                    this.fetchTokenDebug();
                } catch (e) {
                    alert('Error stopping');
                } finally {
                    this.stopping = false;
                }
            },

            updatePageName(event) {
                if (event && event.target) {
                    this.selectedPageName = event.target.options[event.target.selectedIndex].text.trim();
                } else {
                    const select = document.querySelector('select');
                    const option = select.options[select.selectedIndex];
                    this.selectedPageName = option ? option.text.trim() : '';
                }
            },

            async fetchTokenDebug() {
                if (!this.selectedPageId) return;
                try {
                    const res = await fetch(`ajax_moderator.php?action=get_token_debug&page_id=${this.selectedPageId}`);
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.debugInfo = result.data;
                    }
                } catch (e) {
                    console.error('Debug failed', e);
                }
            },

            async loadRules() {
                if (!this.selectedPageId) return;
                try {
                    const res = await fetch('ajax_moderator.php?action=get_rules&page_id=' + this.selectedPageId);
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.rules = {
                            banned_keywords: result.data.banned_keywords || '',
                            hide_phones: !!parseInt(result.data.hide_phones),
                            hide_links: !!parseInt(result.data.hide_links),
                            action_type: result.data.action_type || 'hide',
                            is_active: !!parseInt(result.data.is_active)
                        };
                        this.loadLogs();
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            async saveRules() {
                this.saving = true;
                try {
                    const formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    formData.append('banned_keywords', this.rules.banned_keywords);
                    if (this.rules.hide_phones) formData.append('hide_phones', '1');
                    if (this.rules.hide_links) formData.append('hide_links', '1');
                    formData.append('action_type', this.rules.action_type);
                    if (this.rules.is_active) formData.append('is_active', '1');

                    const res = await fetch('ajax_moderator.php?action=save_rules', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    alert(result.message);
                } catch (e) {
                    alert('فشل الحفظ');
                } finally {
                    this.saving = false;
                }
            },

            async loadLogs() {
                try {
                    const res = await fetch('ajax_moderator.php?action=get_logs' + (this.selectedPageId ? '&page_id=' + this.selectedPageId : ''));
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.logs = result.data;
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            formatDate(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleString();
            },

            confirmDelete(log) {
                this.logToDelete = log;
                this.showDeleteModal = true;
            },

            async deleteLog() {
                if (!this.logToDelete) return;
                this.deleting = true;
                try {
                    const formData = new FormData();
                    formData.append('log_id', this.logToDelete.id);
                    const res = await fetch('ajax_moderator.php?action=delete_log', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.showDeleteModal = false;
                        this.loadLogs();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    alert('Error deleting');
                } finally {
                    this.deleting = false;
                    this.logToDelete = null;
                }
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>