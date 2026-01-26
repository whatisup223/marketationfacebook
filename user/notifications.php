<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// --- User Preferences ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_notifications'])) {
    $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $prefs = json_decode($stmt->fetchColumn() ?: '{}', true);

    $current = $prefs['notifications_muted'] ?? false;
    $prefs['notifications_muted'] = !$current;

    $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
    $stmt->execute([json_encode($prefs), $user_id]);

    header("Location: notifications.php");
    exit;
}

// Check mute status
$stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$prefs = json_decode($stmt->fetchColumn() ?: '{}', true);
$is_muted = $prefs['notifications_muted'] ?? false;


// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } elseif ($_POST['action'] === 'delete_all') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    header("Location: notifications.php");
    exit;
}


// --- Pagination ---
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->bindParam(2, $limit, PDO::PARAM_INT);
$stmt->bindParam(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 9990;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-overlay.open {
        display: flex;
    }

    .modal-content {
        background: #1f2937;
        border: 1px solid #374151;
        border-radius: 1rem;
        padding: 2rem;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: scale(0.95);
        transition: transform 0.2s;
    }

    .modal-overlay.open .modal-content {
        transform: scale(1);
    }
</style>

<!-- Delete All Confirmation Modal -->
<div id="deleteAllModal" class="modal-overlay">
    <div class="modal-content">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2"><?php echo __('delete_all'); ?></h3>
            <p class="text-sm text-gray-400 mb-6">
                <?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف جميع الإشعارات؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to delete all notifications? This action cannot be undone.'; ?>
            </p>
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeModal('deleteAllModal')"
                    class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <?php echo __('cancel'); ?>
                </button>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors font-bold shadow-lg">
                        <?php echo $lang === 'ar' ? 'نعم، احذف الكل' : 'Yes, Delete All'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Mark All Read Confirmation Modal -->
<div id="markReadModal" class="modal-overlay">
    <div class="modal-content">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 mb-4">
                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 13l4 4L19 7m-1.99-6.01L9 16.29 4.3 11.6" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2"><?php echo __('mark_all_read'); ?></h3>
            <p class="text-sm text-gray-400 mb-6">
                <?php echo $lang === 'ar' ? 'هل تريد تحديد جميع الإشعارات كمقروءة؟' : 'Do you want to mark all notifications as read?'; ?>
            </p>
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeModal('markReadModal')"
                    class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <?php echo __('cancel'); ?>
                </button>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors font-bold shadow-lg">
                        <?php echo $lang === 'ar' ? 'نعم، تحديد الكل' : 'Yes, Mark All'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Generic Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="modal-content">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2"><?php echo __('delete'); ?></h3>
            <p class="text-sm text-gray-400 mb-6" id="deleteConfirmMsg"></p>
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeModal('deleteConfirmModal')"
                    class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <?php echo __('cancel'); ?>
                </button>
                <button type="button" onclick="confirmDeleteAction()"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors font-bold shadow-lg">
                    <?php echo $lang === 'ar' ? 'نعم، احذف' : 'Yes, Delete'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold"><?php echo __('notifications'); ?></h1>
            <form method="POST">
                <input type="hidden" name="toggle_notifications" value="1">
                <button type="submit"
                    class="px-4 py-2 rounded-xl text-sm font-bold transition-all border <?php echo $is_muted ? 'bg-red-500/20 text-red-400 border-red-500/30 hover:bg-red-500/30' : 'bg-gray-800 text-gray-300 border-gray-700 hover:text-white'; ?>">
                    <?php echo $is_muted ? __('unmute_notifications') : __('mute_notifications'); ?>
                </button>
            </form>
        </div>

        <?php if ($total_notifications > 0): ?>
            <div
                class="bg-gray-900/50 rounded-xl p-4 mb-6 border border-gray-800 flex flex-wrap gap-4 items-center justify-between">
                <div class="text-sm text-gray-400">
                    <?php
                    $show_text = $lang === 'ar' ? "عرض %d من أصل %d إشعار" : "Showing %d of %d notifications";
                    echo sprintf($show_text, count($notifications), $total_notifications);
                    ?>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="openModal('markReadModal')"
                        class="text-indigo-400 hover:text-indigo-300 text-sm font-bold flex items-center gap-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7m-1.99-6.01L9 16.29 4.3 11.6" />
                        </svg>
                        <?php echo __('mark_all_read'); ?>
                    </button>
                    <div class="w-px h-4 bg-gray-700"></div>
                    <button type="button" onclick="openModal('deleteAllModal')"
                        class="text-red-400 hover:text-red-300 text-sm font-bold flex items-center gap-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <?php echo __('delete_all'); ?>
                    </button>
                    <!-- Bulk Delete Button (Hidden initially) -->
                    <button type="button" id="bulkDeleteBtn" onclick="deleteSelected()" style="display:none;"
                        class="text-red-400 hover:text-red-300 text-sm font-bold flex items-center gap-1 transition-colors border-l border-gray-700 pl-3 ml-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <?php echo $lang === 'ar' ? 'حذف المحدد' : 'Delete Selected'; ?> (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- List -->
        <div class="space-y-4">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="glass-card p-5 rounded-2xl flex items-start gap-4 transition-all hover:bg-gray-800/60 <?php echo !$notif['is_read'] ? 'border-l-4 border-l-indigo-500 bg-gray-800/40' : ''; ?>"
                        id="notif-card-<?php echo $notif['id']; ?>">
                        <!-- Checkbox -->
                        <div class="flex-shrink-0 mt-1.5">
                            <input type="checkbox" value="<?php echo $notif['id']; ?>"
                                class="notif-checkbox w-4 h-4 rounded border-gray-600 bg-gray-700 text-indigo-600 focus:ring-indigo-500"
                                onchange="toggleBulkBtn()">
                        </div>

                        <div class="flex-shrink-0 mt-1">
                            <?php if (!$notif['is_read']): ?>
                                <div
                                    class="w-3 h-3 bg-indigo-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(99,102,241,0.5)]">
                                </div>
                            <?php else: ?>
                                <div class="w-3 h-3 bg-gray-700 rounded-full"></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-bold text-white mb-1"><?php echo htmlspecialchars(__($notif['title'])); ?>
                            </h3>
                            <p class="text-gray-400 text-sm mb-3 leding-relaxed">
                                <?php
                                $msgText = $notif['message'];
                                $decoded = json_decode($msgText, true);
                                if ($decoded && is_array($decoded) && isset($decoded['key'])) {
                                    $params = $decoded['params'] ?? [];
                                    if (isset($decoded['param_keys']) && is_array($decoded['param_keys'])) {
                                        foreach ($decoded['param_keys'] as $idx) {
                                            if (isset($params[$idx])) {
                                                $params[$idx] = __($params[$idx]);
                                            }
                                        }
                                    }
                                    $msgText = vsprintf(__($decoded['key']), $params);
                                } else {
                                    $msgText = __($msgText);
                                }
                                echo htmlspecialchars($msgText);
                                ?>
                            </p>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></span>
                                <?php if ($notif['link'] && $notif['link'] != '#'): ?>
                                    <a href="<?php echo $prefix . htmlspecialchars($notif['link']); ?>"
                                        onclick="fetch('<?php echo $prefix; ?>includes/api/mark_notification_read.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: <?php echo $notif['id']; ?>}) });"
                                        class="text-indigo-400 hover:text-white transition-colors flex items-center gap-1 font-bold group">
                                        <?php echo __('view_details'); ?>
                                        <svg class="w-3 h-3 rtl:rotate-180 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-transform"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                                            </path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                            <button onclick="markSingleRead(this, <?php echo $notif['id']; ?>)"
                                title="<?php echo __('mark_read'); ?>"
                                class="p-2 text-gray-500 hover:text-green-400 hover:bg-green-400/10 rounded-lg transition-all self-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                        <?php endif; ?>
                        <button onclick="deleteSingle(<?php echo $notif['id']; ?>)" title="<?php echo __('delete'); ?>"
                            class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-all self-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-16">
                    <div
                        class="bg-gray-800/50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 border border-gray-700">
                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2"><?php echo __('no_notifications'); ?></h3>
                    <p class="text-gray-500"><?php echo __('no_notifications_desc'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-8 gap-2">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>"
                        class="w-10 h-10 flex items-center justify-center rounded-xl font-bold transition-all <?php echo $i == $page ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }
    // Close on outside click
    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('open');
        }
    }
    function markSingleRead(btn, id) {
        // Optimistic UI update
        btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

        fetch('<?php echo $prefix; ?>includes/api/mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const card = btn.closest('.glass-card');
                    card.classList.remove('border-l-4', 'border-l-indigo-500', 'bg-gray-800/40');
                    const dotWrapper = card.querySelector('.flex-shrink-0');
                    if (dotWrapper) {
                        dotWrapper.innerHTML = '<div class="w-3 h-3 bg-gray-700 rounded-full"></div>';
                    }
                    btn.style.opacity = '0';
                    setTimeout(() => btn.remove(), 300);
                }
            });
    }

    // --- Unified Delete Logic ---
    let deleteMode = ''; // 'single' or 'bulk'
    let deleteId = null;
    let deleteIds = [];

    function toggleBulkBtn() {
        const checkboxes = document.querySelectorAll('.notif-checkbox:checked');
        const count = checkboxes.length;
        const btn = document.getElementById('bulkDeleteBtn');
        const countSpan = document.getElementById('selectedCount');

        if (count > 0) {
            btn.style.display = 'flex';
            countSpan.innerText = count;
        } else {
            btn.style.display = 'none';
        }
    }

    function deleteSingle(id) {
        deleteMode = 'single';
        deleteId = id;
        document.getElementById('deleteConfirmMsg').innerText = '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف هذا الإشعار؟' : 'Are you sure you want to delete this notification?'; ?>';
        openModal('deleteConfirmModal');
    }

    function deleteSelected() {
        const checkboxes = document.querySelectorAll('.notif-checkbox:checked');
        if (checkboxes.length === 0) return;

        deleteMode = 'bulk';
        deleteIds = Array.from(checkboxes).map(cb => cb.value);

        document.getElementById('deleteConfirmMsg').innerText = '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف العناصر المحددة؟ (' : 'Delete selected items? ('; ?>' + deleteIds.length + ')';
        openModal('deleteConfirmModal');
    }

    function confirmDeleteAction() {
        closeModal('deleteConfirmModal');
        let payload = {};

        if (deleteMode === 'single') {
            payload = { id: deleteId };
        } else {
            payload = { ids: deleteIds };
        }

        // Show loading state if needed, but for now just fetch
        fetch('<?php echo $prefix; ?>includes/api/delete_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (deleteMode === 'single') {
                        const card = document.getElementById('notif-card-' + deleteId);
                        if (card) { card.style.opacity = '0'; setTimeout(() => card.remove(), 300); }
                    } else {
                        deleteIds.forEach(id => {
                            const card = document.getElementById('notif-card-' + id);
                            if (card) card.remove();
                        });
                        toggleBulkBtn();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed'));
                }
            })
            .catch(err => console.error(err));
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>