<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $exchange_id = !empty($_POST['exchange_id']) ? (int) $_POST['exchange_id'] : null;

    if (empty($subject) || empty($message)) {
        $error = $lang === 'ar' ? 'الموضوع والرسالة مطلوبان' : 'Subject and message are required.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert Ticket with campaign_id
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, campaign_id, subject, status) VALUES (?, ?, ?, 'open')");
            $stmt->execute([$user_id, $campaign_id, $subject]);
            $ticket_id = $pdo->lastInsertId();

            // Insert Initial Message
            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
            $stmt->execute([$ticket_id, $user_id, $message]);

            // Notify Admins
            notifyAdmins(__('new_ticket') . " #$ticket_id", __('ticket_created_by') . " " . $_SESSION['user_name'], "admin/view_ticket.php?id=$ticket_id");

            $pdo->commit();
            header("Location: view_ticket.php?id=$ticket_id&new=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = ($lang === 'ar' ? 'فشل في إنشاء التذكرة: ' : 'Failed to create ticket: ') . $e->getMessage();
        }
    }
}

// Fetch User's Recent Campaigns for Dropdown
$stmt = $pdo->prepare("SELECT id, campaign_name as name, status, created_at 
                       FROM campaigns 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$user_id]);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">
                <?php echo __('create_ticket'); ?>
            </h1>

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="glass-card p-8 rounded-2xl">
                <form method="POST" class="space-y-6">

                    <!-- Subject -->
                    <div>
                        <label class="block text-gray-400 text-sm font-bold mb-2">
                            <?php echo __('subject'); ?>
                        </label>
                        <input type="text" name="subject" required
                            class="w-full bg-gray-900/50 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Related Campaign (Optional) -->
                    <div>
                        <label class="block text-gray-400 text-sm font-bold mb-2">
                            <?php echo __('related_campaign'); ?>
                        </label>
                        <select name="campaign_id"
                            class="w-full bg-gray-900/50 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-all">
                            <option value="">
                                <?php echo __('general_inquiry'); ?>
                            </option>
                            <?php foreach ($campaigns as $camp): ?>
                                <option value="<?php echo $camp['id']; ?>">
                                    #<?php echo $camp['id']; ?> - <?php echo htmlspecialchars($camp['name']); ?>
                                    (<?php echo $camp['status']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php echo __('select_campaign_hint') ?? 'Select the campaign related to this ticket (optional).'; ?>
                        </p>
                    </div>

                    <!-- Message -->
                    <div>
                        <label class="block text-gray-400 text-sm font-bold mb-2">
                            <?php echo __('your_message'); ?>
                        </label>
                        <textarea name="message" rows="6" required
                            class="w-full bg-gray-900/50 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 transition-all"></textarea>
                    </div>

                    <div class="flex justify-end gap-4 pt-4">
                        <a href="support.php"
                            class="px-6 py-3 rounded-xl text-gray-400 hover:text-white font-bold transition-colors">
                            <?php echo __('cancel'); ?>
                        </a>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 font-bold transition-all">
                            <?php echo __('submit'); ?>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>