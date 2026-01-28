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

    if (empty($subject) || empty($message)) {
        $error = __('subject_message_required');
    } else {
        try {
            $pdo->beginTransaction();

            // Insert Simple Ticket (No relations for now)
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'open')");
            $stmt->execute([$user_id, $subject]);
            $ticket_id = $pdo->lastInsertId();

            // Insert Initial Message
            $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
            $stmt->execute([$ticket_id, $user_id, $message]);

            // Notify Admins - Use translation keys instead of direct text
            notifyAdmins('new_ticket', json_encode(['key' => 'new_ticket_notification', 'params' => ["#$ticket_id", $_SESSION['user_name']]]), "admin/view_ticket.php?id=$ticket_id");

            // Send Email to Admin
            require_once __DIR__ . '/../includes/MailService.php';
            require_once __DIR__ . '/../includes/email_templates.php';

            $adminEmail = getSetting('contact_email');
            if ($adminEmail) {
                $emailData = [
                    'ticket_id' => $ticket_id,
                    'user_name' => $_SESSION['user_name'],
                    'subject' => $subject,
                    'priority' => __('normal'),
                    'message' => $message,
                    'admin_url' => getSetting('site_url') . "/admin/view_ticket.php?id=$ticket_id"
                ];
                $tpl = getEmailTemplate('new_ticket_admin', $emailData);
                sendEmail($adminEmail, $tpl['subject'], $tpl['body']);
            }

            $pdo->commit();
            header("Location: view_ticket.php?id=$ticket_id&new=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = __('error_network') . ': ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center gap-4 mb-8">
                <a href="support.php"
                    class="p-2 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 transition-all">
                    <svg class="w-5 h-5 <?php echo $lang == 'ar' ? 'rotate-180' : ''; ?>" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-3xl font-bold"><?php echo __('create_ticket'); ?></h1>
            </div>

            <?php if ($error): ?>
                <div
                    class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="glass-card p-8 rounded-[2rem] border border-white/10 shadow-2xl">
                <form method="POST" class="space-y-6">

                    <!-- Subject -->
                    <div class="space-y-2">
                        <label class="block text-gray-400 text-sm font-bold ml-1">
                            <?php echo __('subject'); ?>
                        </label>
                        <input type="text" name="subject" required
                            placeholder="<?php echo __('subject_placeholder'); ?>"
                            class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all placeholder:text-gray-600 font-medium">
                    </div>

                    <!-- Message -->
                    <div class="space-y-2">
                        <label class="block text-gray-400 text-sm font-bold ml-1">
                            <?php echo __('your_message'); ?>
                        </label>
                        <textarea name="message" rows="6" required
                            placeholder="<?php echo __('message_placeholder'); ?>"
                            class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all placeholder:text-gray-600 font-medium"></textarea>
                    </div>

                    <div class="flex justify-end gap-4 pt-4">
                        <button type="submit"
                            class="group relative bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white px-10 py-4 rounded-2xl shadow-xl shadow-indigo-500/20 font-bold transition-all hover:-translate-y-1 flex items-center gap-2 overflow-hidden">
                            <div
                                class="absolute inset-0 bg-white/20 blur-xl group-hover:opacity-100 opacity-0 transition-opacity duration-500">
                            </div>
                            <span class="relative z-10"><?php echo __('submit'); ?></span>
                            <svg class="w-5 h-5 relative z-10 group-hover:translate-x-1 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>