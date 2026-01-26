<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Fetch New Stats for SaaS System
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'active_subs' => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn(),
    'fb_accounts' => $pdo->query("SELECT COUNT(*) FROM fb_accounts WHERE is_active=1")->fetchColumn(),
    'campaigns' => $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn(),
    'support_open' => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 min-w-0 p-4 md:p-8">
        <h1 class="text-3xl font-bold mb-8"><?php echo __('overview'); ?></h1>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Active Subscriptions -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-indigo-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1">Active Subscriptions</div>
                <div class="text-3xl font-bold text-indigo-400">
                    <?php echo $stats['active_subs']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Paid & Trial Users</div>
            </div>

            <!-- Total Users -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-blue-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1"><?php echo __('users'); ?></div>
                <div class="text-3xl font-bold text-white">
                    <?php echo $stats['users']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Registered Members</div>
            </div>

            <!-- FB Accounts -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-blue-400">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1">Connected FB Accounts</div>
                <div class="text-3xl font-bold text-blue-400">
                    <?php echo $stats['fb_accounts']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Total Linked Profiles</div>
            </div>

            <!-- Campaigns Run -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-purple-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1">Campaigns Launched</div>
                <div class="text-3xl font-bold text-purple-400">
                    <?php echo $stats['campaigns']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Active Automation</div>
            </div>

            <!-- Open Tickets -->
            <div class="glass-card p-6 rounded-2xl border-l-4 border-yellow-500">
                <div class="text-gray-400 text-sm font-medium uppercase mb-1">Open Tickets</div>
                <div class="text-3xl font-bold text-yellow-400">
                    <?php echo $stats['support_open']; ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Needs Attention</div>
            </div>
        </div>

        <!-- Recent Campaigns (instead of Exchanges) -->
        <div class="glass-card rounded-2xl p-6 overflow-hidden mb-8">
            <h2 class="text-xl font-bold mb-4">Recent Campaigns</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-3 pl-2">ID</th>
                            <th class="pb-3">User</th>
                            <th class="pb-3">Campaign Name</th>
                            <th class="pb-3">Leads</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        // Check if campaigns table exists (it should, but just safe query)
                        $stmt = $pdo->query("SELECT c.*, u.name as user_name 
                                            FROM campaigns c 
                                            LEFT JOIN users u ON c.user_id = u.id 
                                            ORDER BY c.created_at DESC LIMIT 5");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="py-4 pl-2 font-mono text-sm text-gray-500">#<?php echo $row['id']; ?></td>
                                <td class="py-4 font-medium">
                                    <?php echo htmlspecialchars($row['user_name'] ?? 'Unknown'); ?>
                                </td>
                                <td class="py-4 text-white">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>
                                <td class="py-4 text-gray-300">
                                    <?php echo number_format($row['total_leads']); ?>
                                </td>
                                <td class="py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php
                                    if ($row['status'] == 'completed')
                                        echo 'bg-green-500/20 text-green-400';
                                    elseif ($row['status'] == 'running')
                                        echo 'bg-blue-500/20 text-blue-400 animate-pulse';
                                    elseif ($row['status'] == 'scheduled')
                                        echo 'bg-yellow-500/20 text-yellow-400';
                                    else
                                        echo 'bg-gray-500/20 text-gray-400';
                                    ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-500 text-sm">
                                    <?php echo date('M d, H:i', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php if ($stmt->rowCount() == 0): ?>
                    <p class="text-center text-gray-500 py-4">No campaigns found yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="glass-card rounded-2xl p-6 overflow-hidden">
            <h2 class="text-xl font-bold mb-4">Newest Users</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-3 pl-2">User</th>
                            <th class="pb-3">Email</th>
                            <th class="pb-3">Joined</th>
                            <th class="pb-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        $stmtUsers = $pdo->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5");
                        while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-gray-800/30">
                                <td class="py-3 pl-2 font-medium"><?php echo htmlspecialchars($u['name']); ?></td>
                                <td class="py-3 text-gray-400 text-sm"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="py-3 text-gray-500 text-sm">
                                    <?php echo date('M d', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="py-3">
                                    <a href="users.php?edit=<?php echo $u['id']; ?>"
                                        class="text-indigo-400 hover:text-white text-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>