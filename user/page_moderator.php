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

<div class="flex min-h-screen pt-4" x-data="autoModerator()">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <main class="flex-1 p-4 md:p-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header Section -->
            <div
                class="mb-8 p-8 glass-panel rounded-[2.5rem] border border-white/5 bg-gray-900/40 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-8 opacity-10">
                    <svg class="w-32 h-32 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div class="relative z-10">
                    <h1 class="text-3xl font-bold text-white mb-2"><?php echo __('auto_moderator'); ?></h1>
                    <p class="text-gray-400 max-w-2xl"><?php echo __('auto_moderator_desc'); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Configuration Form -->
                <div class="lg:col-span-12">
                    <div class="glass-panel p-8 rounded-[2.5rem] border border-white/5 bg-gray-900/40">
                        <div class="flex flex-col md:flex-row gap-6 items-end mb-8">
                            <div class="flex-1 w-full">
                                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-3">
                                    <?php echo __('select_page'); ?>
                                </label>
                                <select x-model="selectedPageId" @change="loadRules()"
                                    class="w-full bg-black/40 border border-white/10 rounded-2xl px-6 py-4 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <option value="">
                                        <?php echo __('select_page'); ?>...
                                    </option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo $page['page_id']; ?>">
                                            <?php echo htmlspecialchars($page['page_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-center gap-4 h-14" x-show="selectedPageId">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" x-model="rules.is_active" class="sr-only peer">
                                    <div
                                        class="w-14 h-8 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600">
                                    </div>
                                    <span class="ml-3 text-sm font-bold text-gray-300 mr-3">
                                        <?php echo __('active'); ?>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div x-show="selectedPageId" x-transition class="space-y-8">
                            <!-- Banned Keywords -->
                            <div class="space-y-4">
                                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">
                                    <?php echo __('banned_keywords'); ?>
                                </label>
                                <textarea x-model="rules.banned_keywords" rows="4"
                                    class="w-full bg-black/40 border border-white/10 rounded-[1.5rem] px-6 py-4 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all resize-none"
                                    placeholder="أدخل الكلمات التي تريد حجبها، افصل بينها بفاصلة (،) أو (,)"></textarea>
                                <p class="text-[10px] text-gray-500 italic">مثال: سعر، بكام، منافس، كلمة مسيئة...</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <!-- Options -->
                                <div class="space-y-6">
                                    <label
                                        class="block text-xs font-black text-gray-500 uppercase tracking-widest">خيارات
                                        الحماية</label>

                                    <div
                                        class="flex items-center justify-between p-5 bg-black/20 rounded-2xl border border-white/5 group hover:border-indigo-500/30 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="p-3 bg-red-500/10 rounded-xl text-red-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-white">
                                                    <?php echo __('hide_phones'); ?>
                                                </p>
                                                <p class="text-[10px] text-gray-500">إخفاء أي تعليق يحتوي على رقم هاتف.
                                                </p>
                                            </div>
                                        </div>
                                        <input type="checkbox" x-model="rules.hide_phones"
                                            class="w-5 h-5 rounded border-white/10 bg-black/40 text-indigo-600 focus:ring-indigo-500">
                                    </div>

                                    <div
                                        class="flex items-center justify-between p-5 bg-black/20 rounded-2xl border border-white/5 group hover:border-indigo-500/30 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="p-3 bg-blue-500/10 rounded-xl text-blue-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826L10.242 9.172a4 4 0 015.656 0l4 4a4 4 0 01-5.656 5.656l-1.102 1.101" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-white">
                                                    <?php echo __('hide_links'); ?>
                                                </p>
                                                <p class="text-[10px] text-gray-500">إخفاء أي تعليق يحتوي على رابط
                                                    (URL).</p>
                                            </div>
                                        </div>
                                        <input type="checkbox" x-model="rules.hide_links"
                                            class="w-5 h-5 rounded border-white/10 bg-black/40 text-indigo-600 focus:ring-indigo-500">
                                    </div>
                                </div>

                                <!-- Action Type -->
                                <div class="space-y-6">
                                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">نوع
                                        الإجراء</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <button @click="rules.action_type = 'hide'"
                                            :class="rules.action_type === 'hide' ? 'bg-indigo-600 border-indigo-500' : 'bg-black/20 border-white/5'"
                                            class="p-6 rounded-2xl border flex flex-col items-center gap-3 transition-all hover:scale-[1.02]">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span class="text-xs font-bold text-white">إخفاء (موصى به)</span>
                                        </button>
                                        <button @click="rules.action_type = 'delete'"
                                            :class="rules.action_type === 'delete' ? 'bg-red-600 border-red-500' : 'bg-black/20 border-white/5'"
                                            class="p-6 rounded-2xl border flex flex-col items-center gap-3 transition-all hover:scale-[1.02]">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span class="text-xs font-bold text-white">مسح نهائي</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="pt-6">
                                <button @click="saveRules()" :disabled="saving"
                                    class="w-full py-5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-[1.5rem] font-black shadow-2xl shadow-indigo-600/20 transition-all flex items-center justify-center gap-3">
                                    <template x-if="saving">
                                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </template>
                                    <span x-text="saving ? 'جاري الحفظ...' : 'حفظ الإعدادات'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Logs (Full width below) -->
                <div class="lg:col-span-12">
                    <div class="glass-panel p-8 rounded-[2.5rem] border border-white/5 bg-gray-900/40">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-bold text-white">
                                <?php echo __('moderation_logs'); ?>
                            </h3>
                            <button @click="loadLogs()"
                                class="p-2 text-gray-500 hover:text-indigo-400 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead>
                                    <tr
                                        class="text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-white/5">
                                        <th class="pb-4 px-4 text-right"><?php echo __('user'); ?></th>
                                        <th class="pb-4 px-4 text-right"><?php echo __('comment'); ?></th>
                                        <th class="pb-4 px-4 text-right"><?php echo __('reason'); ?></th>
                                        <th class="pb-4 px-4 text-right"><?php echo __('action'); ?></th>
                                        <th class="pb-4 px-4 text-right"><?php echo __('date'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-white/5">
                                    <template x-for="log in logs" :key="log.id">
                                        <tr class="hover:bg-white/5 transition-colors">
                                            <td class="py-4 px-4">
                                                <div class="font-bold text-white"
                                                    x-text="log.sender_name || 'Anonymous'"></div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="text-gray-400 max-w-xs truncate" x-text="log.comment_text">
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span
                                                    class="px-2 py-1 rounded text-[10px] font-bold bg-white/5 text-gray-400"
                                                    x-text="log.reason"></span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span class="px-2 py-1 rounded text-[10px] font-bold"
                                                    :class="log.action_taken === 'hide' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-red-500/20 text-red-400'"
                                                    x-text="log.action_taken === 'hide' ? 'مخفي' : 'محذوف'"></span>
                                            </td>
                                            <td class="py-4 px-4 text-gray-500 text-xs"
                                                x-text="formatDate(log.created_at)"></td>
                                        </tr>
                                    </template>
                                    <template x-if="logs.length === 0">
                                        <tr>
                                            <td colspan="5" class="py-12 text-center text-gray-500 italic">
                                                <?php echo __('no_moderation_logs'); ?></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function autoModerator() {
        return {
            selectedPageId: '',
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

            init() {
                this.loadLogs();
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
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>