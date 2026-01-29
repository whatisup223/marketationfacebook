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
                      ORDER BY p.page_name ASC");
$stmt->execute([$_SESSION['user_id']]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex h-screen bg-gray-900 font-sans overflow-hidden" x-data="autoReplyApp()">
    <?php include '../includes/user_sidebar.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900/50 backdrop-blur-md h-full relative p-6">

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400">
                    <?php echo __('auto_reply_settings'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('auto_reply_desc'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Column: Webhook & Page Selector -->
            <div class="space-y-6">
                <!-- Page Selector -->
                <div
                    class="glass-panel p-6 rounded-2xl border border-white/5 bg-white/5 backdrop-blur-xl hover:border-indigo-500/20 transition-all">
                    <label class="block text-sm font-bold text-white mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                        <?php echo __('select_page'); ?>
                    </label>
                    <div class="relative group">
                        <select x-model="selectedPageId" @change="fetchRules(); fetchTokenDebug();"
                            class="w-full bg-gray-900/50 border border-gray-700 text-white text-sm rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 block p-3 pr-10 appearance-none transition-all group-hover:border-gray-600">
                            <option value=""><?php echo __('select_page'); ?>...</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo htmlspecialchars($page['page_id']); ?>">
                                    <?php echo htmlspecialchars($page['page_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 rtl:right-auto rtl:left-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Activate Webhook Button -->
                    <template x-if="selectedPageId">
                        <div>
                            <button @click="subscribePage()"
                                class="w-full mt-4 flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 font-bold text-sm"
                                :class="subscribing ? 'opacity-50 cursor-not-allowed' : ''" :disabled="subscribing">
                                <svg x-show="!subscribing" class="w-4 h-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <svg x-show="subscribing" class="animate-spin h-4 w-4 text-white"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span
                                    x-text="subscribing ? '<?php echo __('processing_activity'); ?>' : '<?php echo __('activate_auto_reply'); ?>'"></span>
                            </button>

                            <!-- Token Debug Info -->
                            <div x-show="debugInfo"
                                class="mt-4 p-3 bg-black/40 rounded-lg border border-white/10 text-[10px] font-mono text-gray-400">
                                <div class="flex justify-between mb-1">
                                    <span>Token (Masked):</span>
                                    <span class="text-indigo-400" x-text="debugInfo.masked_token"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Token Length:</span>
                                    <span class="text-indigo-400" x-text="debugInfo.length"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Webhook Settings -->
                <div
                    class="glass-panel p-6 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 backdrop-blur-xl relative overflow-hidden">
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-16 -mt-16 pointer-events-none">
                    </div>

                    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2 relative z-10">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <?php echo __('webhook_configuration'); ?>
                    </h3>
                    <p class="text-xs text-gray-400 mb-6 relative z-10 leading-relaxed">
                        <?php echo __('webhook_help'); ?>
                    </p>

                    <div class="space-y-5 relative z-10">
                        <!-- Callback URL -->
                        <div>
                            <label
                                class="block text-[10px] font-bold text-indigo-300/70 uppercase tracking-widest mb-2"><?php echo __('callback_url'); ?></label>
                            <div class="flex gap-2">
                                <input type="text" readonly :value="webhookUrl"
                                    class="flex-1 bg-black/30 border border-white/10 rounded-lg text-xs text-gray-300 p-2.5 font-mono truncate focus:outline-none focus:border-indigo-500/50 transition-colors">
                                <button @click="regenerateWebhookId()"
                                    class="p-2.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-gray-600 rounded-lg text-gray-400 hover:text-white transition-all shadow-lg"
                                    title="<?php echo __('regenerate'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                </button>
                                <button @click="copyToClipboard(webhookUrl)"
                                    class="p-2.5 bg-indigo-600 hover:bg-indigo-500 border border-transparent rounded-lg text-white transition-all shadow-lg shadow-indigo-500/20"
                                    title="<?php echo __('copy'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Verify Token -->
                        <div>
                            <label
                                class="block text-[10px] font-bold text-indigo-300/70 uppercase tracking-widest mb-2"><?php echo __('verify_token'); ?></label>
                            <div class="flex gap-2">
                                <input type="text" readonly :value="verifyToken"
                                    class="flex-1 bg-black/30 border border-white/10 rounded-lg text-xs text-gray-300 p-2.5 font-mono truncate focus:outline-none focus:border-indigo-500/50 transition-colors">
                                <button @click="regenerateVerifyToken()"
                                    class="p-2.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-gray-600 rounded-lg text-gray-400 hover:text-white transition-all shadow-lg"
                                    title="<?php echo __('regenerate'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                </button>
                                <button @click="copyToClipboard(verifyToken)"
                                    class="p-2.5 bg-indigo-600 hover:bg-indigo-500 border border-transparent rounded-lg text-white transition-all shadow-lg shadow-indigo-500/20"
                                    title="<?php echo __('copy'); ?>">
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

            <!-- Right Column: Rules Editor -->
            <div class="lg:col-span-2">
                <!-- Main Content Area (Hidden if no page selected) -->
                <div x-show="selectedPageId" x-transition.opacity class="space-y-6" style="display: none;">

                    <!-- Default Reply Section -->
                    <div
                        class="glass-panel p-6 rounded-2xl border border-white/5 bg-gray-800/50 backdrop-blur-xl relative overflow-hidden group hover:border-indigo-500/20 transition-all">
                        <div class="relative z-10">
                            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                                <span class="p-2 bg-indigo-500/20 rounded-lg text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                </span>
                                <?php echo __('default_reply'); ?>
                            </h3>
                            <p class="text-gray-400 text-sm mb-4"><?php echo __('default_reply_hint'); ?></p>

                            <div class="gap-4 items-start">
                                <textarea x-model="defaultReplyText" rows="3"
                                    class="w-full bg-gray-900/50 border border-gray-700 rounded-xl p-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all mb-4"
                                    placeholder="<?php echo __('reply_placeholder'); ?>"></textarea>
                                <div class="flex justify-end">
                                    <button @click="saveDefaultReply()"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-xl transition-all shadow-lg shadow-indigo-500/30 flex items-center gap-2 text-sm hover:-translate-y-0.5 transform">
                                        <span x-show="!savingDefault"><?php echo __('save'); ?></span>
                                        <svg x-show="savingDefault" class="animate-spin h-4 w-4 text-white"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keyword Rules Section -->
                    <div class="glass-panel p-6 rounded-2xl border border-white/5 bg-white/5 backdrop-blur-xl">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-white"><?php echo __('keyword_rules'); ?></h3>
                            <button @click="openAddModal()"
                                class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all hover:-translate-y-0.5 flex items-center gap-2 shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                <?php echo __('add_new_rule'); ?>
                            </button>
                        </div>

                        <!-- Rules List -->
                        <div class="space-y-3">
                            <template x-if="rules.length === 0">
                                <div
                                    class="text-center py-12 border-2 border-dashed border-gray-800 rounded-xl bg-white/5">
                                    <p class="text-gray-500"><?php echo __('no_rules_found'); ?></p>
                                </div>
                            </template>
                            <template x-for="(rule, index) in rules" :key="rule.id">
                                <div
                                    class="bg-gray-900/40 border border-gray-800 rounded-xl p-4 flex justify-between items-start group hover:border-indigo-500/30 transition-all hover:bg-white/5">
                                    <div class="flex-1">
                                        <div>
                                            <div class="mb-3">
                                                <span
                                                    class="text-[10px] text-gray-500 uppercase tracking-wider font-bold"><?php echo __('if_comment_contains'); ?></span>
                                                <div class="mt-1.5 flex flex-wrap gap-2 items-center">
                                                    <template x-for="kw in rule.keywords.split(',')">
                                                        <span
                                                            class="bg-gray-800 text-indigo-300 text-xs px-2.5 py-1 rounded-lg border border-gray-700 font-medium"
                                                            x-text="kw.trim()"></span>
                                                    </template>

                                                    <!-- Hide Badge -->
                                                    <div x-show="rule.hide_comment == 1"
                                                        class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] font-bold"
                                                        title="<?php echo __('hide_comment'); ?>">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                                            </path>
                                                        </svg>
                                                        <span><?php echo __('auto_hide_status'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <span
                                                    class="text-[10px] text-gray-500 uppercase tracking-wider font-bold"><?php echo __('reply_with'); ?></span>
                                                <p class="text-white text-sm mt-1.5 whitespace-pre-wrap leading-relaxed"
                                                    x-text="rule.reply_message"></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="editRule(rule)"
                                            class="p-2 text-gray-400 hover:text-white transition-colors bg-gray-800 hover:bg-indigo-600 rounded-lg shadow-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteRule(rule.id)"
                                            class="p-2 text-gray-400 hover:text-white transition-colors bg-gray-800 hover:bg-red-600 rounded-lg shadow-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Placeholder when no page selected -->
                <div x-show="!selectedPageId"
                    class="glass-panel p-12 rounded-2xl border border-white/5 border-dashed flex flex-col items-center justify-center text-center h-full min-h-[400px]">
                    <div
                        class="w-20 h-20 bg-gray-800/30 rounded-full flex items-center justify-center mb-6 text-gray-600">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-400 mb-2"><?php echo __('select_page'); ?></h3>
                    <p class="text-gray-500 text-sm max-w-sm leading-relaxed"><?php echo __('auto_reply_desc'); ?></p>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal -->
    <div x-show="showModal" style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl relative"
            @click.away="closeModal()">
            <!-- Modal Pattern -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-10 -mt-10 pointer-events-none">
            </div>

            <div class="p-6 relative z-10">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <span class="p-1.5 bg-indigo-500/10 rounded-lg text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </span>
                    <span
                        x-text="editMode ? '<?php echo __('edit_rule'); ?>' : '<?php echo __('add_new_rule'); ?>'"></span>
                </h3>

                <div class="space-y-5">
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2"><?php echo __('trigger_keyword'); ?></label>
                        <input type="text" x-model="modalKeywords"
                            placeholder="<?php echo __('keyword_placeholder'); ?>"
                            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                        <p class="text-[10px] text-gray-500 mt-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Comma separated keywords (e.g. price, details)
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2"><?php echo __('reply_message'); ?></label>
                        <textarea x-model="modalReply" rows="4" placeholder="<?php echo __('reply_placeholder'); ?>"
                            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all resize-none"></textarea>
                    </div>

                    <!-- Hide Comment Checkbox -->
                    <div class="flex items-center gap-3 bg-black/20 p-3 rounded-xl border border-white/5">
                        <div
                            class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" x-model="modalHideComment" id="toggleHide"
                                class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" />
                            <label for="toggleHide"
                                :class="modalHideComment ? 'bg-indigo-600 border-indigo-600' : 'bg-gray-700 border-gray-700'"
                                class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer transition-colors border-2"></label>
                        </div>
                        <div>
                            <label for="toggleHide"
                                class="text-sm font-bold text-gray-300 cursor-pointer select-none"><?php echo __('hide_comment'); ?></label>
                            <p class="text-[10px] text-gray-500"><?php echo __('hide_comment_desc'); ?>
                            </p>
                        </div>
                    </div>

                    <style>
                        .toggle-checkbox:checked {
                            right: 0;
                            border-color: #6875F5;
                        }

                        .toggle-checkbox:checked+.toggle-label {
                            background-color: #6875F5;
                            border-color: #6875F5;
                        }

                        .toggle-checkbox {
                            right: 50%;
                            transition: all 0.3s;
                        }
                    </style>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button @click="closeModal()"
                        class="px-5 py-2.5 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700 transition-colors font-medium text-sm"><?php echo __('cancel'); ?></button>
                    <button @click="saveRule()"
                        class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-600/20 font-bold text-sm transform active:scale-95">
                        <?php echo __('save'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function autoReplyApp() {
        return {
            selectedPageId: '',
            rules: [],
            defaultReplyText: '',
            savingDefault: false,
            showModal: false,
            editMode: false,
            currentRuleId: null,
            modalKeywords: '',
            modalReply: '',
            modalHideComment: false,
            subscribing: false,
            debugInfo: null,

            // Webhook info
            webhookUrl: 'Loading...',
            verifyToken: 'Loading...',

            init() {
                // Check if page stored in localstorage
                const stored = localStorage.getItem('ar_last_page');
                if (stored) this.selectedPageId = stored;
                if (this.selectedPageId) {
                    this.fetchRules();
                    this.fetchTokenDebug();
                }

                this.fetchWebhookInfo();
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

                fetch('ajax_auto_reply.php?action=subscribe_page', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        this.subscribing = false;
                        if (data.success) {
                            alert(data.message);
                        } else {
                            alert(data.error);
                        }
                    })
                    .catch(err => {
                        this.subscribing = false;
                        alert('Network Error');
                    });
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

            regenerateWebhookId() {
                if (!confirm('This will change your Callback URL. You must update it in Facebook. Continue?')) return;

                fetch('ajax_auto_reply.php?action=regenerate_webhook', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.webhookUrl = data.webhook_url;
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
            },

            regenerateVerifyToken() {
                if (!confirm('This will change your Verify Token. You must update it in Facebook. Continue?')) return;

                fetch('ajax_auto_reply.php?action=regenerate_verify', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.verifyToken = data.verify_token;
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
            },

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('<?php echo __('copied'); ?>');
                });
            },

            fetchRules() {
                if (!this.selectedPageId) return;
                localStorage.setItem('ar_last_page', this.selectedPageId);

                this.rules = [];
                this.defaultReplyText = '';

                fetch(`ajax_auto_reply.php?action=fetch_rules&page_id=${this.selectedPageId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Split rules into normal and default
                            this.rules = data.rules.filter(r => r.trigger_type === 'keyword');
                            const defRule = data.rules.find(r => r.trigger_type === 'default');
                            if (defRule) {
                                this.defaultReplyText = defRule.reply_message;
                            }
                        }
                    });
            },

            saveDefaultReply() {
                if (!this.selectedPageId) { alert('Please select a page first'); return; }
                if (!this.defaultReplyText.trim()) { alert('Please enter a reply message'); return; }

                this.savingDefault = true;

                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('type', 'default');
                formData.append('reply', this.defaultReplyText);
                formData.append('keywords', '*');

                fetch('ajax_auto_reply.php?action=save_rule', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        this.savingDefault = false;
                        if (data.success) {
                            // Flash success or no-op
                        } else {
                            alert(data.error);
                        }
                    })
                    .catch(err => {
                        this.savingDefault = false;
                        alert('Network Error');
                    });
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
                this.modalHideComment = (rule.hide_comment == 1);
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
            },

            saveRule() {
                if (!this.selectedPageId || !this.modalReply) return;

                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('type', 'keyword');
                formData.append('keywords', this.modalKeywords);
                formData.append('reply', this.modalReply);
                formData.append('hide_comment', this.modalHideComment ? '1' : '0');
                if (this.editMode) formData.append('rule_id', this.currentRuleId);

                fetch('ajax_auto_reply.php?action=save_rule', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.closeModal();
                            this.fetchRules();
                        } else {
                            alert(data.error);
                        }
                    });
            },

            deleteRule(id) {
                if (!confirm('<?php echo __('confirm_delete_rule'); ?>')) return;

                let formData = new FormData();
                formData.append('id', id);

                fetch('ajax_auto_reply.php?action=delete_rule', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.fetchRules();
                        }
                    });
            }
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>