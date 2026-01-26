<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold">
                <?php echo __('support_tickets'); ?>
            </h1>
            <a href="create_ticket.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all font-medium flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <?php echo __('create_ticket'); ?>
            </a>
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
                                <?php echo __('subject'); ?>
                            </th>
                            <th class="p-4">
                                <?php echo __('related_exchange'); ?>
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
                        $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC");
                        $stmt->execute([$user_id]);
                        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($tickets) > 0):
                            foreach ($tickets as $ticket):
                                ?>
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="p-4 font-mono text-sm text-gray-500">#
                                        <?php echo $ticket['id']; ?>
                                    </td>
                                    <td class="p-4 font-bold text-white">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </td>
                                    <td class="p-4">
                                        <?php if ($ticket['exchange_id']): ?>
                                            <a href="view_exchange.php?id=<?php echo $ticket['exchange_id']; ?>"
                                                class="text-indigo-400 hover:text-indigo-300 underline">
                                                #
                                                <?php echo $ticket['exchange_id']; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">
                                                <?php echo __('general_inquiry'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase
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
                                    </td>
                                    <td class="p-4 text-sm text-gray-500">
                                        <?php echo date('M d, H:i', strtotime($ticket['updated_at'])); ?>
                                    </td>
                                    <td class="p-4">
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                            class="inline-block bg-gray-800 hover:bg-gray-700 text-white text-xs px-3 py-1.5 rounded-lg border border-gray-600 transition-colors">
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