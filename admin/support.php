<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold">
                <?php echo __('support_tickets'); ?>
            </h1>
        </div>

        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="p-4">
                                <?php echo __('ticket_id'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('user'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('subject'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('status'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('last_update'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('actions'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        // Fetch all tickets with user names
                        $stmt = $pdo->query("SELECT t.*, u.name as user_name, u.email 
                                           FROM support_tickets t 
                                           LEFT JOIN users u ON t.user_id = u.id 
                                           ORDER BY 
                                            CASE WHEN t.status = 'open' THEN 1 
                                                 WHEN t.status = 'answered' THEN 2 
                                                 ELSE 3 
                                            END, 
                                            t.updated_at DESC");
                        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($tickets) > 0):
                            foreach ($tickets as $ticket):
                                ?>
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="p-4 font-mono text-sm text-gray-500">#
                                        <?php echo $ticket['id']; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-white">
                                            <?php echo htmlspecialchars($ticket['user_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($ticket['email']); ?>
                                        </div>
                                    </td>
                                    <td class="p-4 font-medium text-gray-300">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase
                                            <?php
                                            if ($ticket['status'] == 'open')
                                                echo 'bg-green-500/20 text-green-400 animate-pulse';
                                            elseif ($ticket['status'] == 'answered')
                                                echo 'bg-blue-500/20 text-blue-400';
                                            else
                                                echo 'bg-gray-700 text-gray-400';
                                            ?>">
                                            <?php echo __('status_' . $ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-sm text-gray-500">
                                        <?php echo date('M d, H:i', strtotime($ticket['updated_at'])); ?>
                                    </td>
                                    <td class="p-4">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                            class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg transition-colors shadow-lg shadow-indigo-500/30">
                                            <?php echo __('view_details'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500">
                                    <?php echo __('no_tickets'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>