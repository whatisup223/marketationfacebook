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
    style="font-family: <?php echo $font; ?>;" x-data="messengerBotApp()">
    <?php include '../includes/user_sidebar.php'; ?>

    <main class="flex-1 flex flex-col bg-gray-900/50 backdrop-blur-md relative p-6 overflow-x-hidden">

        <!-- Header -->
        <div class="flex-none flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1
                    class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-pink-500 via-purple-500 to-orange-500">
                    <?php echo __('ig_messenger_bot'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('ig_messenger_bot_desc'); ?></p>
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

        <!-- Selection -->
        <div class="flex-none mb-8">
            <div
                class="glass-panel p-6 rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl hover:border-pink-500/20 transition-all shadow-xl max-w-2xl">
                <label class="block text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <div class="p-2 bg-pink-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                            </path>
                        </svg>
                    </div>
                    <?php echo __('select_ig_account'); ?>
                </label>

                <div class="relative group">
                    <select x-model="selectedPageId"
                        @change="localStorage.setItem('ar_last_ig_bot_page', selectedPageId); fetchRules(); fetchTokenDebug();"
                        class="w-full bg-black/40 border border-white/10 text-white text-sm rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-pink-500 block p-3.5 pr-10 appearance-none transition-all group-hover:border-white/20">
                        <option value=""><?php echo __('select_ig_account'); ?>...</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo htmlspecialchars($page['ig_business_id']); ?>">
                                @<?php echo htmlspecialchars($page['ig_username']); ?>
                                (<?php echo htmlspecialchars($page['page_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-none p-12 text-center" x-show="!selectedPageId">
            <div class="w-20 h-20 bg-pink-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('select_ig_account_to_start'); ?></h3>
            <p class="text-gray-400 max-w-sm mx-auto">
                <?php echo __('ig_messenger_bot_desc') ?: 'اختر حساب انستجرام للبدء في ضبط ردود الرسائل المباشرة.'; ?>
            </p>
        </div>

        <div x-show="selectedPageId" class="space-y-8" x-cloak>
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-2xl font-black text-white"><?php echo __('dm_reply_rules'); ?></h3>
                    <p class="text-gray-400 text-sm"><?php echo __('manage_auto_replies'); ?></p>
                </div>
                <button @click="openAddModal()"
                    class="bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-500 hover:to-purple-500 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-pink-600/20 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span><?php echo __('add_smart_reply'); ?></span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <template x-for="rule in rules" :key="rule.id">
                    <div>
                        <div
                            class="glass-panel p-6 rounded-3xl bg-white/5 border border-white/10 hover:border-pink-500/30 transition-all flex justify-between items-center">
                            <div>
                                <p class="text-xs text-pink-400 font-bold uppercase tracking-widest mb-1">
                                    <?php echo __('via_dm'); ?></p>
                                <h4 class="text-white font-bold text-lg" x-text="rule.keywords"></h4>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEditModal(rule)"
                                    class="p-2 text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </button>
                                <button @click="deleteRule(rule.id)"
                                    class="p-2 text-gray-400 hover:text-red-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 p-4 bg-black/20 rounded-2xl border border-white/5">
                            <p class="text-gray-300 text-sm leading-relaxed" x-text="rule.reply_message"></p>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="rules.length === 0"
                    class="text-center py-20 bg-white/5 rounded-[2.5rem] border border-dashed border-white/10">
                    <p class="text-gray-500"><?php echo __('no_dm_rules_hint'); ?></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        x-cloak>
        <div class="glass-panel w-full max-w-lg bg-gray-900 rounded-[2.5rem] border border-white/10 p-8 shadow-2xl">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-2xl font-black text-white"
                    x-text="editMode ? '<?php echo __('edit_dm_reply'); ?>' : '<?php echo __('add_dm_reply'); ?>'"></h3>
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
                        class="block text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-3"><?php echo __('bot_message'); ?></label>
                    <textarea x-model="modalReply" rows="5" placeholder="<?php echo __('reply_placeholder'); ?>"
                        class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-pink-500 outline-none transition-all"></textarea>
                </div>
                <button @click="saveRule()"
                    class="w-full bg-gradient-to-r from-pink-600 to-purple-600 hover:from-pink-500 hover:to-purple-500 text-white font-bold py-4 rounded-2xl shadow-lg shadow-pink-600/20 transition-all"><?php echo __('save_reply'); ?></button>
            </div>
        </div>
    </div>

    <script>
        function messengerBotApp() {
            return {
                selectedPageId: '<?php echo $preselected_id; ?>',
                rules: [],
                debugInfo: null,
                pages: <?php echo json_encode($pages); ?>,
                showModal: false,
                editMode: false,
                currentRuleId: null,
                modalKeywords: '',
                modalReply: '',

                init() {
                    const lastPage = localStorage.getItem('ar_last_ig_bot_page');
                    if (lastPage) {
                        this.selectedPageId = lastPage;
                        this.fetchRules();
                        this.fetchTokenDebug();
                    }
                },

                getPageName() {
                    const page = this.pages.find(p => p.ig_business_id == this.selectedPageId);
                    return page ? '@' + page.ig_username : '';
                },

                fetchRules() {
                    if (!this.selectedPageId) return;
                    fetch(`ajax_auto_reply.php?action=fetch_rules&page_id=${this.selectedPageId}&source=message&platform=instagram`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) this.rules = data.rules;
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

                openAddModal() {
                    this.editMode = false;
                    this.modalKeywords = '';
                    this.modalReply = '';
                    this.showModal = true;
                },

                editRule(rule) {
                    this.editMode = true;
                    this.currentRuleId = rule.id;
                    this.modalKeywords = rule.keywords;
                    this.modalReply = rule.reply_message;
                    this.showModal = true;
                },

                saveRule() {
                    let formData = new FormData();
                    formData.append('page_id', this.selectedPageId);
                    formData.append('keywords', this.modalKeywords);
                    formData.append('reply', this.modalReply);
                    formData.append('source', 'message');
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