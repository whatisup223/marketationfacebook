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

            // Notify User (Internal Notification)
            addNotification($ticket['user_id'], 'admin_response', json_encode(['key' => 'ticket_notification_fmt', 'params' => [$ticket['subject']]]), "user/view_ticket.php?id=$ticket_id");

            header("Location: view_ticket.php?id=$ticket_id");
            exit;
        }
    } elseif (isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? '';
        $allowed_statuses = ['open', 'answered', 'closed', 'pending', 'solved'];

        if (in_array($new_status, $allowed_statuses)) {
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);

            // Notify User
            addNotification($ticket['user_id'], 'ticket_status_updated', json_encode(['key' => 'ticket_status_change_fmt', 'params' => [$new_status], 'param_keys' => [0]]), "user/view_ticket.php?id=$ticket_id");

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

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">

            <!-- Chat Area -->
            <div class="lg:col-span-3 flex flex-col h-[calc(100vh-10rem)]">
                <div
                    class="glass-card flex-1 flex flex-col rounded-[2rem] overflow-hidden border border-white/10 shadow-2xl">
                    <!-- Chat Header -->
                    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-black/20">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h1 class="text-xl font-bold text-white">
                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                </h1>
                                <span class="px-3 py-1 rounded-xl text-[10px] font-bold uppercase border
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
                            <p class="text-xs text-gray-500"><?php echo __('ticket_id'); ?>
                                #<?php echo $ticket['id']; ?> •
                                <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                            </p>
                        </div>

                        <!-- Admin Actions -->
                        <div class="flex gap-2">
                            <form method="POST" class="flex items-center">
                                <select name="status" onchange="this.form.submit()"
                                    class="bg-slate-800 text-white text-xs rounded-xl border-slate-700 py-2 pl-3 pr-8 focus:ring-0">
                                    <?php foreach (['open', 'pending', 'answered', 'solved', 'closed'] as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo $ticket['status'] == $st ? 'selected' : ''; ?>>
                                            <?php echo __('status_' . $st); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>

                            <form method="POST" onsubmit="return confirm('Delete this ticket permanently?');">
                                <button type="submit" name="delete_ticket"
                                    class="w-9 h-9 flex items-center justify-center bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scrollbar" id="messages-container">
                        <?php foreach ($messages as $msg):
                            $isAdmin = $msg['is_admin'];
                            ?>
                            <div class="flex <?php echo $isAdmin ? 'justify-end' : 'justify-start'; ?> animate-fade-in-up">
                                <div
                                    class="flex items-start gap-4 max-w-[85%] <?php echo $isAdmin ? 'flex-row-reverse' : 'flex-row'; ?>">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 rounded-2xl flex items-center justify-center border border-white/10
                                        <?php echo $isAdmin ? 'bg-indigo-600/20 text-indigo-400' : 'bg-slate-700/50 text-slate-300'; ?>">
                                        <?php if ($isAdmin): ?>
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                                                </path>
                                            </svg>
                                        <?php else: ?>
                                            <span
                                                class="text-xs font-bold"><?php echo mb_substr($ticket['user_name'], 0, 3, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="space-y-1">
                                        <div
                                            class="p-5 rounded-3xl text-sm leading-relaxed
                                            <?php echo $isAdmin ? 'bg-indigo-600 text-white rounded-tr-none shadow-lg shadow-indigo-500/10' : 'bg-slate-800 border border-white/5 text-white rounded-tl-none'; ?>">
                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        </div>
                                        <div
                                            class="text-[10px] text-gray-600 px-2 <?php echo $isAdmin ? 'text-right' : 'text-left'; ?>">
                                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Area -->
                    <div class="p-6 border-t border-white/5 bg-black/20">
                        <form method="POST" class="flex gap-4">
                            <textarea name="message" rows="1" required placeholder="<?php echo __('write_reply'); ?>"
                                class="flex-1 bg-slate-900 border border-slate-700 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all resize-none font-medium"></textarea>
                            <button type="submit" name="reply"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 rounded-2xl font-bold shadow-xl shadow-indigo-500/20 transition-all flex items-center justify-center hover:-translate-y-1 active:scale-95">
                                <svg class="w-6 h-6 rtl:rotate-180" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Info Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <div class="glass-card p-6 rounded-3xl border border-white/10 shadow-xl">
                    <h3 class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-6 px-1">
                        <?php echo __('user_info'); ?>
                    </h3>

                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="w-20 h-20 rounded-[2rem] bg-slate-800 p-1 border-2 border-indigo-500/30">
                            <?php if ($ticket['avatar']): ?>
                                <img src="../<?php echo $ticket['avatar']; ?>"
                                    class="w-full h-full object-cover rounded-[1.8rem]">
                            <?php else: ?>
                                <div
                                    class="w-full h-full flex items-center justify-center bg-indigo-500/10 text-indigo-400 text-2xl font-bold rounded-[1.8rem]">
                                    <?php echo mb_substr($ticket['user_name'], 0, 1, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h4 class="text-white font-bold text-lg mb-1">
                                <?php echo htmlspecialchars($ticket['user_name']); ?>
                            </h4>
                            <p class="text-xs text-gray-500 font-medium">
                                <?php echo htmlspecialchars($ticket['email']); ?>
                            </p>
                        </div>

                        <a href="users.php?search=<?php echo urlencode($ticket['email']); ?>"
                            class="w-full py-3 rounded-2xl bg-white/5 border border-white/10 text-xs font-bold text-gray-300 hover:bg-white/10 hover:text-white transition-all">
                            <?php echo __('view_user_profile'); ?>
                        </a>
                    </div>
                </div>
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
</style>

<script>
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>