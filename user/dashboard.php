<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch User Stats
$stats = [
    'connected_accounts' => $pdo->query("SELECT COUNT(*) FROM fb_accounts WHERE user_id = $user_id AND is_active=1")->fetchColumn(),
    'tasks_total' => $pdo->query("SELECT COUNT(*) FROM extraction_tasks WHERE user_id = $user_id")->fetchColumn(),
    'leads_total' => $pdo->query("SELECT SUM(total_leads) FROM extraction_tasks WHERE user_id = $user_id")->fetchColumn() ?: 0,
    'tasks_running' => $pdo->query("SELECT COUNT(*) FROM extraction_tasks WHERE user_id = $user_id AND status IN ('pending', 'processing')")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold"><?php echo __('overview'); ?></h1>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Connected Accounts -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-blue-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1"><?php echo __('fb_accounts'); ?></div>
                <div class="text-3xl font-bold text-blue-400">
                    <?php echo $stats['connected_accounts']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2"><?php echo __('active_linked_accounts'); ?></div>
            </div>

            <!-- Profile Completion (placeholder for something useful) -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-indigo-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1"><?php echo __('account_status'); ?></div>
                <div class="text-3xl font-bold text-white"><?php echo __('verified'); ?></div>
                <div class="text-xs text-gray-500 mt-2"><?php echo __('active_membership'); ?></div>
            </div>

            <!-- Support Tickets -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1"><?php echo __('support_tickets'); ?></div>
                <div class="text-3xl font-bold text-yellow-500">
                    <?php echo getUserTicketUnreadCount($_SESSION['user_id']); ?>
                </div>
                <div class="text-xs text-gray-500 mt-2"><?php echo __('unread_responses'); ?></div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>