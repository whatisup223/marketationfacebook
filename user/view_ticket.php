<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['id'] ?? 0;

// Fetch Ticket + Check Owner
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
$stmt->execute([$ticket_id, $user_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header("Location: support.php");
    exit;
}

// Mark as read by user
if ($ticket['is_read_user'] == 0) {
    $stmt = $pdo->prepare("UPDATE support_tickets SET is_read_user = 1 WHERE id = ?");
    $stmt->execute([$ticket_id]);
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message) && !in_array($ticket['status'], ['closed', 'solved'])) {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
        $stmt->execute([$ticket_id, $user_id, $message]);

        // Update ticket status/timestamp and mark as unread for admin
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'open', is_read_admin = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Notify Admins - User replied to ticket
        notifyAdmins('user_ticket_reply', json_encode(['key' => 'user_replied_ticket', 'params' => ["#$ticket_id", $_SESSION['user_name']]]), "admin/view_ticket.php?id=$ticket_id");

        header("Location: view_ticket.php?id=$ticket_id");
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
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header with Back Button -->
            <div class="flex items-center justify-between bg-white/5 p-4 rounded-2xl border border-white/10">
                <div class="flex items-center gap-4">
                    <a href="support.php"
                        class="p-2 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 transition-all">
                        <svg class="w-5 h-5 <?php echo $lang == 'ar' ? 'rotate-180' : ''; ?>" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white"><?php echo htmlspecialchars($ticket['subject']); ?>
                        </h1>
                        <p class="text-xs text-gray-500"><?php echo __('ticket_no'); ?><?php echo $ticket['id']; ?> •
                            <?php echo __('created_on'); ?>
                            <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?>
                        </p>
                    </div>
                </div>
                <span class="px-4 py-1.5 rounded-xl text-xs font-bold uppercase shadow-lg shadow-indigo-500/10
                    <?php
                    if ($ticket['status'] == 'open')
                        echo 'bg-green-500/20 text-green-400 border border-green-500/30';
                    elseif ($ticket['status'] == 'answered')
                        echo 'bg-blue-500/20 text-blue-400 border border-blue-500/30';
                    elseif ($ticket['status'] == 'pending')
                        echo 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
                    elseif ($ticket['status'] == 'solved')
                        echo 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30';
                    else
                        echo 'bg-gray-700 text-gray-400 border border-gray-600';
                    ?>">
                    <?php echo __('status_' . $ticket['status']); ?>
                </span>
            </div>

            <!-- Chat Area -->
            <div
                class="glass-card flex flex-col rounded-[2rem] overflow-hidden border border-white/10 shadow-2xl h-[calc(100vh-16rem)]">
                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-6 md:p-10 space-y-8 custom-scrollbar" id="messages-container">
                    <?php foreach ($messages as $msg):
                        $isAdmin = $msg['is_admin'];
                        ?>
                        <div class="flex <?php echo $isAdmin ? 'justify-start' : 'justify-end'; ?> animate-fade-in-up">
                            <div
                                class="flex items-start gap-4 max-w-[85%] <?php echo $isAdmin ? 'flex-row' : 'flex-row-reverse'; ?>">
                                <!-- Avatar Style Shadow-less -->
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
                                        <span class="text-[10px] font-bold"><?php echo __('me'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Bubble -->
                                <div class="space-y-1">
                                    <div
                                        class="p-5 rounded-3xl text-sm leading-relaxed shadow-sm
                                        <?php echo $isAdmin ? 'bg-indigo-900/30 border border-indigo-500/20 text-indigo-100 rounded-tl-none' : 'bg-slate-800 border border-white/5 text-white rounded-tr-none'; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                    <div
                                        class="text-[10px] text-gray-600 px-2 <?php echo $isAdmin ? 'text-left' : 'text-right'; ?>">
                                        <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Area -->
                <div class="p-6 border-t border-white/5 bg-black/20">
                    <?php if (in_array($ticket['status'], ['closed', 'solved'])): ?>
                        <div
                            class="text-center p-6 text-gray-500 bg-white/5 rounded-2xl border border-white/10 border-dashed">
                            <span class="flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <?php echo $ticket['status'] == 'solved' ? __('ticket_solved_msg') : __('ticket_closed_msg'); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="flex gap-4">
                            <textarea name="message" rows="1" required placeholder="<?php echo __('write_reply'); ?>"
                                class="flex-1 bg-slate-900 border border-slate-700 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all resize-none font-medium"></textarea>
                            <button type="submit" name="reply"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 rounded-2xl font-bold shadow-xl shadow-indigo-500/20 transition-all flex items-center justify-center hover:-translate-y-1 active:scale-95">
                                <svg class="w-6 h-6 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
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