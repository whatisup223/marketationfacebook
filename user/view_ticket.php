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

        // Update ticket status/timestamp
        // Update ticket status/timestamp and mark as unread for admin
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'open', is_read_admin = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);

        // Notify Admins
        notifyAdmins('new_reply_ticket', json_encode(['key' => 'user_replied_fmt', 'params' => [$ticket['subject']]]), "admin/view_ticket.php?id=$ticket_id");

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
                                    elseif ($ticket['status'] == 'pending')
                                        echo 'bg-yellow-500/20 text-yellow-400';
                                    elseif ($ticket['status'] == 'solved')
                                        echo 'bg-indigo-500/20 text-indigo-400';
                                    else
                                        echo 'bg-gray-700 text-gray-400';
                                    ?>">
                                    <?php echo __('status_' . $ticket['status']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 flex gap-2">
                                <span>#
                                    <?php echo $ticket['id']; ?>
                                </span>
                                <span>•</span>
                                <span>
                                    <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6" id="messages-container">
                        <?php foreach ($messages as $msg):
                            $isAdmin = $msg['is_admin'];
                            ?>
                            <div class="flex <?php echo $isAdmin ? 'justify-start' : 'justify-end'; ?>">
                                <div
                                    class="flex items-end gap-3 max-w-[80%] <?php echo $isAdmin ? 'flex-row' : 'flex-row-reverse'; ?>">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center 
                                        <?php echo $isAdmin ? 'bg-indigo-600' : 'bg-gray-700'; ?>">
                                        <?php if ($isAdmin): ?>
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                                                </path>
                                            </svg>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-white">ME</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Bubble -->
                                    <div
                                        class="p-4 rounded-2xl text-sm leading-relaxed 
                                        <?php echo $isAdmin ? 'bg-indigo-900/40 border border-indigo-500/20 text-indigo-100 rounded-bl-none' : 'bg-gray-700 text-white rounded-br-none'; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <div class="mt-1 text-[10px] opacity-50 text-right">
                                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Area -->
                    <div class="p-4 border-t border-gray-700/50 bg-gray-900/30">
                        <?php if (in_array($ticket['status'], ['closed', 'solved'])): ?>
                            <div
                                class="text-center p-4 text-gray-500 bg-gray-800/50 rounded-xl border border-gray-700 border-dashed">
                                <?php echo $ticket['status'] == 'solved' ? __('ticket_solved_msg') : __('ticket_closed_msg'); ?>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="flex gap-4">
                                <textarea name="message" rows="1" required placeholder="<?php echo __('write_reply'); ?>"
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Details Sidebar -->
            <div class="space-y-6">
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
                                    <span class="text-indigo-400 font-mono text-sm">#
                                        <?php echo $related['id']; ?>
                                    </span>
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
                                <a href="view_exchange.php?id=<?php echo $related['id']; ?>"
                                    class="block mt-3 text-center text-xs bg-indigo-600/20 text-indigo-300 py-2 rounded-lg hover:bg-indigo-600 hover:text-white transition-all">
                                    <?php echo __('view_details'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm italic">
                            <?php echo __('general_inquiry'); ?>
                        </p>
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