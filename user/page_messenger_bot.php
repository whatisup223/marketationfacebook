<?php
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get User Pages via Account Join
$pdo = getDB();
$stmt = $pdo->prepare("SELECT p.* FROM fb_pages p 
                      JOIN fb_accounts a ON p.account_id = a.id 
                      WHERE a.user_id = ? 
                      GROUP BY p.page_id 
                      ORDER BY p.page_name ASC");
$stmt->execute([$_SESSION['user_id']]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex min-h-screen bg-gray-900 font-sans" x-data="autoReplyApp()">
    <?php include '../includes/user_sidebar.php'; ?>

    <main class="flex-1 flex flex-col bg-gray-900/50 backdrop-blur-md relative p-6">

        <!-- Header -->
        <div class="flex-none flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400">
                    <?php echo __('auto_reply_messages'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('auto_reply_desc'); ?></p>
            </div>

            <template x-if="selectedPageId">
                <div class="flex items-center gap-3 p-3 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-sm">
                    <div class="w-2 h-2 rounded-full animate-pulse" :class="debugInfo ? 'bg-green-500' : 'bg-red-500'">
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400"
                        x-text="debugInfo ? '<?php echo __('valid_token'); ?>' : '<?php echo __('invalid_token'); ?>'"></span>
                    <div class="h-4 w-px bg-white/10 mx-1"></div>
                    <span class="text-xs font-bold text-white" x-text="getPageName()"></span>
                </div>
            </template>
        </div>

        <!-- Top Row: Selector & Webhook -->
        <div class="flex-none grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Page Selector -->
            <div
                class="glass-panel p-6 rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl hover:border-indigo-500/20 transition-all shadow-xl">
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
                        <select x-model="selectedPageId" @change="fetchRules(); fetchTokenDebug();"
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
                                <span x-text="subscribing ? '...' : '<?php echo __('activate_auto_reply'); ?>'"></span>
                            </button>

                            <button @click="stopAutoReply()"
                                class="flex items-center justify-center gap-2 px-4 py-3.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/10 rounded-xl transition-all font-bold"
                                title="<?php echo __('stop_auto_reply_desc'); ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Token Debug Info -->
                <!-- Token Debug Info -->
                <template x-if="debugInfo && selectedPageId">
                    <div x-transition.opacity
                        class="mt-4 p-3 bg-black/40 rounded-xl border border-white/5 text-[10px] font-mono text-gray-500 flex justify-between items-center">
                        <span>Token: <span class="text-indigo-400/70"
                                x-text="debugInfo ? debugInfo.masked_token : ''"></span></span>
                        <span>Length: <span class="text-indigo-400/70"
                                x-text="debugInfo ? debugInfo.length : ''"></span></span>
                    </div>
                </template>
            </div>

            <!-- Webhook Settings -->
            <div
                class="glass-panel p-6 rounded-3xl border border-indigo-500/20 bg-indigo-500/5 backdrop-blur-xl relative overflow-hidden shadow-xl">
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-16 -mt-16 pointer-events-none">
                </div>

                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2 relative z-10">
                    <div class="p-2 bg-indigo-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <?php echo __('webhook_configuration'); ?>
                </h3>

                <p
                    class="text-[10px] text-amber-400/80 bg-amber-400/5 p-2 rounded-lg border border-amber-400/10 mb-4 leading-relaxed relative z-10">
                    <svg class="w-3 h-3 inline mr-1 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                    <?php echo __('webhook_global_warning'); ?>
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 relative z-10">
                    <!-- Callback URL -->
                    <div class="min-w-0">
                        <label
                            class="block text-[10px] font-bold text-indigo-300/70 uppercase tracking-widest mb-2"><?php echo __('callback_url'); ?></label>
                        <div class="flex gap-2">
                            <input type="text" readonly :value="webhookUrl"
                                class="flex-1 min-w-0 bg-black/30 border border-white/10 rounded-xl text-[11px] text-gray-300 p-2.5 font-mono truncate focus:outline-none">
                            <button @click="copyToClipboard(webhookUrl)"
                                class="p-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl text-white transition-all shadow-lg shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Verify Token -->
                    <div class="min-w-0">
                        <label
                            class="block text-[10px] font-bold text-indigo-300/70 uppercase tracking-widest mb-2"><?php echo __('verify_token'); ?></label>
                        <div class="flex gap-2">
                            <input type="text" readonly :value="verifyToken"
                                class="flex-1 min-w-0 bg-black/30 border border-white/10 rounded-xl text-[11px] text-gray-300 p-2.5 font-mono truncate focus:outline-none">
                            <button @click="copyToClipboard(verifyToken)"
                                class="p-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl text-white transition-all shadow-lg shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Body: Preview (Left) & Rules (Right) -->
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-8 pb-20">

            <!-- Left Side: Preview Card -->
            <div class="lg:col-span-4 order-2 lg:order-1">
                <div class="sticky top-24 space-y-6">
                    <div
                        class="glass-card rounded-[32px] border border-white/10 shadow-2xl overflow-hidden bg-[#18191a]">
                        <!-- Title Bar -->
                        <div class="bg-[#242526] border-b border-white/5 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                                <?php echo __('message_preview'); ?>
                            </h3>
                            <div class="flex gap-1">
                                <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                                <div class="w-2 h-2 rounded-full bg-gray-600"></div>
                            </div>
                        </div>

                        <div class="p-4 bg-[#18191a] h-[480px] flex flex-col font-sans">
                            <!-- Helper Text -->
                            <div class="text-center mb-6 mt-4">
                                <div
                                    class="w-16 h-16 rounded-full bg-[#0084ff] flex items-center justify-center mx-auto mb-2 text-white text-3xl font-bold shadow-lg overflow-hidden">
                                    <template x-if="getPageName()">
                                        <span x-text="getPageName().charAt(0).toUpperCase()"></span>
                                    </template>
                                    <template x-if="!getPageName()">
                                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                        </svg>
                                    </template>
                                </div>
                                <h4 class="font-bold text-white text-lg"
                                    x-text="getPageName() || 'Marketation - ماركتيشن'"></h4>
                                <p class="text-xs text-gray-500">Messenger • Very Responsive</p>
                            </div>

                            <div class="flex-1 overflow-y-auto px-2 messenger-scrollbar space-y-4">
                                <!-- User Message (Left) -->
                                <div class="flex items-end gap-2">
                                    <div class="w-6 h-6 rounded-full bg-gray-500 flex-shrink-0"></div>
                                    <div
                                        class="bg-[#303030] text-gray-200 rounded-2xl rounded-bl-none px-3 py-2 max-w-[80%] text-[13px]">
                                        <div class="font-bold text-[10px] text-gray-400 mb-0.5">
                                            <?php echo __('customer_name_sample'); ?>
                                        </div>
                                        <div
                                            x-text="previewMode === 'rule' ? previewCustomerMsg : '<?php echo __('customer_msg_sample'); ?>'">
                                        </div>
                                    </div>
                                </div>

                                <!-- Page Reply (Right) -->
                                <div class="flex items-end gap-2 justify-end">
                                    <div
                                        class="bg-[#0084ff] text-white rounded-2xl rounded-br-none px-3 py-2 max-w-[80%] text-[13px] shadow-lg">
                                        <div class="whitespace-pre-wrap leading-snug"
                                            x-text="previewMode === 'rule' ? previewReplyMsg : (defaultReplyText ? defaultReplyText : '<?php echo __('preview_empty_msg'); ?>')">
                                        </div>
                                    </div>
                                    <div
                                        class="w-6 h-6 rounded-full bg-[#0084ff] flex-shrink-0 flex items-center justify-center text-[10px] font-bold text-white overflow-hidden shadow-md">
                                        <template x-if="getPageName()">
                                            <span x-text="getPageName().charAt(0).toUpperCase()"></span>
                                        </template>
                                        <template x-if="!getPageName()">
                                            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                            </svg>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Rules Area -->
            <div class="lg:col-span-8 space-y-8 order-1 lg:order-2">

                <template x-if="!selectedPageId">
                    <div
                        class="glass-panel p-20 rounded-[3rem] border border-white/5 border-dashed flex flex-col items-center justify-center text-center group transition-all hover:bg-white/5">
                        <div
                            class="w-24 h-24 rounded-[2.5rem] bg-gray-800/50 flex items-center justify-center mb-8 border border-white/5 group-hover:scale-110 group-hover:rotate-6 transition-all duration-500">
                            <svg class="w-12 h-12 text-gray-600 group-hover:text-indigo-500 transition-colors"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                </path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-3"><?php echo __('select_page_to_configure'); ?>
                        </h2>
                        <p class="text-gray-500 max-w-sm"><?php echo __('unselected_page_hint'); ?></p>
                    </div>
                </template>

                <div x-show="selectedPageId" x-transition.opacity class="space-y-8" style="display: none;">
                    <!-- Default Reply Card -->
                    <div
                        class="glass-panel p-8 rounded-[2rem] border border-white/10 bg-gray-800/40 backdrop-blur-2xl hover:border-indigo-500/30 transition-all shadow-2xl relative overflow-hidden group">
                        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600 opacity-50"></div>

                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-2"><?php echo __('default_reply'); ?></h3>
                                <p class="text-gray-400 text-sm max-w-md"><?php echo __('default_reply_hint'); ?></p>
                            </div>
                            <button @click="saveDefaultReply()" :disabled="savingDefault"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 font-bold flex items-center gap-2 group-hover:scale-105">
                                <span x-show="!savingDefault"><?php echo __('save_changes'); ?></span>
                                <svg x-show="savingDefault" class="animate-spin h-4 w-4" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </button>
                        </div>

                        <textarea x-model="defaultReplyText" rows="4"
                            class="w-full bg-black/40 border border-white/10 rounded-2xl p-5 text-white placeholder-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all mb-6 text-base leading-relaxed"
                            placeholder="<?php echo __('reply_placeholder'); ?>"></textarea>

                    </div>

                    <!-- Keyword Rules Section -->
                    <div class="space-y-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-2xl font-bold text-white"><?php echo __('keyword_rules'); ?></h3>
                            <button @click="openAddModal()"
                                class="bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white px-5 py-2.5 rounded-xl transition-all border border-indigo-500/20 flex items-center gap-2 font-bold text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                <?php echo __('add_new_rule'); ?>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-4 max-h-[600px] overflow-y-auto pr-2 messenger-scrollbar">
                            <template x-for="rule in rules" :key="rule.id">
                                <div
                                    class="glass-panel p-6 rounded-2xl border border-white/5 bg-gray-800/20 hover:bg-gray-800/40 hover:border-indigo-500/30 transition-all flex justify-between items-center group shrink-0">
                                    <div class="flex items-center gap-4">
                                        <div class="p-3 bg-indigo-500/10 rounded-2xl text-indigo-400">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-1"
                                                x-text="'<?php echo __('keywords_label'); ?>: ' + rule.keywords"></p>
                                            <p class="text-sm text-white font-medium line-clamp-1"
                                                x-text="rule.reply_message"></p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="previewRule(rule)"
                                            class="p-2.5 bg-indigo-600/10 hover:bg-indigo-600 rounded-xl text-indigo-400 hover:text-white transition-all border border-indigo-500/10"
                                            title="<?php echo __('message_preview'); ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button @click="editRule(rule)"
                                            class="p-2.5 bg-white/5 hover:bg-white/10 rounded-xl text-gray-400 hover:text-white transition-all border border-white/5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button @click="deleteRule(rule.id)"
                                            class="p-2.5 bg-red-500/10 hover:bg-red-500/20 rounded-xl text-red-500 transition-all border border-red-500/10">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="rules.length === 0">
                                <div class="p-12 border-2 border-white/5 border-dashed rounded-[2rem] text-center">
                                    <p class="text-gray-500 text-sm italic font-medium">
                                        <?php echo __('no_keyword_rules'); ?>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- Modal -->
    <div x-show="showModal" style="display: none;"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="bg-gray-900 border border-white/10 rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl relative"
            @click.away="closeModal()">
            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-10 -mt-10 pointer-events-none">
            </div>

            <div class="p-8 relative z-10">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-2xl font-bold text-white flex items-center gap-3">
                        <div class="p-2 bg-indigo-600/20 rounded-xl">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span
                            x-text="editMode ? '<?php echo __('edit_rule'); ?>' : '<?php echo __('add_new_rule'); ?>'"></span>
                    </h3>
                    <button @click="closeModal()" class="text-gray-500 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <div>
                        <label
                            class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('trigger_keyword'); ?></label>
                        <input type="text" x-model="modalKeywords"
                            placeholder="<?php echo __('keyword_placeholder'); ?>"
                            class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all text-sm">
                        <p class="text-[10px] text-gray-500 mt-2 italic"><?php echo __('keywords_hint'); ?></p>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('reply_message'); ?></label>
                        <textarea x-model="modalReply" rows="5" placeholder="<?php echo __('reply_placeholder'); ?>"
                            class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all resize-none text-sm leading-relaxed"></textarea>
                    </div>



                    <button @click="saveRule()"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl shadow-xl shadow-indigo-600/20 transition-all transform active:scale-95 text-lg">
                        <?php echo __('save_rule'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .no-scrollbar {
        -ms-overflow-style: none;
        /* IE and Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    .messenger-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .messenger-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.2);
        border-radius: 20px;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(99, 102, 241, 0.5);
    }

    .toggle-checkbox:checked {
        right: 0;
    }

    .toggle-checkbox {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<script>
    function autoReplyApp() {
        return {
            selectedPageId: '',
            rules: [],
            defaultReplyText: '',
            defaultHideComment: false,
            savingDefault: false,
            showModal: false,
            editMode: false,
            currentRuleId: null,
            modalKeywords: '',
            modalReply: '',
            modalHideComment: false,
            subscribing: false,
            stopping: false,
            debugInfo: null,
            webhookUrl: 'Loading...',
            verifyToken: 'Loading...',
            pages: <?php echo json_encode($pages); ?>,

            // Preview State
            previewMode: 'default', // 'default' or 'rule'
            previewCustomerMsg: '',
            previewReplyMsg: '',
            previewHideComment: false,

            getPageName() {
                const page = this.pages.find(p => p.page_id == this.selectedPageId);
                return page ? page.page_name : '';
            },

            init() {
                this.fetchWebhookInfo();
                const lastPage = localStorage.getItem('ar_last_page');
                if (lastPage) {
                    this.selectedPageId = lastPage;
                    this.fetchRules();
                    this.fetchTokenDebug();
                }

                this.$watch('defaultReplyText', () => {
                    this.previewMode = 'default';
                });
            },

            previewRule(rule) {
                this.previewMode = 'rule';
                this.previewCustomerMsg = rule.keywords.split(',')[0].trim();
                this.previewReplyMsg = rule.reply_message;
                this.previewHideComment = (rule.hide_comment == 1);
            },

            fetchTokenDebug() {
                if (!this.selectedPageId) return;
                fetch(`ajax_auto_reply.php?action=debug_token_info&page_id=${this.selectedPageId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.debugInfo = data;
                        }
                    });
            },

            subscribePage() {
                if (!this.selectedPageId) return;
                this.subscribing = true;
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                fetch('ajax_auto_reply.php?action=subscribe_page', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        this.subscribing = false;
                        alert(data.message || data.error);
                    }).catch(() => { this.subscribing = false; alert('Network Error'); });
            },

            stopAutoReply() {
                if (!this.selectedPageId) return;
                if (!confirm('<?php echo __('confirm_stop_auto_reply'); ?>')) return;
                this.stopping = true;
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                fetch('ajax_auto_reply.php?action=unsubscribe_page', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        this.stopping = false;
                        alert(data.message || data.error);
                    }).catch(() => { this.stopping = false; alert('Network Error'); });
            },

            fetchWebhookInfo() {
                fetch('ajax_auto_reply.php?action=get_webhook_info')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.webhookUrl = data.webhook_url;
                            this.verifyToken = data.verify_token;
                        }
                    });
            },

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => { alert('<?php echo __('copied'); ?>'); });
            },

            fetchRules() {
                if (!this.selectedPageId) return;
                localStorage.setItem('ar_last_page', this.selectedPageId);
                this.rules = [];
                this.defaultReplyText = '';
                fetch(`ajax_auto_reply.php?action=fetch_rules&page_id=${this.selectedPageId}&source=message`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.rules = data.rules.filter(r => r.trigger_type === 'keyword');
                            const defRule = data.rules.find(r => r.trigger_type === 'default');
                            if (defRule) {
                                this.defaultReplyText = defRule.reply_message;
                                this.defaultHideComment = (defRule.hide_comment == 1);
                            }
                        }
                    });
            },

            saveDefaultReply() {
                if (!this.selectedPageId) return;
                this.savingDefault = true;
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('type', 'default');
                formData.append('reply', this.defaultReplyText);
                formData.append('keywords', '*');
                formData.append('source', 'message');
                formData.append('hide_comment', '0'); // Hide comment not applicable for messages, but DB might require it
                fetch('ajax_auto_reply.php?action=save_rule', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        this.savingDefault = false;
                        if (!data.success) alert(data.error);
                    }).catch(() => { this.savingDefault = false; });
            },

            openAddModal() {
                if (!this.selectedPageId) { alert('Please select a page first'); return; }
                this.editMode = false;
                this.currentRuleId = null;
                this.modalKeywords = '';
                this.modalReply = '';
                this.modalHideComment = false;
                this.showModal = true;
            },

            editRule(rule) {
                this.editMode = true;
                this.currentRuleId = rule.id;
                this.modalKeywords = rule.keywords;
                this.modalReply = rule.reply_message;
                this.modalHideComment = false;
                this.showModal = true;
            },

            closeModal() { this.showModal = false; },

            saveRule() {
                if (!this.selectedPageId || !this.modalReply) return;
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('type', 'keyword');
                formData.append('keywords', this.modalKeywords);
                formData.append('reply', this.modalReply);
                formData.append('source', 'message');
                formData.append('hide_comment', '0');
                if (this.editMode) formData.append('rule_id', this.currentRuleId);
                fetch('ajax_auto_reply.php?action=save_rule', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) { this.closeModal(); this.fetchRules(); }
                        else alert(data.error);
                    });
            },

            deleteRule(id) {
                if (!confirm('<?php echo __('confirm_delete_rule'); ?>')) return;
                let formData = new FormData();
                formData.append('id', id);
                fetch('ajax_auto_reply.php?action=delete_rule', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => { if (data.success) this.fetchRules(); });
            }
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>