<?php
// user/ig_moderator.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Fetch Instagram Pages (only those with ig_business_id)
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id IN (
    SELECT MIN(p.id) 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? AND p.ig_business_id IS NOT NULL
    GROUP BY p.ig_business_id
) ORDER BY ig_username ASC");
$stmt->execute([$user_id]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$preselected_id = $_GET['ig_id'] ?? '';
$preselected_parent = '';
$preselected_name = '';
if ($preselected_id) {
    foreach ($pages as $p) {
        if ($p['ig_business_id'] == $preselected_id) {
            $preselected_parent = $p['page_id'];
            $preselected_name = '@' . $p['ig_username'];
            break;
        }
    }
}

?>

<div id="main-user-container" class="main-user-container flex min-h-screen bg-gray-900 font-sans"
    style="font-family: <?php echo $font; ?>;" x-data="moderatorApp()">
    <?php include '../includes/user_sidebar.php'; ?>

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
            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('select_ig_account_to_start'); ?></h3>
            <p class="text-gray-500 max-w-sm mx-auto">
                <?php echo __('ig_moderator_desc') ?: 'قم بحماية حسابك من التعليقات المسيئة، أرقام الهواتف، والروابط الدعائية تلقائياً.'; ?>
            </p>
            <div class="flex gap-4">
                <button @click="showDeleteModal = false"
                    class="flex-1 py-4 bg-white/5 hover:bg-white/10 text-gray-300 rounded-2xl font-bold transition-all"><?php echo __('cancel'); ?></button>
                <button @click="deleteLog()" :disabled="deleting"
                    class="flex-1 py-4 bg-red-600 hover:bg-red-700 text-white rounded-2xl font-bold transition-all flex items-center justify-center gap-2">
                    <span x-text="deleting ? '...' : '<?php echo __('confirm'); ?>'"></span>
                </button>
            </div>
        </div>
    </div>

    <main class="flex-1 flex flex-col bg-gray-900/50 backdrop-blur-md relative p-6">
        <div class="flex-none flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1
                    class="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-pink-500 via-purple-500 to-orange-500">
                    <?php echo __('ig_moderator'); ?>
                </h1>
                <p class="text-gray-400 mt-2"><?php echo __('ig_moderator_desc'); ?></p>
            </div>

            <template x-if="selectedPageId">
                <div class="flex items-center gap-3 p-3 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-sm">
                    <div class="w-2 h-2 rounded-full animate-pulse"
                        :class="debugInfo?.subscribed ? 'bg-green-500' : 'bg-red-500'"></div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400"
                        x-text="debugInfo?.subscribed ? '<?php echo __('active'); ?>' : '<?php echo __('inactive'); ?>'"></span>
                    <div class="h-4 w-px bg-white/10 mx-1"></div>
                    <span class="text-xs font-bold text-white" x-text="selectedPageName"></span>
                </div>
            </template>
        </div>

        <div class="flex-none mb-8">
            <div
                class="glass-panel p-6 rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl hover:border-pink-500/20 transition-all shadow-xl max-w-2xl">
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
                            @change="updatePageName($event); loadRules(); fetchTokenDebug();"
                            class="w-full bg-black/40 border border-white/10 text-white text-sm rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-pink-500 block p-3.5 pr-10 appearance-none transition-all group-hover:border-white/20">
                            <option value=""><?php echo __('select_ig_account'); ?>...</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo htmlspecialchars($page['ig_business_id']); ?>"
                                    data-name="@<?php echo htmlspecialchars($page['ig_username']); ?>"
                                    data-parent="<?php echo htmlspecialchars($page['page_id']); ?>" <?php echo $preselected_id == $page['ig_business_id'] ? 'selected' : ''; ?>>
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

                    <template x-if="selectedPageId">
                        <div class="flex gap-2">
                            <button @click="subscribePage()"
                                class="flex-1 flex items-center justify-center gap-2 px-6 py-3.5 bg-pink-600 hover:bg-pink-500 text-white rounded-xl transition-all shadow-lg shadow-pink-600/20 font-bold text-sm"
                                :disabled="subscribing">
                                <span x-text="subscribing ? '...' : '<?php echo __('activate_protection'); ?>'"></span>
                            </button>
                            <button @click="toggleProtection()"
                                class="flex items-center justify-center gap-2 px-4 py-3.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/10 rounded-xl transition-all font-bold">
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

        <div x-show="!selectedPageId" class="mb-12">
            <div
                class="glass-panel p-20 rounded-[3rem] border border-white/5 border-dashed flex flex-col items-center justify-center text-center group transition-all hover:bg-white/5">
                <div
                    class="w-24 h-24 rounded-[2.5rem] bg-pink-500/10 flex items-center justify-center mb-8 border border-white/5 group-hover:scale-110 transition-all duration-500">
                    <svg class="w-12 h-12 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z">
                        </path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-3"><?php echo __('select_ig_account'); ?></h2>
                <p class="text-gray-500 max-w-sm"><?php echo __('unselected_page_hint'); ?></p>
            </div>
        </div>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-8 pb-20" x-show="selectedPageId" x-cloak>
            <!-- Rules -->
            <div class="lg:col-span-12 space-y-8">
                <div class="glass-panel p-8 rounded-3xl bg-white/5 border border-white/10">
                    <h3 class="text-xl font-bold text-white mb-6"><?php echo __('moderation_rules'); ?></h3>
                    <div class="space-y-6">
                        <div>
                            <label
                                class="block text-sm font-bold text-gray-400 mb-3"><?php echo __('banned_keywords'); ?></label>
                            <textarea x-model="rules.banned_keywords"
                                placeholder="<?php echo __('banned_keywords_placeholder'); ?>"
                                class="w-full bg-black/40 border border-white/10 rounded-2xl p-4 text-white min-h-[100px]"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                class="flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/10">
                                <div>
                                    <h4 class="text-white font-bold"><?php echo __('hide_phones'); ?></h4>
                                    <p class="text-xs text-gray-500 font-medium"><?php echo __('phone_violation'); ?>
                                    </p>
                                </div>
                                <input type="checkbox" x-model="rules.hide_phones"
                                    class="w-6 h-6 rounded border-white/10 text-pink-600 focus:ring-pink-500 bg-black/40">
                            </div>
                            <div
                                class="flex items-center justify-between p-6 bg-white/5 rounded-3xl border border-white/10">
                                <div>
                                    <h4 class="text-white font-bold"><?php echo __('hide_links'); ?></h4>
                                    <p class="text-xs text-gray-500 font-medium"><?php echo __('link_violation'); ?></p>
                                </div>
                                <input type="checkbox" x-model="rules.hide_links"
                                    class="w-6 h-6 rounded border-white/10 text-pink-600 focus:ring-pink-500 bg-black/40">
                            </div>
                        </div>
                        <button @click="saveRules()"
                            class="w-full bg-pink-600 hover:bg-pink-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-pink-600/20"><?php echo __('save_settings'); ?></button>
                    </div>
                </div>

                <div class="glass-panel p-8 rounded-3xl bg-white/5 border border-white/10">
                    <h3 class="text-xl font-bold text-white mb-6"><?php echo __('moderation_logs'); ?></h3>
                    <div class="space-y-4">
                        <template x-for="log in logs" :key="log.id">
                            <div
                                class="p-4 bg-white/5 rounded-2xl border border-white/5 flex justify-between items-center group hover:border-pink-500/30 transition-all">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-bold text-white" x-text="log.sender_name"></span>
                                        <span class="text-[10px] text-gray-500" x-text="log.created_at"></span>
                                    </div>
                                    <p class="text-sm text-gray-400" x-text="log.comment_text"></p>
                                    <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider"
                                        x-text="log.reason"></span>
                                </div>
                                <button @click="confirmDelete(log.id)"
                                    class="text-gray-500 p-2 hover:bg-red-500/10 hover:text-red-400 rounded-xl transition-all opacity-0 group-hover:opacity-100">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <div x-show="logs.length === 0"
                            class="text-center py-20 bg-black/20 rounded-3xl border border-dashed border-white/5">
                            <p class="text-gray-500"><?php echo __('no_moderation_logs'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function moderatorApp() {
            return {
                selectedPageId: '<?php echo $preselected_id; ?>',
                selectedPageName: '<?php echo $preselected_name; ?>',
                selectedParentId: '<?php echo $preselected_parent; ?>',
                rules: { banned_keywords: '', hide_phones: 0, hide_links: 0, action_type: 'hide', is_active: 1 },
                logs: [],
                debugInfo: null,
                subscribing: false,
                deleting: false,
                showDeleteModal: false,
                currentLogId: null,

                init() {
                    if (this.selectedPageId) {
                        this.loadRules();
                        this.fetchTokenDebug();
                    }
                },

                updatePageName(e) {
                    const opt = e.target.options[e.target.selectedIndex];
                    this.selectedPageName = opt.dataset.name;
                    this.selectedParentId = opt.dataset.parent;
                },

                loadRules() {
                    if (!this.selectedPageId) return;
                    fetch(`ajax_moderator.php?action=get_rules&page_id=${this.selectedPageId}&platform=instagram`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.status === 'success') this.rules = res.data;
                            this.loadLogs();
                        });
                },

                loadLogs() {
                    fetch(`ajax_moderator.php?action=get_logs&page_id=${this.selectedPageId}&platform=instagram`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.status === 'success') this.logs = res.data;
                        });
                },

                saveRules() {
                    let fd = new FormData();
                    fd.append('action', 'save_rules');
                    fd.append('page_id', this.selectedPageId);
                    fd.append('platform', 'instagram');
                    fd.append('banned_keywords', this.rules.banned_keywords);
                    fd.append('hide_phones', this.rules.hide_phones ? 1 : 0);
                    fd.append('hide_links', this.rules.hide_links ? 1 : 0);
                    fd.append('action_type', 'hide');
                    fd.append('is_active', 1);

                    fetch('ajax_moderator.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(res => {
                            if (res.status === 'success') alert('<?php echo __('settings_saved_success'); ?>');
                        });
                },

                fetchTokenDebug() {
                    if (!this.selectedParentId) return;
                    fetch(`ajax_moderator.php?action=get_token_debug&page_id=${this.selectedParentId}&ig_id=${this.selectedPageId}`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.status === 'success') this.debugInfo = res.data;
                        });
                },

                subscribePage() {
                    this.subscribing = true;
                    let fd = new FormData();
                    fd.append('action', 'subscribe_page');
                    fd.append('page_id', this.selectedParentId);
                    fetch('ajax_moderator.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(res => {
                            this.subscribing = false;
                            this.fetchTokenDebug();
                            alert(res.message);
                        });
                },

                toggleProtection() {
                    if (this.rules.is_active && !confirm('<?php echo __('confirm_stop_protection'); ?>')) return;
                    let fd = new FormData();
                    fd.append('action', 'unsubscribe_page');
                    fd.append('page_id', this.selectedParentId);
                    fetch('ajax_moderator.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(res => {
                            this.fetchTokenDebug();
                            alert(res.message);
                        });
                },

                confirmDelete(id) {
                    this.currentLogId = id;
                    this.showDeleteModal = true;
                },

                deleteLog() {
                    this.deleting = true;
                    let fd = new FormData();
                    fd.append('action', 'delete_log');
                    fd.append('log_id', this.currentLogId);
                    fetch('ajax_moderator.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(res => {
                            this.deleting = false;
                            this.showDeleteModal = false;
                            this.loadLogs();
                        });
                }
            }
        }
    </script>
</div>
<?php require_once '../includes/footer.php'; ?>