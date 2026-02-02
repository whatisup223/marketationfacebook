<?php
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get User Pages via Account Join - Robust query
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id IN (
    SELECT MIN(p.id) 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? 
    GROUP BY p.page_id
) ORDER BY page_name ASC");
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
                    <?php echo __('auto_reply_settings'); ?>
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
                        <select x-model="selectedPageId"
                            @change="localStorage.setItem('ar_last_page', selectedPageId); fetchRules(); fetchTokenDebug(); fetchPageSettings(); fetchStats(); fetchHandover();"
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

        <!-- NEW: Insight & Control Center Section -->
        <template x-if="selectedPageId">
            <div x-transition.opacity class="mt-12 space-y-12 pb-24 border-t border-white/5 pt-12 relative">
                <!-- Background Glow -->
                <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-96 bg-indigo-600/5 blur-[120px] pointer-events-none -z-10">
                </div>

                <!-- Section Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 relative z-30">
                    <div class="w-full md:w-auto">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-8 h-[2px] bg-indigo-500 rounded-full"></span>
                            <span
                                class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.3em]"><?php echo __('overview'); ?></span>
                        </div>
                        <h2 class="text-3xl font-black text-white leading-tight rtl:text-right">
                            <?php echo __('insight_control_center'); ?>
                        </h2>
                    </div>

                    <!-- Range Filter -->
                    <div
                        class="flex flex-wrap items-center gap-2 bg-white/5 p-1 rounded-2xl border border-white/10 backdrop-blur-md w-full md:w-auto">
                        <button @click="statsRange = 'today'; fetchStats()"
                            :class="statsRange === 'today' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-500 hover:text-white'"
                            class="flex-1 md:flex-none px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-300">
                            <?php echo __('today'); ?>
                        </button>
                        <button @click="statsRange = 'week'; fetchStats()"
                            :class="statsRange === 'week' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-500 hover:text-white'"
                            class="flex-1 md:flex-none px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-300">
                            <?php echo __('last_7_days'); ?>
                        </button>
                        <button @click="statsRange = 'month'; fetchStats()"
                            :class="statsRange === 'month' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-500 hover:text-white'"
                            class="flex-1 md:flex-none px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-300">
                            <?php echo __('last_30_days'); ?>
                        </button>
                        <button @click="statsRange = 'all'; fetchStats()"
                            :class="statsRange === 'all' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-500 hover:text-white'"
                            class="flex-1 md:flex-none px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-300">
                            <?php echo __('all_time'); ?>
                        </button>
                        <div class="w-px h-4 bg-white/10 mx-1 hidden md:block"></div>
                        <button @click="openCustomRangeModal()"
                            class="flex-1 md:flex-none px-4 py-2 rounded-xl text-[10px] font-black uppercase text-indigo-400 hover:bg-white/5 transition-all">
                            <?php echo __('custom_period') ?? 'Custom Range'; ?>
                        </button>
                    </div>

                    <!-- Additional Filters -->
                    <div
                        class="flex items-center gap-3 bg-white/5 p-1 rounded-2xl border border-white/10 backdrop-blur-md w-full md:w-auto">
                        <select x-model="statsRule" @change="fetchStats()"
                            class="bg-transparent text-gray-400 text-[10px] font-black uppercase px-4 py-2 border-none focus:ring-0 cursor-pointer hover:text-white transition-colors w-full md:w-48">
                            <option value="" class="bg-gray-900"><?php echo __('all_rules') ?? 'All Rules'; ?></option>
                            <option value="0" class="bg-gray-900"><?php echo __('default_reply'); ?></option>
                            <template x-for="rule in rules" :key="rule.id">
                                <option :value="rule.id" class="bg-gray-900" x-text="rule.keywords.split(',')[0]">
                                </option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Interacted -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('total_interacted'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-white group-hover:scale-110 transition-transform origin-left"
                                    x-text="stats.total_interacted"></h4>
                            </div>
                            <div
                                class="p-3 bg-indigo-500/10 rounded-2xl text-indigo-400 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('live_database_feed'); ?></span>
                        </div>
                    </div>

                    <!-- AI Success Rate -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('ai_success_rate'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-white group-hover:scale-110 transition-transform origin-left"
                                    x-text="stats.ai_success_rate"></h4>
                            </div>
                            <div
                                class="p-3 bg-green-500/10 rounded-2xl text-green-400 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <div class="flex-1 h-1 bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-green-500" :style="'width: ' + stats.ai_success_rate"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Comments Stat -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('hidden_comments'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-white group-hover:scale-110 transition-transform origin-left"
                                    x-text="stats.hidden_comments || 0"></h4>
                            </div>
                            <div
                                class="p-3 bg-red-500/10 rounded-2xl text-red-500 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268-2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('moderation_active'); ?></span>
                        </div>
                    </div>

                    <!-- Response Speed -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('avg_response_speed'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-white group-hover:scale-110 transition-transform origin-left"
                                    x-text="stats.avg_response_speed"></h4>
                            </div>
                            <div
                                class="p-3 bg-indigo-500/10 rounded-2xl text-indigo-400 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span
                                class="text-[10px] text-indigo-400 uppercase font-black tracking-tighter"><?php echo __('instant_replies'); ?></span>
                        </div>
                    </div>

                    <!-- Most Active Rule -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('most_active_rule'); ?>
                                </p>
                                <h4 class="text-xl font-black text-white truncate" x-text="stats.top_rule"></h4>
                            </div>
                            <div
                                class="p-3 bg-fuchsia-500/10 rounded-2xl text-fuchsia-400 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('top_performing_logic'); ?></span>
                        </div>
                    </div>

                    <!-- Peak Hour -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('peak_hour'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-white" x-text="stats.peak_hour"></h4>
                            </div>
                            <div
                                class="p-3 bg-amber-500/10 rounded-2xl text-amber-500 group-hover:rotate-12 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('highest_traffic_period'); ?></span>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('system_health'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-green-400" x-text="stats.system_health"></h4>
                            </div>
                            <div
                                class="p-3 bg-cyan-500/10 rounded-2xl text-cyan-400 group-hover:scale-110 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('api_status_operational'); ?></span>
                        </div>
                    </div>

                    <!-- Safety Alerts (The 4th card in 2nd row) -->
                    <div
                        class="glass-panel p-6 rounded-3xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-all group overflow-hidden relative">
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">
                                    <?php echo __('safety_alerts'); ?>
                                </p>
                                <h4 class="text-3xl font-black text-rose-400" x-text="stats.anger_alerts"></h4>
                            </div>
                            <div
                                class="p-3 bg-rose-500/10 rounded-2xl text-rose-400 group-hover:scale-110 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full"
                                :class="stats.anger_alerts > 0 ? 'bg-rose-500 animate-pulse' : 'bg-green-500'"></span>
                            <span
                                class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter"><?php echo __('ai_protection'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Log (Replaces Live Alerts) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-12">
                        <div
                            class="glass-panel p-8 rounded-[2.5rem] border border-white/5 bg-black/40 backdrop-blur-3xl relative overflow-hidden group">
                            <div class="flex items-center justify-between mb-8">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10">
                                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-white"><?php echo __('live_alerts_center'); ?>
                                        </h3>
                                        <p class="text-xs text-gray-500 font-bold tracking-wide">
                                            <?php echo __('monitor_alerts_desc'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="fetchRules(); fetchHandover(); fetchStats();"
                                        class="flex items-center gap-2 px-4 py-2 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white rounded-xl transition-all border border-indigo-500/20 text-[10px] font-black uppercase tracking-widest"
                                        :class="fetchingHandover ? 'animate-pulse' : ''">
                                        <svg class="w-4 h-4" :class="fetchingHandover ? 'animate-spin' : ''" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span><?php echo __('sync'); ?></span>
                                    </button>

                                    <button @click="markAllAsResolved()"
                                        class="flex items-center gap-2 px-4 py-2 bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-white rounded-xl transition-all border border-emerald-500/20 text-[10px] font-black uppercase tracking-widest">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span><?php echo __('mark_all_resolved'); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <template x-if="handoverConversations.length > 0">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <template x-for="conv in handoverConversations" :key="conv.id">
                                            <div
                                                class="bg-white/[0.03] border border-white/5 p-5 rounded-3xl hover:border-red-500/30 transition-all group/item relative overflow-hidden">
                                                <div
                                                    class="absolute top-0 right-0 p-4 opacity-10 group-hover/item:opacity-20 transition-opacity">
                                                    <svg class="w-12 h-12 text-red-500" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                                        </path>
                                                    </svg>
                                                </div>
                                                <div class="relative z-10">
                                                    <div class="flex items-center gap-3 mb-4">
                                                        <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-400 font-black border border-indigo-500/20"
                                                            x-text="conv.user_id.substring(0,2).toUpperCase()"></div>
                                                        <div class="min-w-0">
                                                            <p
                                                                class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-0.5">
                                                                <?php echo __('human_intervention_needed'); ?>
                                                            </p>
                                                            <p class="text-xs font-bold text-white truncate"
                                                                x-text="conv.user_name || 'ID: ' + conv.user_id"></p>
                                                        </div>
                                                    </div>
                                                    <div class="mb-4" x-show="conv.last_user_message">
                                                        <p
                                                            class="text-[10px] text-red-400 font-bold mb-1 uppercase tracking-tighter">
                                                            <?php echo __('last_message'); ?>:
                                                        </p>
                                                        <p class="text-xs text-gray-300 line-clamp-2 italic"
                                                            x-text="'&quot;' + conv.last_user_message + '&quot;'"></p>
                                                    </div>
                                                    <div class="mb-4">
                                                        <p
                                                            class="text-[9px] text-gray-500 uppercase tracking-tighter mb-1">
                                                            <?php echo __('last_interaction'); ?>:
                                                        </p>
                                                        <p class="text-[10px] text-gray-400 truncate italic"
                                                            x-text="conv.last_bot_reply_text || '<?php echo __('waiting_response'); ?>'">
                                                        </p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 mb-4">
                                                        <template x-if="conv.is_anger_detected == 1">
                                                            <span
                                                                class="px-2 py-1 bg-red-500/20 text-red-400 text-[8px] font-black rounded-lg border border-red-500/20 uppercase"><?php echo __('danger_alert'); ?></span>
                                                        </template>
                                                        <template x-if="conv.repeat_count >= 3">
                                                            <span
                                                                class="px-2 py-1 bg-orange-500/20 text-orange-400 text-[8px] font-black rounded-lg border border-orange-500/20 uppercase"><?php echo __('repetition_alert'); ?></span>
                                                        </template>
                                                    </div>
                                                    <button @click="resolveHandover(conv.id)"
                                                        class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-black rounded-xl transition-all shadow-lg shadow-indigo-600/20 uppercase tracking-widest flex items-center justify-center gap-2">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                        <?php echo __('mark_as_resolved'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="handoverConversations.length === 0">
                                    <div class="flex flex-col items-center justify-center py-12 text-center">
                                        <div
                                            class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mb-4 border border-green-500/20">
                                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <h4 class="text-white font-bold mb-1"><?php echo __('no_active_alerts'); ?></h4>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</div>
</div>
</template>

<!-- Main Body: Preview (Left) & Rules (Right) -->
<div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-8 pb-20">

    <!-- Left Side: Preview Card -->
    <div id="comment-preview-section" class="lg:col-span-4 order-last lg:order-1">
        <div class="sticky top-24 space-y-6">
            <div class="glass-card rounded-[32px] border border-white/10 shadow-2xl overflow-hidden bg-[#18191a]">
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
                    <!-- Page Post Header -->
                    <div class="flex items-center gap-3 mb-4 px-2">
                        <div
                            class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center border border-white/10 shadow-lg text-white font-bold text-lg overflow-hidden">
                            <template x-if="getPageName()">
                                <span x-text="getPageName().charAt(0).toUpperCase()"></span>
                            </template>
                            <template x-if="!getPageName()">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </template>
                        </div>
                        <div class="space-y-0.5">
                            <div class="font-bold text-gray-200 text-sm"
                                x-text="getPageName() || 'Marketation - ماركتيشن'">
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

                        <!-- User Comment -->
                        <div class="relative group/comment transition-all duration-300"
                            :class="(previewMode === 'rule' ? previewHideComment : defaultHideComment) ? 'opacity-50 grayscale' : ''">

                            <div class="flex gap-2">
                                <div
                                    class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 to-orange-500 flex-shrink-0 border border-black/20">
                                </div>
                                <div class="flex-1 max-w-[90%]">
                                    <div class="bg-[#3a3b3c] rounded-2xl px-3 py-2 inline-block text-[#e4e6eb]">
                                        <div class="font-bold text-xs mb-0.5 cursor-pointer hover:underline">
                                            <?php echo __('customer_name_sample'); ?>
                                        </div>
                                        <div class="text-[13px]"
                                            x-text="previewMode === 'rule' ? previewCustomerMsg : '<?php echo __('customer_msg_sample'); ?>'">
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-wrap gap-4 mt-1 ml-1 text-[11px] font-bold text-[#b0b3b8] items-center">
                                        <span class="cursor-pointer hover:underline"><?php echo __('fb_like'); ?></span>
                                        <span
                                            class="cursor-pointer hover:underline"><?php echo __('fb_reply'); ?></span>
                                        <span><?php echo __('fb_time_2m'); ?></span>

                                        <!-- Hidden Indicator -->
                                        <span x-show="previewMode === 'rule' ? previewHideComment : defaultHideComment"
                                            class="text-red-400/80 flex items-center gap-1 ml-2 transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268-2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                                </path>
                                            </svg>
                                            <?php echo __('hidden'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Page Reply -->
                        <div class="flex gap-2 ml-10">
                            <div
                                class="w-6 h-6 rounded-full bg-blue-600 flex-shrink-0 flex items-center justify-center border border-black/20 shadow-md overflow-hidden">
                                <template x-if="getPageName()">
                                    <span class="text-[9px] font-bold text-white"
                                        x-text="getPageName().charAt(0).toUpperCase()"></span>
                                </template>
                                <template x-if="!getPageName()">
                                    <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                    </svg>
                                </template>
                            </div>
                            <div class="flex-1 max-w-[90%]">
                                <div
                                    class="bg-[#3a3b3c] rounded-2xl px-3 py-2 inline-block text-[#e4e6eb] border border-blue-500/30 shadow-[0_0_15px_rgba(59,130,246,0.1)]">
                                    <div class="flex items-center gap-1 mb-0.5">
                                        <span class="font-bold text-xs cursor-pointer hover:underline"
                                            x-text="getPageName() || 'Marketation - ماركتيشن'"></span>
                                        <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                        </svg>
                                    </div>
                                    <div class="text-[13px] whitespace-pre-wrap leading-snug"
                                        x-text="previewMode === 'rule' ? previewReplyMsg : (defaultReplyText ? defaultReplyText : '<?php echo __('preview_empty_msg'); ?>')">
                                    </div>
                                </div>
                                <div class="flex gap-4 mt-1 ml-1 text-[11px] font-bold text-[#b0b3b8]">
                                    <span
                                        class="text-blue-400 cursor-pointer hover:underline"><?php echo __('fb_like'); ?></span>
                                    <span class="cursor-pointer hover:underline"><?php echo __('fb_reply'); ?></span>
                                    <span><?php echo __('just_now'); ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Rules Area -->
    <div class="lg:col-span-8 space-y-8 order-first lg:order-2">

        <template x-if="!selectedPageId">
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
                    <button @click="scrollToPreview()"
                        class="p-3 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white rounded-2xl transition-all border border-indigo-500/20 group/eye"
                        title="<?php echo __('message_preview'); ?>">
                        <svg class="w-6 h-6 group-hover/eye:scale-110 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg>
                    </button>
                </div>

                <textarea x-model="defaultReplyText" rows="4"
                    class="w-full bg-black/40 border border-white/10 rounded-2xl p-5 text-white placeholder-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all mb-6 text-base leading-relaxed"
                    placeholder="<?php echo __('reply_placeholder'); ?>"></textarea>

                <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-indigo-500/10 rounded-xl">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268-2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">
                                <?php echo __('hide_comment_after_reply'); ?>
                            </p>
                            <p class="text-[10px] text-gray-500"><?php echo __('hide_comment_help'); ?></p>
                        </div>
                    </div>
                    <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                        <input type="checkbox" x-model="defaultHideComment" id="toggleHideDefault"
                            class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-6 transition-all duration-300" />
                        <label for="toggleHideDefault" :class="defaultHideComment ? 'bg-indigo-600' : 'bg-gray-700'"
                            class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                    </div>
                </div>

                <!-- Private Reply Section (Default) -->
                <div class="mt-4 p-4 bg-white/5 rounded-2xl border border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-blue-500/10 rounded-xl">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-white"><?php echo __('send_private_reply'); ?></p>
                                <p class="text-[10px] text-gray-500"><?php echo __('send_private_reply_hint'); ?></p>
                            </div>
                        </div>
                        <div
                            class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" x-model="defaultPrivateReplyEnabled" id="togglePrivateReplyDefault"
                                class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-6 transition-all duration-300" />
                            <label for="togglePrivateReplyDefault"
                                :class="defaultPrivateReplyEnabled ? 'bg-indigo-600' : 'bg-gray-700'"
                                class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                        </div>
                    </div>
                    <div x-show="defaultPrivateReplyEnabled" x-transition>
                        <textarea x-model="defaultPrivateReplyText" rows="2"
                            class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white placeholder-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm"
                            placeholder="<?php echo __('private_reply_placeholder'); ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- Keyword Rules Section -->
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-2xl font-bold text-white"><?php echo __('keyword_rules'); ?></h3>
                    <button @click="openAddModal()"
                        class="bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white px-5 py-2.5 rounded-xl transition-all border border-indigo-500/20 flex items-center gap-2 font-bold text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
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
                                    <p class="text-sm text-white font-medium line-clamp-1" x-text="rule.reply_message">
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="previewRule(rule); scrollToPreview()"
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

            <!-- Bot Intelligence Settings (Hidden for Comments to avoid conflict) -->
            <!-- These settings (Cooldown, Schedule) are shared with Messenger Bot and are more relevant there. 
                         To avoid confusion, we hide them here. If needed, they can be enabled in Messenger Bot page. -->
            <div style="display: none;">
                <!-- Hidden Inputs to preserve values during save -->
                <input type="hidden" x-model="cooldownHours">
                <input type="hidden" x-model="cooldownMinutes">
                <input type="hidden" x-model="cooldownSeconds">
                <input type="hidden" x-model="schEnabled">
                <input type="hidden" x-model="schStart">
                <input type="hidden" x-model="schEnd">
            </div>

            <!-- AI Protection Card -->
            <div
                class="glass-panel p-8 rounded-[2rem] border border-white/10 bg-white/5 backdrop-blur-2xl hover:border-indigo-500/20 transition-all shadow-2xl relative overflow-hidden group">
                <!-- Decorative Background -->
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-16 -mt-16 pointer-events-none">
                </div>

                <div class="flex flex-col md:flex-row gap-8">
                    <!-- Left Side: Toggle & Info -->
                    <div class="flex-shrink-0 w-full md:w-64">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 bg-indigo-500/20 rounded-xl">
                                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white"><?php echo __('ai_protection'); ?></h3>
                        </div>

                        <div class="bg-black/20 p-5 rounded-2xl border border-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-gray-400 uppercase tracking-wider"><?php echo __('status'); ?></span>
                                <div
                                    class="relative inline-block w-11 align-middle select-none transition duration-200 ease-in">
                                    <input type="checkbox" x-model="aiSentimentEnabled" id="toggleAiSentimentCard"
                                        class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-5 transition-all duration-300" />
                                    <label for="toggleAiSentimentCard"
                                        :class="aiSentimentEnabled ? 'bg-indigo-600' : 'bg-gray-700'"
                                        class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed rtl:text-right">
                                <?php echo __('human_takeover_hint'); ?>
                            </p>
                        </div>

                        <!-- Compact Exclude Toggle -->
                        <div
                            class="mt-4 flex items-center justify-between gap-3 p-3 rounded-xl border border-white/5 bg-white/5 hover:border-indigo-500/30 transition-all group">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div
                                    class="w-1.5 h-1.5 rounded-full bg-indigo-500/50 group-hover:bg-indigo-400 group-hover:scale-125 transition-all flex-shrink-0">
                                </div>
                                <span
                                    class="text-[11px] font-bold text-gray-400 uppercase tracking-widest truncate group-hover:text-gray-200 transition-colors">
                                    <?php echo __('exclude_keyword_rules'); ?>
                                </span>
                            </div>

                            <div
                                class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in flex-shrink-0">
                                <input type="checkbox" x-model="botExcludeKeywords" id="toggleGlobalExclCard"
                                    class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-2 appearance-none cursor-pointer checked:right-0 right-5 transition-all duration-300" />
                                <label for="toggleGlobalExclCard"
                                    :class="botExcludeKeywords ? 'bg-indigo-600' : 'bg-gray-700'"
                                    class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer transition-colors"></label>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Keywords & Settings -->
                    <div class="flex-1" x-show="aiSentimentEnabled" x-transition.opacity>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                                    <label
                                        class="text-xs font-bold text-gray-300 uppercase tracking-widest"><?php echo __('bot_anger_keywords'); ?></label>
                                </div>
                                <span
                                    class="text-[10px] text-indigo-400 font-mono"><?php echo __('comma_separated'); ?></span>
                            </div>

                            <div class="relative group">
                                <textarea x-model="angerKeywords" rows="5"
                                    placeholder="<?php echo __('anger_keywords_placeholder'); ?>"
                                    class="w-full bg-black/40 border border-white/10 rounded-2xl p-5 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all resize-none font-medium leading-relaxed group-hover:border-white/20"></textarea>
                            </div>

                            <div
                                class="flex items-start gap-3 text-xs text-gray-500 bg-white/5 p-4 rounded-xl border border-white/5">
                                <svg class="w-4 h-4 text-indigo-400 flex-shrink-0 mt-0.5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?php echo __('bot_anger_keywords_help'); ?></span>
                            </div>

                            <!-- NEW: Handover Settings -->
                            <div class="mt-6 pt-6 border-t border-white/5 space-y-4">
                                <!-- Repetition Threshold -->
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-300 uppercase tracking-widest mb-2"><?php echo __('repetition_threshold'); ?></label>
                                    <div class="flex items-center gap-3">
                                        <input type="number" x-model="repetitionThreshold" min="1" max="10"
                                            class="w-20 bg-black/40 border border-white/10 rounded-xl p-3 text-white text-center font-bold focus:ring-2 focus:ring-indigo-500 transition-all">
                                        <p class="text-[10px] text-gray-500">
                                            <?php echo __('repetition_threshold_hint') ?? 'Times a user can repeat the same message before handover.'; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Handover Reply -->
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-300 uppercase tracking-widest mb-2"><?php echo __('handover_reply_msg'); ?></label>
                                    <textarea x-model="handoverReply" rows="2"
                                        placeholder="<?php echo __('handover_reply_placeholder') ?? 'Message to send before handing over...'; ?>"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 transition-all resize-none"></textarea>
                                    <p class="text-[10px] text-gray-500 mt-2">
                                        <?php echo __('handover_reply_help') ?? 'Optional: Bot will send this message once before silencing itself.'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Disabled State -->
                    <div class="flex-1 flex flex-col items-center justify-center p-8 border-2 border-white/5 border-dashed rounded-2xl bg-black/10 min-h-[200px]"
                        x-show="!aiSentimentEnabled" x-transition.opacity>
                        <div class="p-4 bg-gray-800/50 rounded-full mb-4 text-gray-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                </path>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 font-medium"><?php echo __('ai_system_disabled'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Global Save Bar (Moved here) -->
            <div x-show="selectedPageId" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform translate-y-4"
                x-transition:enter-end="opacity-100 transform translate-y-0" class="mt-12 mb-12 flex justify-center">
                <div
                    class="glass-panel p-3 rounded-[2rem] border border-white/10 bg-black/40 backdrop-blur-3xl flex flex-col md:flex-row items-center justify-between gap-6 w-full max-w-4xl shadow-2xl">
                    <div class="flex items-center gap-4 px-6 text-center md:text-left">
                        <div class="relative">
                            <div class="w-12 h-12 bg-indigo-600/20 rounded-2xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div
                                class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900 animate-pulse">
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white uppercase tracking-tight">
                                <?php echo __('wa_settings_ready') ?? 'Settings Ready to Save'; ?>
                            </p>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em]">
                                <?php echo __('unsaved_changes_hint'); ?>
                            </p>
                        </div>
                    </div>
                    <button @click="saveAllSettings()" :disabled="isGlobalSaving"
                        class="px-12 py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-black rounded-[1.5rem] shadow-xl shadow-indigo-600/30 transition-all transform active:scale-95 flex items-center gap-3 group">
                        <template x-if="!isGlobalSaving">
                            <svg class="w-5 h-5 group-hover:translate-y-[-2px] transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                                </path>
                            </svg>
                        </template>
                        <template x-if="isGlobalSaving">
                            <svg class="animate-spin w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </template>
                        <span class="uppercase tracking-widest text-xs"
                            x-text="isGlobalSaving ? '<?php echo __('saving'); ?>...' : '<?php echo __('save_changes'); ?>'"></span>
                    </button>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span
                        x-text="editMode ? '<?php echo __('edit_rule'); ?>' : '<?php echo __('add_new_rule'); ?>'"></span>
                </h3>
                <button @click="closeModal()" class="text-gray-500 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="space-y-6">
                <div>
                    <label
                        class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('trigger_keyword'); ?></label>
                    <input type="text" x-model="modalKeywords" placeholder="<?php echo __('keyword_placeholder'); ?>"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all text-sm">
                    <p class="text-[10px] text-gray-500 mt-2 italic"><?php echo __('keywords_hint'); ?></p>
                </div>
                <div>
                    <label
                        class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?php echo __('reply_message'); ?></label>
                    <textarea x-model="modalReply" rows="5" placeholder="<?php echo __('reply_placeholder'); ?>"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all resize-none text-sm leading-relaxed"></textarea>
                </div>

                <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-red-500/10 rounded-xl">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268-2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-white"><?php echo __('hide_comment'); ?></span>
                    </div>
                    <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                        <input type="checkbox" x-model="modalHideComment" id="toggleHideModal"
                            class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-6 transition-all duration-300" />
                        <label for="toggleHideModal" :class="modalHideComment ? 'bg-indigo-600' : 'bg-gray-700'"
                            class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                    </div>
                </div>

                <!-- Private Reply Section (Modal) -->
                <div class="p-4 bg-white/5 rounded-2xl border border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-blue-500/10 rounded-xl">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-white"><?php echo __('send_private_reply'); ?></p>
                                <p class="text-[10px] text-gray-500"><?php echo __('send_private_reply_hint'); ?></p>
                            </div>
                        </div>
                        <div
                            class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" x-model="modalPrivateReplyEnabled" id="togglePrivateReplyModal"
                                class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-6 transition-all duration-300" />
                            <label for="togglePrivateReplyModal"
                                :class="modalPrivateReplyEnabled ? 'bg-indigo-600' : 'bg-gray-700'"
                                class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                        </div>
                    </div>
                    <div x-show="modalPrivateReplyEnabled" x-transition>
                        <textarea x-model="modalPrivateReplyText" rows="2"
                            class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white placeholder-gray-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm"
                            placeholder="<?php echo __('private_reply_placeholder'); ?>"></textarea>
                    </div>
                </div>

                <!-- New Advanced Feature Flags -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- AI Safe Toggle -->
                    <div class="flex flex-col gap-3 p-4 bg-white/5 rounded-2xl border border-white/5">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-indigo-500/10 rounded-xl text-indigo-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                                    </path>
                                </svg>
                            </div>
                            <div
                                class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                                <input type="checkbox" x-model="modalAiSafe" id="toggleAiSafe"
                                    class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-5 transition-all duration-300" />
                                <label for="toggleAiSafe" :class="modalAiSafe ? 'bg-indigo-600' : 'bg-gray-700'"
                                    class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer transition-colors"></label>
                            </div>
                        </div>
                        <span
                            class="text-[10px] font-black text-white uppercase tracking-wider"><?php echo __('ai_safe_rule'); ?></span>
                    </div>

                    <!-- Bypass Schedule Toggle -->
                    <div class="flex flex-col gap-3 p-4 bg-white/5 rounded-2xl border border-white/5">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-orange-500/10 rounded-xl text-orange-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div
                                class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                                <input type="checkbox" x-model="modalBypassSchedule" id="toggleBypassSch"
                                    class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-5 transition-all duration-300" />
                                <label for="toggleBypassSch"
                                    :class="modalBypassSchedule ? 'bg-indigo-600' : 'bg-gray-700'"
                                    class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer transition-colors"></label>
                            </div>
                        </div>
                        <span
                            class="text-[10px] font-black text-white uppercase tracking-wider"><?php echo __('bypass_schedule_rule'); ?></span>
                    </div>

                    <!-- Bypass Cooldown Toggle -->
                    <div class="flex flex-col gap-3 p-4 bg-white/5 rounded-2xl border border-white/5">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-green-500/10 rounded-xl text-green-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                    </path>
                                </svg>
                            </div>
                            <div
                                class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                                <input type="checkbox" x-model="modalBypassCooldown" id="toggleBypassCool"
                                    class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-5 transition-all duration-300" />
                                <label for="toggleBypassCool"
                                    :class="modalBypassCooldown ? 'bg-indigo-600' : 'bg-gray-700'"
                                    class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer transition-colors"></label>
                            </div>
                        </div>
                        <span
                            class="text-[10px] font-black text-white uppercase tracking-wider"><?php echo __('bypass_cooldown_rule'); ?></span>
                    </div>
                </div>

                <button @click="saveRule()"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl shadow-xl shadow-indigo-600/20 transition-all transform active:scale-95 text-lg">
                    <?php echo __('save_rule'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Range Modal -->
<div x-show="showCustomRangeModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
    style="display: none;" x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95">
    <div class="fixed inset-0 bg-black/80 backdrop-blur-xl" @click="closeCustomRangeModal()"></div>

    <div
        class="glass-panel p-6 sm:p-10 rounded-[3rem] border border-white/10 bg-gray-900 w-full max-w-2xl relative z-10 shadow-[0_32px_120px_-15px_rgba(0,0,0,0.8)] max-h-[90vh] overflow-y-auto messenger-scrollbar">
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-indigo-500/10 rounded-2xl text-indigo-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002-2z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-3xl font-black text-white leading-none"><?php echo __('custom_period'); ?></h3>
            </div>
            <button @click="closeCustomRangeModal()"
                class="p-3 bg-white/5 hover:bg-white/10 rounded-2xl text-gray-500 hover:text-white transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <div class="space-y-12">
            <!-- Start Date & Time -->
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <span
                        class="text-sm font-black text-gray-400 uppercase tracking-widest"><?php echo __('start_date'); ?></span>
                </div>
                <div class="relative group">
                    <input type="datetime-local" x-model="customStartDate"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all text-sm">
                </div>
            </div>

            <!-- End Date & Time -->
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span
                        class="text-sm font-black text-gray-400 uppercase tracking-widest"><?php echo __('end_date'); ?></span>
                </div>
                <div class="relative group">
                    <input type="datetime-local" x-model="customEndDate"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all text-sm">
                </div>
            </div>

            <button @click="applyCustomRange()"
                class="w-full py-6 bg-indigo-600 hover:bg-indigo-500 text-white font-black rounded-[2rem] shadow-2xl shadow-indigo-600/40 transition-all transform active:scale-[0.98] uppercase tracking-[0.2em] text-sm flex items-center justify-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7">
                    </path>
                </svg>
                <?php echo __('apply_filter'); ?>
            </button>
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
            defaultPrivateReplyEnabled: false,
            defaultPrivateReplyText: '',
            savingDefault: false,
            showModal: false,
            editMode: false,
            currentRuleId: null,
            modalKeywords: '',
            modalReply: '',
            modalHideComment: false,
            modalPrivateReplyEnabled: false,
            modalPrivateReplyText: '',
            subscribing: false,
            stopping: false,
            modalAiSafe: true,
            modalBypassSchedule: false,
            modalBypassCooldown: false,
            debugInfo: null,
            webhookUrl: 'Loading...',
            verifyToken: 'Loading...',
            pages: <?php echo json_encode($pages); ?>,

            // Bot Intelligence Settings
            cooldownHours: 0,
            cooldownMinutes: 0,
            cooldownSeconds: 0,
            schEnabled: false,
            schStart: '00:00',
            schEnd: '23:59',
            botExcludeKeywords: false,
            aiSentimentEnabled: true,
            angerKeywords: '',
            repetitionThreshold: 3,
            handoverReply: '',
            handoverConversations: [],
            fetchingHandover: false,
            savingPageSettings: false,
            isGlobalSaving: false,
            statsRange: 'all',
            stats: {
                total_interacted: 0,
                active_handovers: 0,
                ai_success_rate: '0%',
                avg_response_speed: '0s',
                top_rule: '--',
                peak_hour: '--:--',
                system_health: '100%',
                anger_alerts: 0,
                ai_filtered: 0
            },
            showCustomRangeModal: false,
            customStartDate: '',
            customEndDate: '',
            statsRule: '',

            scrollToPreview() {
                const element = document.getElementById('comment-preview-section');
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            },

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
                    this.fetchTokenDebug();
                    this.fetchPageSettings();
                    this.fetchRules();
                    this.fetchHandover();
                    this.fetchStats();
                }

                this.$watch('defaultReplyText', () => {
                    this.previewMode = 'default';
                });

                setInterval(() => {
                    this.fetchHandover();
                    this.fetchStats();
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('preview')) {
                        setTimeout(() => this.scrollToPreview(), 1000);
                    }
                }, 30000);
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

            fetchPageSettings() {
                if (!this.selectedPageId) return;
                fetch(`ajax_auto_reply.php?action=fetch_page_settings&page_id=${this.selectedPageId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const s = data.settings;
                            const totalSec = parseInt(s.bot_cooldown_seconds || 0);
                            this.cooldownHours = Math.floor(totalSec / 3600);
                            this.cooldownMinutes = Math.floor((totalSec % 3600) / 60);
                            this.cooldownSeconds = totalSec % 60;
                            this.schEnabled = (s.bot_schedule_enabled == 1);
                            this.schStart = s.bot_schedule_start ? s.bot_schedule_start.substring(0, 5) : '00:00';
                            this.schEnd = s.bot_schedule_end ? s.bot_schedule_end.substring(0, 5) : '23:59';
                            this.botExcludeKeywords = (s.bot_exclude_keywords == 1);
                            this.aiSentimentEnabled = (s.bot_ai_sentiment_enabled == 1);
                            this.angerKeywords = s.bot_anger_keywords || '';
                            this.repetitionThreshold = parseInt(s.bot_repetition_threshold || 3);
                            this.handoverReply = s.bot_handover_reply || '';
                        }
                    });
            },

            savePageSettings() {
                // Internal use for global save
                const totalSec = (parseInt(this.cooldownHours) * 3600) + (parseInt(this.cooldownMinutes) * 60) + parseInt(this.cooldownSeconds);
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('cooldown_seconds', totalSec);
                formData.append('schedule_enabled', this.schEnabled ? '1' : '0');
                formData.append('schedule_start', this.schStart);
                formData.append('schedule_end', this.schEnd);
                formData.append('exclude_keywords', this.botExcludeKeywords ? '1' : '0');
                formData.append('ai_sentiment_enabled', this.aiSentimentEnabled ? '1' : '0');
                formData.append('anger_keywords', this.angerKeywords);
                formData.append('repetition_count', this.repetitionThreshold);
                formData.append('handover_reply', this.handoverReply);

                return fetch('ajax_auto_reply.php?action=save_page_settings', { method: 'POST', body: formData })
                    .then(res => res.json());
            },

            saveDefaultReply() {
                // Internal use for global save
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('type', 'default');
                formData.append('reply', this.defaultReplyText);
                formData.append('keywords', '*');
                formData.append('hide_comment', this.defaultHideComment ? '1' : '0');
                formData.append('private_reply_enabled', this.defaultPrivateReplyEnabled ? '1' : '0');
                formData.append('private_reply_text', this.defaultPrivateReplyText);
                return fetch('ajax_auto_reply.php?action=save_rule', { method: 'POST', body: formData })
                    .then(res => res.json());
            },

            saveAllSettings() {
                if (!this.selectedPageId) return;
                this.isGlobalSaving = true;

                Promise.all([this.savePageSettings(), this.saveDefaultReply()])
                    .then(([res1, res2]) => {
                        this.isGlobalSaving = false;
                        if (res1.success && res2.success) {
                            alert('<?php echo __('wa_settings_saved'); ?>'); // Reusing existing key
                        } else {
                            alert(res1.error || res2.error || 'Error saving settings');
                        }
                    })
                    .catch(() => {
                        this.isGlobalSaving = false;
                        alert('Network Error');
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
                this.fetchPageSettings();
                fetch(`ajax_auto_reply.php?action=fetch_rules&page_id=${this.selectedPageId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.rules = data.rules.filter(r => r.trigger_type === 'keyword');
                            const defRule = data.rules.find(r => r.trigger_type === 'default');
                            if (defRule) {
                                this.defaultReplyText = defRule.reply_message;
                                this.defaultHideComment = (defRule.hide_comment == 1);
                                this.defaultPrivateReplyEnabled = (defRule.private_reply_enabled == 1);
                                this.defaultPrivateReplyText = defRule.private_reply_text || '';
                            }
                        }
                    });
                this.fetchHandover();
            },

            fetchStats(customStart = '', customEnd = '') {
                if (!this.selectedPageId) return;
                let url = `ajax_auto_reply.php?action=fetch_page_stats&page_id=${this.selectedPageId}&range=${this.statsRange}&source=comment`;
                if (this.statsRange === 'custom') {
                    url += `&start=${customStart}&end=${customEnd}`;
                }
                if (this.statsRule !== '') {
                    url += `&rule_id=${this.statsRule}`;
                }
                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.stats = data.stats;
                        }
                    });
            },

            openCustomRangeModal() {
                const now = new Date();
                const pad = (n) => n.toString().padStart(2, '0');
                const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                this.customStartDate = formatDate(new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0));
                this.customEndDate = formatDate(new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59));
                this.showCustomRangeModal = true;
            },

            closeCustomRangeModal() {
                this.showCustomRangeModal = false;
            },

            applyCustomRange() {
                if (!this.customStartDate || !this.customEndDate) return;
                const startStr = this.customStartDate.replace('T', ' ') + ':00';
                const endStr = this.customEndDate.replace('T', ' ') + ':59';

                this.statsRange = 'custom';
                this.fetchStats(startStr, endStr);
                this.closeCustomRangeModal();
            },

            fetchHandover() {
                if (!this.selectedPageId) return;
                this.fetchingHandover = true;
                fetch(`ajax_auto_reply.php?action=fetch_handover_conversations&page_id=${this.selectedPageId}&source=comment&t=${new Date().getTime()}`)
                    .then(res => res.json())
                    .then(data => {
                        this.fetchingHandover = false;
                        if (data.success) {
                            this.handoverConversations = data.conversations;
                        }
                    }).catch(() => { this.fetchingHandover = false; });
            },

            resolveHandover(id) {
                let formData = new FormData();
                formData.append('id', id);
                fetch('ajax_auto_reply.php?action=mark_as_resolved', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) this.fetchHandover();
                    });
            },

            markAllAsResolved() {
                if (!this.selectedPageId || this.handoverConversations.length === 0) return;
                if (!confirm('<?php echo __('confirm_mark_all_resolved'); ?>')) return;
                let formData = new FormData();
                formData.append('page_id', this.selectedPageId);
                formData.append('source', 'comment');
                fetch('ajax_auto_reply.php?action=mark_all_as_resolved', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.fetchHandover();
                            this.fetchStats();
                        }
                    });
            },

            openAddModal() {
                if (!this.selectedPageId) { alert('Please select a page first'); return; }
                this.editMode = false;
                this.currentRuleId = null;
                this.modalKeywords = '';
                this.modalReply = '';
                this.modalHideComment = false;
                this.modalPrivateReplyEnabled = false;
                this.modalPrivateReplyText = '';
                this.modalAiSafe = true;
                this.modalBypassSchedule = false;
                this.modalBypassCooldown = false;
                this.showModal = true;
            },

            editRule(rule) {
                this.editMode = true;
                this.currentRuleId = rule.id;
                this.modalKeywords = rule.keywords;
                this.modalReply = rule.reply_message;
                this.modalReply = rule.reply_message;
                this.modalHideComment = (rule.hide_comment == 1);
                this.modalPrivateReplyEnabled = (rule.private_reply_enabled == 1);
                this.modalPrivateReplyText = rule.private_reply_text || '';
                this.modalAiSafe = (rule.is_ai_safe == 1);
                this.modalBypassSchedule = (rule.bypass_schedule == 1);
                this.modalBypassCooldown = (rule.bypass_cooldown == 1);
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
                formData.append('hide_comment', this.modalHideComment ? '1' : '0');
                formData.append('private_reply_enabled', this.modalPrivateReplyEnabled ? '1' : '0');
                formData.append('private_reply_text', this.modalPrivateReplyText);
                formData.append('is_ai_safe', this.modalAiSafe ? '1' : '0');
                formData.append('bypass_schedule', this.modalBypassSchedule ? '1' : '0');
                formData.append('bypass_cooldown', this.modalBypassCooldown ? '1' : '0');
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