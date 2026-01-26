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

// Handle Actions (Reply, Close, Delete)
// Handle Actions (Reply, Update Status, Close, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply'])) {
        $message = trim($_POST['message'] ?? '');
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)");
            $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);

            // Update ticket status to answered if currently open or pending
            // Update ticket status to answered if currently open or pending, AND mark as unread for user
            if (in_array($ticket['status'], ['open', 'pending'])) {
                $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'answered', is_read_user = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket_id]);
            } else {
                // If status didn't change (e.g. was already answered/closed? rare), still mark unread for user
                $stmt = $pdo->prepare("UPDATE support_tickets SET is_read_user = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket_id]);
            }

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
$stmt = $pdo->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
$stmt->execute([$ticket_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Chat Area -->
            <div class="lg:col-span-2 flex flex-col h-[calc(100vh-8rem)]">
                <div class="glass-card flex-1 flex flex-col rounded-2xl overflow-hidden">
                    <!-- Chat Header -->
                    <div class="p-6 border-b border-gray-700/50 flex justify-between items-center bg-gray-900/30">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h1 class="text-xl font-bold text-white">
                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                </h1>
                                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase
                                    <?php
                                    if ($ticket['status'] == 'open')
                                        echo 'bg-green-500/20 text-green-400';
                                    elseif ($ticket['status'] == 'answered')
                                        echo 'bg-blue-500/20 text-blue-400';
                                    else
                                        echo 'bg-gray-700 text-gray-400';
                                    ?>">
                                    <?php echo __('status_' . $ticket['status']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 flex gap-2">
                                <span>#<?php echo $ticket['id']; ?></span>
                                <span>•</span>
                                <span><?php echo htmlspecialchars($ticket['user_name']); ?></span>
                            </div>
                        </div>

                        <!-- Admin Status Update -->
                        <div class="flex gap-2">
                            <form method="POST" class="flex items-center gap-2">
                                <select name="status" onchange="this.form.submit()"
                                    class="bg-gray-800 text-white text-xs rounded-lg border-gray-700 focus:ring-indigo-500 focus:border-indigo-500 py-1.5 pl-3 pr-8">
                                    <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>
                                        <?php echo __('status_open'); ?>
                                    </option>
                                    <option value="pending" <?php echo $ticket['status'] == 'pending' ? 'selected' : ''; ?>><?php echo __('status_pending'); ?></option>
                                    <option value="answered" <?php echo $ticket['status'] == 'answered' ? 'selected' : ''; ?>><?php echo __('status_answered'); ?></option>
                                    <option value="solved" <?php echo $ticket['status'] == 'solved' ? 'selected' : ''; ?>>
                                        <?php echo __('status_solved'); ?>
                                    </option>
                                    <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>
                                        <?php echo __('status_closed'); ?>
                                    </option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>

                            <form method="POST" onsubmit="return confirm('Delete this ticket permanently?');">
                                <button type="submit" name="delete_ticket"
                                    class="bg-red-600/20 hover:bg-red-600/40 text-red-400 p-1.5 rounded-lg transition-colors"
                                    title="<?php echo __('delete_ticket'); ?>">
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
                    <div class="flex-1 overflow-y-auto p-6 space-y-6" id="messages-container">
                        <?php foreach ($messages as $msg):
                            $isAdmin = $msg['is_admin'];
                            ?>
                            <div class="flex <?php echo $isAdmin ? 'justify-end' : 'justify-start'; ?>">
                                <div
                                    class="flex items-end gap-3 max-w-[80%] <?php echo $isAdmin ? 'flex-row-reverse' : 'flex-row'; ?>">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center 
                                        <?php echo $isAdmin ? 'bg-indigo-600' : 'bg-gray-700'; ?>">
                                        <?php if ($isAdmin): ?>
                                            <span class="text-xs font-bold text-white">ADM</span>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-white">USR</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Bubble -->
                                    <div
                                        class="p-4 rounded-2xl text-sm leading-relaxed 
                                        <?php echo $isAdmin ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-gray-700 text-white rounded-bl-none'; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <div class="mt-1 text-[10px] opacity-70 text-right">
                                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Area (Always visible for admin usually, but logic same as user) -->
                    <div class="p-4 border-t border-gray-700/50 bg-gray-900/30">
                        <form method="POST" class="flex gap-4">
                            <textarea name="message" rows="1" required placeholder="<?php echo __('write_reply'); ?>..."
                                class="flex-1 bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-all resize-none"></textarea>
                            <button type="submit" name="reply"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-xl font-bold shadow-lg shadow-indigo-500/30 transition-all flex items-center justify-center">
                                <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User & Exchange Info Sidebar -->
            <div class="space-y-6">
                <!-- User Info -->
                <div class="glass-card p-6 rounded-2xl">
                    <h3
                        class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-4 border-b border-gray-700 pb-2">
                        <?php echo __('user'); ?>
                    </h3>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden">
                            <?php if ($ticket['avatar']): ?>
                                <img src="../<?php echo $ticket['avatar']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span
                                    class="text-lg font-bold text-gray-400"><?php echo mb_substr($ticket['user_name'], 0, 1, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="font-bold text-white"><?php echo htmlspecialchars($ticket['user_name']); ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($ticket['email']); ?></div>
                        </div>
                    </div>
                    <a href="users.php?search=<?php echo urlencode($ticket['email']); ?>"
                        class="block mt-4 text-center text-xs border border-gray-600 text-gray-300 py-2 rounded-lg hover:border-white hover:text-white transition-all">
                        <?php echo __('view_user_profile'); ?>
                    </a>
                </div>

                <!-- Related Exchange -->
                <div class="glass-card p-6 rounded-2xl">
                    <h3
                        class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-4 border-b border-gray-700 pb-2">
                        <?php echo __('related_exchange'); ?>
                    </h3>

                    <?php if ($ticket['exchange_id']):
                        $stmt = $pdo->prepare("SELECT e.*, c1.symbol as from_sym, c2.symbol as to_sym,
                                            pm1.name as send_method_name, pm1.name_ar as send_method_name_ar,
                                            pm2.name as receive_method_name, pm2.name_ar as receive_method_name_ar
                                            FROM exchanges e 
                                            LEFT JOIN currencies c1 ON e.currency_from_id = c1.id 
                                            LEFT JOIN currencies c2 ON e.currency_to_id = c2.id 
                                            LEFT JOIN payment_methods pm1 ON e.payment_method_send_id = pm1.id
                                            LEFT JOIN payment_methods pm2 ON e.payment_method_receive_id = pm2.id
                                            WHERE e.id = ?");
                        $stmt->execute([$ticket['exchange_id']]);
                        $related = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <?php if ($related): ?>
                            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-indigo-400 font-mono text-sm">#<?php echo $related['id']; ?></span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-300">
                                        <?php echo __('status_' . $related['status']); ?>
                                    </span>
                                </div>
                                <div class="text-white font-medium text-sm">
                                    <?php echo $related['amount_send'] . ' ' . $related['from_sym']; ?>
                                    <?php if (!empty($related['send_method_name'])): ?>
                                        <span class="text-xs text-indigo-400 block">
                                            Via:
                                            <?php echo $lang === 'ar' && !empty($related['send_method_name_ar']) ? $related['send_method_name_ar'] : $related['send_method_name']; ?>
                                        </span>
                                    <?php endif; ?>

                                    <div class="text-gray-500 my-1 text-center">↓</div>

                                    <?php echo $related['amount_receive'] . ' ' . $related['to_sym']; ?>
                                    <?php if (!empty($related['receive_method_name'])): ?>
                                        <span class="text-xs text-green-400 block">
                                            Via:
                                            <?php echo $lang === 'ar' && !empty($related['receive_method_name_ar']) ? $related['receive_method_name_ar'] : $related['receive_method_name']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="exchanges.php?search=<?php echo $related['id']; ?>"
                                    class="block mt-3 text-center text-xs bg-indigo-600/20 text-indigo-300 py-2 rounded-lg hover:bg-indigo-600 hover:text-white transition-all">
                                    <?php echo __('manage_exchange'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm italic"><?php echo __('general_inquiry'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Auto scroll to bottom
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>