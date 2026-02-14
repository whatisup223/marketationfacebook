<?php
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../includes/header.php';

// Get User Instagram Accounts via Account Join
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id IN (
    SELECT MIN(p.id) 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? AND p.ig_business_id IS NOT NULL
    GROUP BY p.ig_business_id
) ORDER BY ig_username ASC");
$stmt->execute([$_SESSION['user_id']]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$preselected_id = $_GET['ig_id'] ?? '';
?>

<div id="main-user-container" class="main-user-container flex min-h-screen bg-gray-900 font-sans"
    style="font-family: <?php echo $font; ?>;" x-data="autoReplyApp()">
    <?php include '../includes/user_sidebar.php'; ?>

    <main class="flex-1 flex flex-col bg-gray-900/50 backdrop-blur-md relative p-6 overflow-x-hidden">

        <!-- Header -->
        <div class="flex-none flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1
                    class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-pink-500 via-purple-500 to-orange-500">
                    <?php echo __('ig_auto_reply'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('ig_auto_reply_desc'); ?></p>
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

        <!-- Selection & Integration Notice -->
        <div class="flex-none grid grid-cols-1 gap-8 mb-8">
            <div
                class="glass-panel p-6 rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl hover:border-pink-500/20 transition-all shadow-xl">
                <label class="block text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <div class="p-2 bg-pink-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg>
                    </div>
                    <?php echo __('select_ig_account'); ?>
                </label>

                <div class="flex flex-col sm:flex-row gap-4 items-stretch">
                    <div class="relative group flex-1">
                        <select x-model="selectedPageId"
                            @change="localStorage.setItem('ar_last_ig_page', selectedPageId); fetchRules(); fetchTokenDebug(); fetchPageSettings();"
                            class="w-full bg-black/40 border border-white/10 text-white text-sm rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-pink-500 block p-3.5 pr-10 appearance-none transition-all group-hover:border-white/20">
                            <option value=""><?php echo __('select_ig_account'); ?>...</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo htmlspecialchars($page['ig_business_id']); ?>">
                                    @<?php echo htmlspecialchars($page['ig_username']); ?>
                                    (<?php echo htmlspecialchars($page['page_name']); ?>)
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
                </div>
            </div>
        </div>

        <div class="flex-none p-12 text-center" x-show="!selectedPageId">
            <div class="w-20 h-20 bg-pink-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('select_ig_account_to_start'); ?></h3>
            <p class="text-gray-500 max-w-sm mx-auto">
                <?php echo __('ig_auto_reply_desc') ?: 'بمجرد اختيار الحساب، يمكنك إضافة قواعد الرد الآلي والتحكم في ذكاء البوت.'; ?>
            </p>
        </div>

        <div x-show="selectedPageId" class="space-y-8" x-cloak>
            <!-- Tabs Navigation -->
            <div class="flex gap-2">
                <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'bg-pink-600/20 text-pink-400 border-pink-500/30' : 'bg-white/5 text-gray-400'"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all"><?php echo __('control_results'); ?></button>
                <button @click="activeTab = 'replies'"
                    :class="activeTab === 'replies' ? 'bg-pink-600/20 text-pink-400 border-pink-500/30' : 'bg-white/5 text-gray-400'"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all"><?php echo __('reply_rules'); ?></button>
                <button @click="activeTab = 'ai'"
                    :class="activeTab === 'ai' ? 'bg-pink-600/20 text-pink-400 border-pink-500/30' : 'bg-white/5 text-gray-400'"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all"><?php echo __('bot_intelligence'); ?></button>
            </div>

            <!-- Dashboard Content -->
            <div x-show="activeTab === 'dashboard'" class="space-y-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="glass-panel p-6 rounded-3xl bg-white/5 border border-white/10">
                        <p class="text-xs text-gray-400 uppercase font-black tracking-widest mb-1">
                            <?php echo __('total_replies'); ?></p>
                        <h4 class="text-3xl font-black text-white" x-text="stats.total_replies">0</h4>
                    </div>
                </div>
            </div>

            <!-- Replies Content -->
            <div x-show="activeTab === 'replies'" class="space-y-6">
                <!-- Add Rule Button -->
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white"><?php echo __('manage_auto_replies'); ?></h3>
                    <button @click="openAddModal()"
                        class="bg-pink-600 hover:bg-pink-500 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-pink-600/20 transition-all">+
                        <?php echo __('add_new_rule'); ?></button>
                </div>

                <!-- Rules List -->
                <div class="grid grid-cols-1 gap-4">
                    <template x-for="rule in rules" :key="rule.id">
                        <div
                            class="glass-panel p-6 rounded-3xl bg-white/5 border border-white/10 hover:border-pink-500/30 transition-all flex justify-between items-center">
                            <div>
                                <span
                                    class="text-[10px] bg-pink-500/10 text-pink-400 px-2 py-1 rounded-lg font-black uppercase tracking-tighter mb-2 inline-block"
                                    x-text="rule.trigger_type"></span>
                                <h4 class="text-lg font-bold text-white mb-1" x-text="rule.keywords"></h4>
                                <p class="text-sm text-gray-400" x-text="rule.reply_message"></p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="editRule(rule)"
                                    class="p-2 bg-indigo-500/10 text-indigo-400 rounded-lg"><svg class="w-5 h-5"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                        </path>
                                    </svg></button>
                                <button @click="deleteRule(rule.id)"
                                    class="p-2 bg-red-500/10 text-red-400 rounded-lg"><svg class="w-5 h-5" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg></button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- AI Content -->
            <div x-show="activeTab === 'ai'" class="space-y-6">
                <!-- Bot Intelligence Settings -->
                <div class="glass-panel p-8 rounded-3xl bg-white/5 border border-white/10 space-y-8">
                    <div class="flex items-center gap-4 border-b border-white/5 pb-6">
                        <div class="p-4 bg-orange-500/10 rounded-2xl text-orange-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-white"><?php echo __('smart_bot'); ?></h3>
                            <p class="text-gray-400"><?php echo __('bot_behavior_desc'); ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Cooldown -->
                        <div>
                            <label
                                class="block text-sm font-bold text-gray-400 mb-4"><?php echo __('cooldown_time'); ?></label>
                            <input type="number" x-model="cooldown"
                                class="w-full bg-black/40 border border-white/10 rounded-2xl p-4 text-white">
                        </div>
                        <!-- Exclusion -->
                        <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5">
                            <div>
                                <h4 class="text-white font-bold"><?php echo __('ignore_customer_keywords'); ?></h4>
                                <p class="text-xs text-gray-500"><?php echo __('ignore_keywords_desc'); ?></p>
                            </div>
                            <input type="checkbox" x-model="excludeKeywords"
                                class="w-6 h-6 rounded border-white/10 text-pink-600 focus:ring-pink-500 bg-black/40">
                        </div>
                    </div>

                    <button @click="savePageSettings()"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-2xl transition-all"><?php echo __('save_bot_settings'); ?></button>
                </div>
            </div>
        </div>

    </main>

    <!-- Add/Edit Rule Modal -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        x-cloak>
        <div class="glass-panel w-full max-w-lg bg-gray-900 rounded-[2.5rem] border border-white/10 p-8 shadow-2xl">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-2xl font-black text-white"
                    x-text="editMode ? '<?php echo __('edit_rule'); ?>' : '<?php echo __('add_new_rule'); ?>'"></h3>
                <button @click="showModal = false" class="text-gray-500 hover:text-white"><svg class="w-6 h-6"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg></button>
            </div>

            <div class="space-y-6">
                <div>
                    <label
                        class="block text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3"><?php echo __('keywords'); ?></label>
                    <input type="text" x-model="modalKeywords" placeholder="<?php echo __('keywords_placeholder'); ?>"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-pink-500 outline-none transition-all">
                </div>
                <div>
                    <label
                        class="block text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3"><?php echo __('reply_message'); ?></label>
                    <textarea x-model="modalReply" rows="4"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-pink-500 outline-none transition-all"></textarea>
                </div>

                <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/5">
                    <span class="text-sm font-bold text-white"><?php echo __('hide_comment_after_reply'); ?></span>
                    <input type="checkbox" x-model="modalHideComment"
                        class="w-6 h-6 rounded border-white/10 text-pink-600 focus:ring-pink-500">
                </div>

                <button @click="saveRule()"
                    class="w-full bg-pink-600 hover:bg-pink-500 text-white font-bold py-4 rounded-full shadow-lg shadow-pink-600/20 transition-all"><?php echo __('save_rule'); ?></button>
            </div>
        </div>
    </div>

    <script>
        function autoReplyApp() {
            return {
                activeTab: 'dashboard',
                selectedPageId: '<?php echo $preselected_id; ?>',
                platform: 'instagram',
                debugInfo: null,
                rules: [],
                stats: { total_replies: 0 },
                pages: <?php echo json_encode($pages); ?>,
                showModal: false,
                editMode: false,
                currentRuleId: null,
                modalKeywords: '',
                modalReply: '',
                modalHideComment: false,
                cooldown: 0,
                excludeKeywords: false,

                init() {
                    const lastPage = localStorage.getItem('ar_last_ig_page');
                    if (lastPage) {
                        this.selectedPageId = lastPage;
                        this.fetchRules();
                        this.fetchTokenDebug();
                        this.fetchPageSettings();
                    }
                },

                getPageName() {
                    const page = this.pages.find(p => p.ig_business_id == this.selectedPageId);
                    return page ? '@' + page.ig_username : '';
                },

                fetchRules() {
                    if (!this.selectedPageId) return;
                    fetch(`ajax_auto_reply.php?action=fetch_rules&page_id=${this.selectedPageId}&source=comment&platform=instagram`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.rules = data.rules;
                                this.stats.total_replies = this.rules.reduce((acc, r) => acc + parseInt(r.usage_count || 0), 0);
                            }
                        });
                },

                fetchTokenDebug() {
                    const page = this.pages.find(p => p.ig_business_id == this.selectedPageId);
                    if (!page) return;
                    fetch(`ajax_auto_reply.php?action=get_token_debug&page_id=${page.page_id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) this.debugInfo = data;
                        });
                },

                fetchPageSettings() {
                    fetch(`ajax_auto_reply.php?action=fetch_page_settings&page_id=${this.selectedPageId}&platform=instagram`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.cooldown = data.settings.bot_cooldown_seconds;
                                this.excludeKeywords = data.settings.bot_exclude_keywords == 1;
                            }
                        });
                },

                savePageSettings() {
                    let formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    formData.append('cooldown', this.cooldown);
                    formData.append('exclude_keywords', this.excludeKeywords ? '1' : '0');
                    formData.append('platform', 'instagram');
                    fetch('ajax_auto_reply.php?action=save_page_settings', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) alert('<?php echo __('settings_saved_success'); ?>');
                        });
                },

                openAddModal() {
                    this.editMode = false;
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
                    this.modalHideComment = rule.hide_comment == 1;
                    this.showModal = true;
                },

                saveRule() {
                    let formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    formData.append('keywords', this.modalKeywords);
                    formData.append('reply', this.modalReply);
                    formData.append('hide_comment', this.modalHideComment ? '1' : '0');
                    formData.append('platform', 'instagram');
                    if (this.editMode) formData.append('rule_id', this.currentRuleId);

                    fetch('ajax_auto_reply.php?action=save_rule', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showModal = false;
                                this.fetchRules();
                            } else {
                                alert(data.error);
                            }
                        });
                },

                deleteRule(id) {
                    if (!confirm('<?php echo __('confirm_delete_rule'); ?>')) return;
                    let formData = new FormData();
                    formData.append('rule_id', id);
                    formData.append('page_id', this.selectedPageId);
                    fetch('ajax_auto_reply.php?action=delete_rule', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) this.fetchRules();
                        });
                }
            }
        }
    </script>
</div>
<?php require_once '../includes/footer.php'; ?>