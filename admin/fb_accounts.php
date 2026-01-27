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
        <h1 class="text-3xl font-bold mb-8"><?php echo __('all_fb_accounts'); ?></h1>

        <div class="glass-card rounded-2xl overflow-hidden shadow-2xl border border-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-white/5 text-gray-400 text-xs uppercase tracking-wider">
                            <th class="px-6 py-5 font-bold"><?php echo __('user'); ?></th>
                            <th class="px-6 py-5 font-bold"><?php echo __('account_name'); ?></th>
                            <th class="px-6 py-5 font-bold"><?php echo __('fb_id_label'); ?></th>
                            <th class="px-6 py-5 font-bold"><?php echo __('status'); ?></th>
                            <th class="px-6 py-5 font-bold"><?php echo __('added_date'); ?></th>
                            <th class="px-6 py-5 font-bold text-right"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php
                        $stmt = $pdo->query("SELECT f.*, u.name as user_name, u.email as user_email 
                                            FROM fb_accounts f 
                                            JOIN users u ON f.user_id = u.id 
                                            ORDER BY f.created_at DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-5">
                                    <div class="font-bold text-white">
                                        <?php echo htmlspecialchars($row['user_name']); ?>
                                    </div>
                                    <div class="text-[10px] text-gray-500">
                                        <?php echo htmlspecialchars($row['user_email']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-gray-300 font-medium">
                                    <?php echo htmlspecialchars($row['fb_name']); ?>
                                </td>
                                <td class="px-6 py-5 font-mono text-xs text-gray-500">
                                    <?php echo htmlspecialchars($row['fb_id'] ?: __('no_data')); ?>
                                </td>
                                <td class="px-6 py-5">
                                    <?php if ($row['is_active']): ?>
                                        <span
                                            class="px-3 py-1 rounded-xl text-[10px] font-bold bg-green-500/10 text-green-400 border border-green-500/20 uppercase"><?php echo __('status_active'); ?></span>
                                    <?php else: ?>
                                        <span
                                            class="px-3 py-1 rounded-xl text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20 uppercase"><?php echo __('status_inactive'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-5 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <a href="?delete=<?php echo $row['id']; ?>"
                                        onclick="return confirm('<?php echo __('delete_account_confirm'); ?>');"
                                        class="inline-flex items-center gap-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white text-xs px-4 py-2 rounded-xl border border-red-500/20 transition-all font-bold">
                                        <?php echo __('delete'); ?>
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