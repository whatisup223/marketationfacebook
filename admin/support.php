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
        <div class="flex items-center justify-between mb-8 max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold">
                <?php echo __('support_tickets'); ?>
            </h1>
        </div>

        <div class="glass-card rounded-[2rem] overflow-hidden border border-white/10 shadow-2xl max-w-7xl mx-auto">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-white/5 bg-white/5">
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('ticket_id'); ?>
                            </th>
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('user'); ?>
                            </th>
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('subject'); ?>
                            </th>
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('status'); ?>
                            </th>
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('last_update'); ?>
                            </th>
                            <th class="px-6 py-5 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('actions'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
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
                                <tr class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-5 font-mono text-xs text-gray-500">
                                        #<?php echo $ticket['id']; ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="font-bold text-white"><?php echo htmlspecialchars($ticket['user_name']); ?>
                                        </div>
                                        <div class="text-[10px] text-gray-500"><?php echo htmlspecialchars($ticket['email']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 font-medium text-gray-300">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="px-3 py-1 rounded-xl text-[10px] font-bold uppercase border
                                            <?php
                                            if ($ticket['status'] == 'open')
                                                echo 'bg-green-500/10 text-green-400 border-green-500/20 animate-pulse';
                                            elseif ($ticket['status'] == 'answered')
                                                echo 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                            elseif ($ticket['status'] == 'pending')
                                                echo 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
                                            elseif ($ticket['status'] == 'solved')
                                                echo 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
                                            else
                                                echo 'bg-gray-700/20 text-gray-400 border-gray-600/30';
                                            ?>">
                                            <?php echo __('status_' . $ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-gray-500">
                                        <?php echo date('M d, H:i', strtotime($ticket['updated_at'])); ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-4 py-2 rounded-xl transition-all shadow-lg shadow-indigo-500/20">
                                            <span><?php echo __('view_details'); ?></span>
                                            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-20 text-center">
                                    <div class="flex flex-col items-center gap-4 text-gray-600">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="font-medium"><?php echo __('no_tickets'); ?></p>
                                    </div>
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