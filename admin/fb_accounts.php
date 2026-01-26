<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM fb_accounts WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: fb_accounts.php?msg=deleted");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <h1 class="text-3xl font-bold mb-8">All Facebook Accounts</h1>

        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-800/50 text-gray-300 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold">User</th>
                            <th class="px-6 py-4 font-bold">Account Name</th>
                            <th class="px-6 py-4 font-bold">FB ID</th>
                            <th class="px-6 py-4 font-bold">Status</th>
                            <th class="px-6 py-4 font-bold">Added Date</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        $stmt = $pdo->query("SELECT f.*, u.name as user_name, u.email as user_email 
                                            FROM fb_accounts f 
                                            JOIN users u ON f.user_id = u.id 
                                            ORDER BY f.created_at DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-gray-800/30">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-white">
                                        <?php echo htmlspecialchars($row['user_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($row['user_email']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-white">
                                    <?php echo htmlspecialchars($row['fb_name']); ?>
                                </td>
                                <td class="px-6 py-4 font-mono text-sm text-gray-400">
                                    <?php echo htmlspecialchars($row['fb_id'] ?: 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($row['is_active']): ?>
                                        <span
                                            class="px-2 py-1 rounded text-[10px] font-bold bg-green-500/10 text-green-500 uppercase">Active</span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-500 uppercase">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-500 text-sm">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="?delete=<?php echo $row['id']; ?>"
                                        onclick="return confirm('Delete this account?');"
                                        class="text-red-400 hover:text-red-300 text-sm font-medium">
                                        Delete
                                    </a>
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