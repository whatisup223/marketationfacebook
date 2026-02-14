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

<div id="main-user-container" class="main-user-container flex min-h-screen bg-gray-900 font-sans"
    x-data="autoModerator()">
    <?php include '../includes/user_sidebar.php'; ?>

    <!-- Modals moved outside main to fix containing block issues (backdrop-filter) -->
    <div x-show="showDeleteModal" x-cloak x-transition.opacity
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div @click.away="showDeleteModal = false"
            class="bg-gray-900 border border-white/10 rounded-[2.5rem] p-8 max-w-sm w-full shadow-2xl text-center">
            <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('confirm_delete'); ?></h3>
            <p class="text-gray-500 text-sm mb-8"><?php echo __('delete_log_hint'); ?></p>
            <div class="flex gap-4">
                <button @click="showDeleteModal = false"
                    class="flex-1 py-4 bg-white/5 hover:bg-white/10 text-gray-300 rounded-2xl font-bold transition-all">
                    <?php echo __('cancel'); ?>
                </button>
                <button @click="deleteLog()" :disabled="deleting"
                    class="flex-1 py-4 bg-red-600 hover:bg-red-700 text-white rounded-2xl font-bold transition-all flex items-center justify-center gap-2">
                    <svg x-show="deleting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span x-text="deleting ? '...' : '<?php echo __('confirm'); ?>'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div x-show="showSuccessModal" x-cloak x-transition.opacity
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div @click.away="showSuccessModal = false"
            class="bg-[#0b0e14] border border-indigo-500/30 rounded-[3rem] p-10 max-w-sm w-full shadow-[0_0_50px_rgba(79,70,229,0.3)] text-center relative overflow-hidden group">
            <h3 class="text-2xl font-black text-white mb-4 tracking-tight" x-text="successMessage"></h3>
            <button @click="showSuccessModal = false"
                class="w-full py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-2xl font-black text-sm transition-all">
                <?php echo __('continue'); ?>
            </button>
        </div>
    </div>

    <main class="flex-1 flex flex-col bg-gray-900/50 backdrop-blur-md relative p-6">

        <!-- Header -->
        <div class="flex-none flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400">
                    <?php echo __('auto_moderator'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('auto_moderator_desc'); ?></p>
            </div>

            <template x-if="selectedPageId">
                <div class="flex items-center gap-3 p-3 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-sm">
                    <div class="w-2 h-2 rounded-full animate-pulse"
                        :class="debugInfo?.valid ? 'bg-green-500' : 'bg-red-500'">
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400"
                        x-text="debugInfo?.valid ? '<?php echo __('active'); ?>' : '<?php echo __('inactive'); ?>'"></span>
                    <div class="h-4 w-px bg-white/10 mx-1"></div>
                    <span class="text-xs font-bold text-white" x-text="selectedPageName"></span>
                </div>
            </template>
        </div>

        <!-- Top Row: Selector -->
        <div class="flex-none mb-8">
            <!-- Page Selector -->
            <div
                class="glass-panel p-6 rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl hover:border-indigo-500/20 transition-all shadow-xl max-w-2xl">
                <label class="block text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <div class="p-2 bg-indigo-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                    </div>
                    <?php echo __('select_page'); ?>
                </label>

                <div class="flex flex-col sm:flex-row gap-4 items-stretch">
                    <div class="relative group flex-1">
                        <select x-model="selectedPageId"
                            @change="updatePageName($event); loadRules(); fetchTokenDebug();"
                            class="w-full bg-black/40 border border-white/10 text-white text-sm rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 block p-3.5 pr-10 appearance-none transition-all group-hover:border-white/20">
                            <option value=""><?php echo __('select_page'); ?>...</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo htmlspecialchars($page['page_id']); ?>">
                                    <?php echo htmlspecialchars($page['page_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <template x-if="selectedPageId">
                        <div class="flex gap-2">
                            <button @click="subscribePage()"
                                class="flex-1 flex items-center justify-center gap-2 px-6 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 font-bold text-sm"
                                :class="subscribing ? 'opacity-50 cursor-not-allowed' : ''" :disabled="subscribing">
                                <svg x-show="!subscribing" class="w-4 h-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <svg x-show="subscribing" class="animate-spin h-4 w-4 text-white" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span
                                    x-text="subscribing ? '<?php echo __('processing'); ?>...' : '<?php echo __('activate_protection'); ?>'"></span>
                            </button>

                            <button @click="stopProtection()"
                                class="flex items-center justify-center gap-2 px-4 py-3.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/10 rounded-xl transition-all font-bold"
                                title="<?php echo __('stop_protection'); ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Main Body: Preview (Left) & Rules (Right) -->
        <!-- No Page Selected Hint -->
        <div x-show="!selectedPageId" class="mb-12">
            <div
                class="glass-panel p-20 rounded-[3rem] border border-white/5 border-dashed flex flex-col items-center justify-center text-center group transition-all hover:bg-white/5">
                <div
                    class="w-24 h-24 rounded-[2.5rem] bg-gray-800/50 flex items-center justify-center mb-8 border border-white/5 group-hover:scale-110 group-hover:rotate-6 transition-all duration-500">
                    <svg class="w-12 h-12 text-gray-600 group-hover:text-indigo-500 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                        </path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-3"><?php echo __('select_page_to_configure'); ?></h2>
                <p class="text-gray-500 max-w-sm"><?php echo __('unselected_page_hint'); ?></p>
            </div>
        </div>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-8 pb-20">

            <!-- Preview Card (Order 2 on mobile, Order 1 on desktop) -->
            <div class="lg:col-span-4 order-2 lg:order-1" x-show="selectedPageId">
                <div class="sticky top-24 space-y-6">
                    <div
                        class="glass-card rounded-[32px] border border-white/10 shadow-2xl overflow-hidden bg-[#18191a]">
                        <!-- Title Bar -->
                        <div class="bg-[#242526] border-b border-white/5 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                                <?php echo __('moderation_preview'); ?>
                            </h3>
                            <div class="flex gap-1">
                                <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                                <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                            </div>
                        </div>

                        <div class="p-4 bg-[#18191a] h-[480px] flex flex-col font-sans">
                            <!-- Page Post Header -->
                            <div class="flex items-center gap-3 mb-4 px-2">
                                <div
                                    class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center border border-white/10 shadow-lg text-white font-bold text-lg overflow-hidden">
                                    <template x-if="selectedPageName">
                                        <span x-text="selectedPageName.charAt(0).toUpperCase()"></span>
                                    </template>
                                </div>
                                <div class="space-y-0.5">
                                    <div class="font-bold text-gray-200 text-sm"
                                        x-text="selectedPageName || 'Marketation - ماركتيشن'">
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10px] text-gray-500 font-medium">
                                        <span><?php echo __('just_now'); ?></span>
                                        <span class="text-xs">&middot;</span>
                                        <svg class="w-3 h-3 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <!-- Fake Post Content Lines -->
                            <div class="px-2 space-y-2 mb-4 opacity-40">
                                <div class="w-full h-2 bg-gray-700 rounded-full"></div>
                                <div class="w-3/4 h-2 bg-gray-700 rounded-full"></div>
                            </div>

                            <div class="h-px bg-[#3e4042] w-full mb-4"></div>

                            <!-- Comments Thread -->
                            <div class="flex-1 overflow-y-auto pr-1 messenger-scrollbar space-y-4">

                                <!-- Dynamic Simulated Comments -->
                                <div class="space-y-4">
                                    <!-- Phone Example -->
                                    <template x-if="rules.hide_phones">
                                        <div class="flex gap-2 animate-in slide-in-from-left-4 duration-300">
                                            <div
                                                class="w-8 h-8 rounded-full bg-blue-500 flex-shrink-0 border border-black/20 flex items-center justify-center text-white text-xs">
                                                A</div>
                                            <div class="flex-1">
                                                <div
                                                    class="bg-[#3a3b3c] rounded-2xl px-3 py-2 inline-block text-[#e4e6eb] relative">
                                                    <div class="font-bold text-xs mb-0.5">Ahmed Ali</div>
                                                    <div class="text-[13px]">ممكن تكلمني على 01012345678؟</div>
                                                    <div class="absolute inset-0 rounded-2xl flex flex-col items-center justify-center p-2 text-white text-center"
                                                        :class="rules.action_type === 'hide' ? 'bg-indigo-950/80 backdrop-blur-[2px] border border-indigo-500/30' : 'bg-red-600 shadow-[0_0_20px_rgba(220,38,38,0.5)]'">
                                                        <span
                                                            class="text-[10px] font-black"><?php echo __('phone_violation'); ?></span>
                                                        <span class="text-[8px] font-bold uppercase opacity-80"
                                                            x-text="rules.action_type === 'hide' ? '<?php echo __('bot_action_hide'); ?>' : '<?php echo __('bot_action_delete'); ?>'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Link Example -->
                                    <template x-if="rules.hide_links">
                                        <div class="flex gap-2 animate-in slide-in-from-left-4 duration-300">
                                            <div
                                                class="w-8 h-8 rounded-full bg-green-500 flex-shrink-0 border border-black/20 flex items-center justify-center text-white text-xs">
                                                S</div>
                                            <div class="flex-1">
                                                <div
                                                    class="bg-[#3a3b3c] rounded-2xl px-3 py-2 inline-block text-[#e4e6eb] relative">
                                                    <div class="font-bold text-xs mb-0.5">Sami J.</div>
                                                    <div class="text-[13px]">Check this out: www.mysite.com</div>
                                                    <div class="absolute inset-0 rounded-2xl flex flex-col items-center justify-center p-2 text-white text-center"
                                                        :class="rules.action_type === 'hide' ? 'bg-indigo-950/80 backdrop-blur-[2px] border border-indigo-500/30' : 'bg-red-600 shadow-[0_0_20px_rgba(220,38,38,0.5)]'">
                                                        <span
                                                            class="text-[10px] font-black"><?php echo __('link_violation'); ?></span>
                                                        <span class="text-[8px] font-bold uppercase opacity-80"
                                                            x-text="rules.action_type === 'hide' ? '<?php echo __('bot_action_hide'); ?>' : '<?php echo __('bot_action_delete'); ?>'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Keyword Example -->
                                    <template x-if="rules.banned_keywords && rules.banned_keywords.trim().length > 0">
                                        <div class="flex gap-2 animate-in slide-in-from-left-4 duration-300">
                                            <div
                                                class="w-8 h-8 rounded-full bg-purple-500 flex-shrink-0 border border-black/20 flex items-center justify-center text-white text-xs">
                                                M</div>
                                            <div class="flex-1">
                                                <div
                                                    class="bg-[#3a3b3c] rounded-2xl px-3 py-2 inline-block text-[#e4e6eb] relative">
                                                    <div class="font-bold text-xs mb-0.5">Mona K.</div>
                                                    <div class="text-[13px] truncate max-w-[160px]">
                                                        <?php echo __('simulated_keyword_comment'); ?>
                                                    </div>
                                                    <div class="absolute inset-0 rounded-2xl flex flex-col items-center justify-center px-4 text-white text-center"
                                                        :class="rules.action_type === 'hide' ? 'bg-indigo-950/80 backdrop-blur-[2px] border border-indigo-500/30' : 'bg-red-600 shadow-[0_0_20px_rgba(220,38,38,0.5)]'">
                                                        <span class="text-[10px] font-black truncate w-full"
                                                            x-text="'<?php echo __('keyword_violation'); ?>' + rules.banned_keywords.split(/[،,]/).slice(0, 1).map(k => k.trim()) + (rules.banned_keywords.split(/[،,]/).length > 1 ? '...' : '')"></span>
                                                        <span class="text-[8px] font-bold uppercase opacity-80"
                                                            x-text="rules.action_type === 'hide' ? '<?php echo __('bot_action_hide'); ?>' : '<?php echo __('bot_action_delete'); ?>'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Summary Hint -->
                                <div
                                    class="bg-indigo-600/5 rounded-2xl border border-indigo-500/10 p-4 text-center mt-4">
                                    <p class="text-[10px] font-bold text-indigo-300">
                                        <?php echo __('simulation_hint'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rules Area (Order 1 on mobile, Order 2 on desktop) -->
            <div class="lg:col-span-8 order-1 lg:order-2" x-show="selectedPageId">
                <div
                    class="glass-panel p-8 rounded-[2rem] border border-white/10 bg-gray-800/40 backdrop-blur-2xl hover:border-indigo-500/30 transition-all shadow-2xl relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600 opacity-50"></div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Left: Keywords & Activation -->
                        <div class="space-y-6">
                            <div class="flex justify-between items-center px-1">
                                <h3 class="text-xl font-bold text-white"><?php echo __('moderation_rules'); ?></h3>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-[10px] font-bold text-gray-500 uppercase"><?php echo __('active'); ?></span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" x-model="rules.is_active" class="sr-only peer">
                                        <div
                                            class="w-10 h-5 bg-gray-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full">
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('banned_keywords'); ?></label>
                                <textarea x-model="rules.banned_keywords" @input="updateModerationResult()" rows="5"
                                    class="w-full bg-black/40 border border-white/10 rounded-2xl p-5 text-white placeholder-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm leading-relaxed"
                                    placeholder="الكلمات الممنوعة، افصل بينها بفاصلة..."></textarea>
                            </div>
                        </div>

                        <!-- Right: Smart Options & Action -->
                        <div class="space-y-6">
                            <label
                                class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('smart_filtering'); ?></label>

                            <div class="space-y-3">
                                <div @click="rules.hide_phones = !rules.hide_phones; updateModerationResult()"
                                    class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5 cursor-pointer hover:bg-white/10 transition-all">
                                    <span class="text-sm font-bold text-white"><?php echo __('hide_phones'); ?></span>
                                    <div class="w-5 h-5 rounded-md border-2 border-indigo-500 flex items-center justify-center transition-colors"
                                        :class="rules.hide_phones ? 'bg-indigo-600' : ''">
                                        <svg x-show="rules.hide_phones" class="w-4 h-4 text-white" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>

                                <div @click="rules.hide_links = !rules.hide_links; updateModerationResult()"
                                    class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5 cursor-pointer hover:bg-white/10 transition-all">
                                    <span class="text-sm font-bold text-white"><?php echo __('hide_links'); ?></span>
                                    <div class="w-5 h-5 rounded-md border-2 border-indigo-500 flex items-center justify-center transition-colors"
                                        :class="rules.hide_links ? 'bg-indigo-600' : ''">
                                        <svg x-show="rules.hide_links" class="w-4 h-4 text-white" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('action_to_take'); ?></label>
                                <div class="flex gap-2">
                                    <button @click="rules.action_type = 'hide'; updateModerationResult()"
                                        class="flex-1 py-3 px-4 rounded-xl border-2 font-bold transition-all text-xs"
                                        :class="rules.action_type === 'hide' ? 'bg-indigo-600/20 border-indigo-600 text-white' : 'bg-black/40 border-white/5 text-gray-500'">
                                        <?php echo __('hide_action'); ?>
                                    </button>
                                    <button @click="rules.action_type = 'delete'; updateModerationResult()"
                                        class="flex-1 py-3 px-4 rounded-xl border-2 font-bold transition-all text-xs"
                                        :class="rules.action_type === 'delete' ? 'bg-red-600/20 border-red-600 text-white' : 'bg-black/40 border-white/5 text-gray-500'">
                                        <?php echo __('delete_action'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Settings -->
                    <div class="mt-8 pt-8 border-t border-white/5">
                        <button @click="saveRules()" :disabled="saving"
                            class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl font-bold shadow-xl shadow-indigo-600/20 transition-all flex items-center justify-center gap-3">
                            <svg x-show="saving" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
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

        <!-- Logs Section (Order 3 on mobile, Order 3 on desktop - will sit under Rules Area) -->
        <div class="lg:col-span-8 lg:col-start-5 order-3" x-show="selectedPageId">
            <div class="glass-panel p-8 rounded-[2rem] border border-white/10 bg-gray-800/40 backdrop-blur-2xl">
                <div class="flex justify-between items-center mb-6 px-1">
                    <h3 class="text-xl font-bold text-white"><?php echo __('moderation_logs'); ?></h3>
                    <button @click="loadLogs()"
                        class="text-indigo-400 hover:text-indigo-300 transition-all flex items-center gap-2"
                        :class="loadingLogs ? 'opacity-50' : ''" :disabled="loadingLogs">
                        <svg class="w-5 h-5" :class="loadingLogs ? 'animate-spin' : ''" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span
                            class="text-[10px] font-bold uppercase tracking-widest hidden md:inline"><?php echo __('sync_pages'); ?></span>
                    </button>
                </div>

                <div class="space-y-3 max-h-[500px] overflow-y-auto pr-2 messenger-scrollbar">
                    <template x-for="log in logs" :key="log.id">
                        <div
                            class="p-4 bg-black/40 rounded-2xl border border-white/5 flex items-center justify-between group">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center font-bold text-indigo-400"
                                    x-text="(log.sender_name || 'A').charAt(0).toUpperCase()"></div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white text-sm"
                                            x-text="log.user_name || 'Anonymous'"></span>
                                        <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase"
                                            :class="log.action_taken === 'hide' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-red-500/20 text-red-500'"
                                            x-text="log.action_taken === 'hide' ? '<?php echo __('bot_action_hide'); ?>' : '<?php echo __('bot_action_delete'); ?>'"></span>
                                        <template x-if="log.reason">
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[8px] font-bold bg-white/5 text-gray-400 border border-white/5"
                                                x-text="log.reason"></span>
                                        </template>
                                    </div>
                                    <p class="text-xs text-gray-500 italic mt-1 line-clamp-1" x-text="log.content">
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                <!-- Open on Facebook -->
                                <template x-if="log.comment_id">
                                    <a :href="'https://facebook.com/' + (log.comment_id.includes('_') ? log.comment_id : log.page_id + '_' + log.comment_id)"
                                        target="_blank"
                                        class="p-2 text-gray-400 hover:text-indigo-400 transition-colors"
                                        title="<?php echo __('view_on_facebook'); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </template>

                                <!-- Delete Log -->
                                <button @click="confirmDelete(log)"
                                    class="p-2 text-gray-400 hover:text-red-500 transition-colors"
                                    title="<?php echo __('delete'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="logs.length === 0">
                        <div class="py-12 text-center text-gray-600 italic text-sm">
                            <?php echo __('no_moderation_logs'); ?>
                        </div>
                    </template>
                </div>
            </div>
        </div>

</div> <!-- End Main Body Grid -->
</main> <!-- End Main -->

</div>

<style>
    .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .messenger-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .messenger-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.2);
        border-radius: 10px;
    }

    [x-cloak] {
        display: none !important;
    }
</style>

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
            loadingLogs: false,
            deleting: false,
            subscribing: false,
            showSuccessModal: false,
            successMessage: '',
            webhookUrl: '',
            verifyToken: '',
            testComment: '',
            moderationResult: { violated: false, reason: '' },

            init() {
                this.fetchWebhookInfo();
                this.$watch('testComment', () => this.updateModerationResult());
                this.$watch('rules', () => this.updateModerationResult(), { deep: true });
            },

            checkViolation(text) {
                if (!text || typeof text !== 'string') return { violated: false };

                // Keywords
                if (this.rules.banned_keywords) {
                    const keywords = this.rules.banned_keywords.split(/[،,]/).map(k => k.trim()).filter(k => k);
                    for (let k of keywords) {
                        if (text.toLowerCase().includes(k.toLowerCase())) {
                            return { violated: true, reason: '<?php echo __('keyword_violation'); ?>' + k };
                        }
                    }
                }

                // Phones
                if (this.rules.hide_phones) {
                    const phoneRegex = /(\d{8,15})|(\+?\d{1,4}?[-.\s]?\(?\d{1,3}?\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,4})/g;
                    if (phoneRegex.test(text)) {
                        return { violated: true, reason: '<?php echo __('phone_violation'); ?>' };
                    }
                }

                // Links
                if (this.rules.hide_links) {
                    const linkRegex = /(https?:\/\/[^\s]+)|(www\.[^\s]+)/gi;
                    if (linkRegex.test(text)) {
                        return { violated: true, reason: '<?php echo __('link_violation'); ?>' };
                    }
                }

                return { violated: false };
            },

            updateModerationResult() {
                this.moderationResult = this.checkViolation(this.testComment);
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

            async fetchTokenDebug() {
                if (!this.selectedPageId) return;
                try {
                    const res = await fetch(`ajax_moderator.php?action=get_token_debug&page_id=${this.selectedPageId}`);
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.debugInfo = result.data;
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
                    if (data.status === 'success') {
                        this.successMessage = data.message;
                        this.showSuccessModal = true;
                    } else {
                        alert(data.message);
                    }
                    this.fetchTokenDebug();
                } catch (e) { alert('Error'); }
                finally { this.subscribing = false; }
            },

            async stopProtection() {
                if (!this.selectedPageId) return;
                if (!confirm('<?php echo __('stop_protection'); ?>?')) return;
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
                } catch (e) { alert('Error'); }
            },

            updatePageName(event) {
                if (event && event.target && event.target.selectedIndex > 0) {
                    this.selectedPageName = event.target.options[event.target.selectedIndex].text.trim();
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
                } catch (e) { console.error(e); }
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
                } catch (e) { alert('Save failed'); }
                finally { this.saving = false; }
            },

            async loadLogs() {
                this.loadingLogs = true;
                try {
                    const res = await fetch('ajax_moderator.php?action=get_logs' + (this.selectedPageId ? '&page_id=' + this.selectedPageId : ''));
                    const result = await res.json();
                    if (result.status === 'success') {
                        this.logs = result.data;
                    }
                } catch (e) { console.error(e); }
                finally { this.loadingLogs = false; }
            },

            confirmDelete(log) {
                console.log('Confirm delete log:', log);
                this.logToDelete = log;
                this.showDeleteModal = true;
            },

            async deleteLog() {
                if (!this.logToDelete) return;
                console.log('Deleting log:', this.logToDelete.id);
                this.deleting = true;
                try {
                    const formData = new FormData();
                    formData.append('log_id', this.logToDelete.id);
                    const res = await fetch('ajax_moderator.php?action=delete_log', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    console.log('Delete result:', data);
                    this.showDeleteModal = false;
                    this.loadLogs();
                    this.logToDelete = null;
                } catch (e) {
                    console.error('Delete error:', e);
                    alert('Error deleting log');
                }
                finally { this.deleting = false; }
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>