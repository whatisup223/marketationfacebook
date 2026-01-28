<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$ticket_id = $_GET['id'] ?? 0;

// Fetch Ticket + User Info
$stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.avatar 
                       FROM support_tickets t 
                       LEFT JOIN users u ON t.user_id = u.id 
                       WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header("Location: support.php");
    exit;
}

// Mark as read by admin
if ($ticket['is_read_admin'] == 0) {
    $stmt = $pdo->prepare("UPDATE support_tickets SET is_read_admin = 1 WHERE id = ?");
    $stmt->execute([$ticket_id]);
}

// Handle Actions (Reply, Update Status, Close, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply'])) {
        $message = trim($_POST['message'] ?? '');
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)");
            $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);

            // Update ticket status to answered and mark unread for user
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'answered', is_read_user = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ticket_id]);

            // Notify User (Internal Notification) - Store ticket ID instead of subject
            addNotification($ticket['user_id'], 'admin_response', json_encode(['key' => 'ticket_notification_fmt', 'params' => ["#$ticket_id"]]), "user/view_ticket.php?id=$ticket_id");

            header("Location: view_ticket.php?id=$ticket_id");
            exit;
        }
    } elseif (isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? '';
        $allowed_statuses = ['open', 'answered', 'closed', 'pending', 'solved'];

        if (in_array($new_status, $allowed_statuses)) {
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);

            // Notify User - Store status as translation key
            addNotification($ticket['user_id'], 'ticket_status_updated', json_encode(['key' => 'ticket_status_change_fmt', 'params' => ['status_' . $new_status], 'param_keys' => [0]]), "user/view_ticket.php?id=$ticket_id");

            header("Location: view_ticket.php?id=$ticket_id");
            exit;
        }
    } elseif (isset($_POST['delete_ticket'])) {
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        header("Location: support.php");
        exit;
    }
}

// Fetch Messages
$stmt = $pdo->prepare("SELECT m.*, u.name as user_name, u.avatar 
                       FROM ticket_messages m 
                       LEFT JOIN users u ON m.user_id = u.id 
                       WHERE m.ticket_id = ? 
                       ORDER BY m.created_at ASC");
$stmt->execute([$ticket_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{ showDeleteModal: false }">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">

            <!-- Chat Area -->
            <div class="lg:col-span-3 flex flex-col h-[calc(100vh-10rem)]">
                <div
                    class="glass-card flex-1 flex flex-col rounded-[2.5rem] overflow-hidden border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.3)]">
                    <!-- Chat Header -->
                    <div
                        class="p-6 border-b border-white/5 flex justify-between items-center bg-black/30 backdrop-blur-md">
                        <div class="flex items-center gap-4">
                            <a href="support.php"
                                class="p-2.5 bg-white/5 rounded-2xl border border-white/10 hover:bg-white/10 transition-all group">
                                <svg class="w-5 h-5 <?php echo $lang == 'ar' ? 'rotate-180' : ''; ?> group-hover:-translate-x-1 transition-transform"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                            </a>
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <h1 class="text-xl font-bold text-white leading-tight">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </h1>
                                    <span class="px-3 py-1 rounded-xl text-[10px] font-bold uppercase border shadow-sm
                                        <?php
                                        if ($ticket['status'] == 'open')
                                            echo 'bg-green-500/10 text-green-400 border-green-500/20';
                                        elseif ($ticket['status'] == 'answered')
                                            echo 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                        elseif ($ticket['status'] == 'pending')
                                            echo 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
                                        elseif ($ticket['status'] == 'solved')
                                            echo 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
                                        else
                                            echo 'bg-gray-700/20 text-gray-400 border-gray-600/30';
                                        ?>">
                                        <?php echo __('status_' . $ticket['status']); ?>
                                    </span>
                                </div>
                                <p class="text-[10px] text-gray-500 font-medium tracking-wide uppercase">
                                    <?php echo __('ticket_no'); ?><?php echo $ticket['id']; ?> •
                                    <?php echo __('created_on'); ?>
                                    <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Admin Actions -->
                        <div class="flex items-center gap-3">
                            <form method="POST" class="flex items-center">
                                <select name="status" onchange="this.form.submit()"
                                    class="bg-slate-800 text-white text-xs font-bold rounded-xl border-slate-700 py-2.5 pl-3 pr-8 focus:ring-2 focus:ring-indigo-500/30 transition-all cursor-pointer">
                                    <?php foreach (['open', 'pending', 'answered', 'solved', 'closed'] as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo $ticket['status'] == $st ? 'selected' : ''; ?>>
                                            <?php echo __('status_' . $st); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>

                            <button @click="showDeleteModal = true"
                                class="w-10 h-10 flex items-center justify-center bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition-all shadow-lg hover:shadow-red-500/20 border border-red-500/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto p-6 md:p-8 space-y-8 custom-scrollbar bg-white/[0.01]"
                        id="messages-container">
                        <?php foreach ($messages as $msg):
                            $isMsgAdmin = $msg['is_admin'];
                            ?>
                            <div
                                class="flex <?php echo $isMsgAdmin ? 'justify-end' : 'justify-start'; ?> animate-fade-in-up">
                                <div
                                    class="flex items-start gap-4 max-w-[85%] <?php echo $isMsgAdmin ? 'flex-row-reverse' : 'flex-row'; ?>">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 rounded-2xl flex items-center justify-center border border-white/10
                                        <?php echo $isMsgAdmin ? 'bg-indigo-600/20 text-indigo-400 shadow-[0_0_15px_rgba(99,102,241,0.1)]' : 'bg-slate-700/50 text-slate-300'; ?>">
                                        <?php if ($isMsgAdmin): ?>
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                                                </path>
                                            </svg>
                                        <?php else: ?>
                                            <span
                                                class="text-[10px] font-bold"><?php echo mb_substr($ticket['user_name'], 0, 3, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="space-y-1.5">
                                        <div
                                            class="flex items-center gap-2 <?php echo $isMsgAdmin ? 'flex-row-reverse' : 'flex-row'; ?>">
                                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                                <?php echo $isMsgAdmin ? __('admin_label') : __('user_label'); ?>
                                            </span>
                                        </div>
                                        <div
                                            class="p-5 rounded-[2rem] text-sm leading-relaxed shadow-xl
                                            <?php echo $isMsgAdmin ? 'bg-indigo-600 text-white rounded-tr-none shadow-indigo-500/10' : 'bg-slate-800 border border-white/5 text-gray-100 rounded-tl-none'; ?>">
                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        </div>
                                        <div
                                            class="text-[9px] font-medium text-gray-600 px-3 <?php echo $isMsgAdmin ? 'text-right' : 'text-left'; ?>">
                                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Area -->
                    <div class="p-6 border-t border-white/5 bg-black/40 backdrop-blur-md">
                        <form method="POST" class="flex gap-4">
                            <textarea name="message" rows="1" required placeholder="<?php echo __('write_reply'); ?>"
                                class="flex-1 bg-slate-900/80 border border-slate-700/50 rounded-2xl px-6 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all resize-none font-medium placeholder:text-gray-600"></textarea>
                            <button type="submit" name="reply"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 rounded-2xl font-bold shadow-xl shadow-indigo-500/30 transition-all flex items-center justify-center hover:-translate-y-1 active:scale-95 group">
                                <svg class="w-6 h-6 rtl:rotate-180 group-hover:translate-x-1 transition-transform"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Info Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <div
                    class="glass-card p-8 rounded-[2.5rem] border border-white/10 shadow-2xl relative overflow-hidden group">
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 blur-3xl rounded-full -mr-16 -mt-16 group-hover:bg-indigo-500/10 transition-colors">
                    </div>

                    <h3 class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.2em] mb-8 px-1 relative">
                        <?php echo __('user_info'); ?>
                    </h3>

                    <div class="flex flex-col items-center text-center space-y-5 relative">
                        <div class="relative">
                            <div
                                class="w-24 h-24 rounded-[2.5rem] bg-slate-800 p-1.5 border-2 border-indigo-500/20 shadow-2xl group-hover:border-indigo-500/40 transition-all duration-500 rotate-3 group-hover:rotate-0">
                                <?php if ($ticket['avatar']): ?>
                                    <img src="../<?php echo $ticket['avatar']; ?>"
                                        class="w-full h-full object-cover rounded-[2.2rem]">
                                <?php else: ?>
                                    <div
                                        class="w-full h-full flex items-center justify-center bg-indigo-500/10 text-indigo-400 text-3xl font-bold rounded-[2.2rem]">
                                        <?php echo mb_substr($ticket['user_name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div
                                class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 border-4 border-slate-900 rounded-full">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <h4 class="text-white font-bold text-xl">
                                <?php echo htmlspecialchars($ticket['user_name']); ?>
                            </h4>
                            <p class="text-xs text-gray-500 font-medium">
                                <?php echo htmlspecialchars($ticket['email']); ?>
                            </p>
                        </div>

                        <div class="w-full pt-4">
                            <a href="users.php?search=<?php echo urlencode($ticket['email']); ?>"
                                class="block w-full py-4 rounded-2xl bg-white/5 border border-white/10 text-xs font-bold text-gray-300 hover:bg-white/10 hover:text-white transition-all shadow-lg hover:shadow-indigo-500/5 text-center">
                                <?php echo __('view_user_profile'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display: none;">

        <div class="glass-card w-full max-w-md rounded-[2.5rem] p-8 border border-white/10 shadow-2xl transform transition-all"
            @click.away="showDeleteModal = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <div
                class="w-16 h-16 bg-red-500/10 rounded-2xl flex items-center justify-center text-red-500 mx-auto mb-6 border border-red-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>

            <h3 class="text-xl font-bold text-white text-center mb-2"><?php echo __('confirm_delete'); ?></h3>
            <p class="text-gray-400 text-center text-sm mb-8 leading-relaxed"><?php echo __('confirm_delete_ticket'); ?>
            </p>

            <div class="flex gap-4">
                <button @click="showDeleteModal = false"
                    class="flex-1 py-4 rounded-2xl bg-white/5 border border-white/10 text-sm font-bold text-gray-400 hover:bg-white/10 hover:text-white transition-all">
                    <?php echo __('cancel'); ?>
                </button>
                <form method="POST" class="flex-1">
                    <button type="submit" name="delete_ticket"
                        class="w-full py-4 rounded-2xl bg-red-600 hover:bg-red-500 text-white text-sm font-bold shadow-xl shadow-red-500/20 transition-all hover:-translate-y-1">
                        <?php echo __('confirm_delete'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    @keyframes fade-in-up {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in-up {
        animation: fade-in-up 0.4s ease-out forwards;
    }
</style>

<script>
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>