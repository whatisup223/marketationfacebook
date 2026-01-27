<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Handle Delete Ticket
if (isset($_POST['delete_ticket'])) {
    $tid = $_POST['ticket_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
    $stmt->execute([$tid]);
    header("Location: support.php?deleted=1");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{ showDeleteModal: false, ticketToDelete: null }">
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
                                        <div class="text-[10px] text-gray-600 uppercase font-bold mb-1"><?php echo __('last_update'); ?>:</div>
                                        <?php echo date('M d, H:i', strtotime($ticket['updated_at'])); ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-2">
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                                class="inline-flex items-center gap-2 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-500 hover:text-white text-xs px-4 py-2 rounded-xl border border-indigo-500/20 transition-all font-bold">
                                                <span><?php echo __('view'); ?></span>
                                            </a>
                                            <button @click="ticketToDelete = <?php echo $ticket['id']; ?>; showDeleteModal = true"
                                                class="p-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition-all border border-red-500/20"
                                                title="<?php echo __('delete'); ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
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

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="display: none;">

        <div class="glass-card w-full max-w-md rounded-[2.5rem] p-8 border border-white/10 shadow-2xl transform transition-all"
            @click.away="showDeleteModal = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <div
                class="w-16 h-16 bg-red-500/10 rounded-2xl flex items-center justify-center text-red-500 mx-auto mb-6 border border-red-500/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>

            <h3 class="text-xl font-bold text-white text-center mb-2"><?php echo __('confirm_delete'); ?></h3>
            <p class="text-gray-400 text-center text-sm mb-8 leading-relaxed"><?php echo __('confirm_delete_ticket'); ?>
            </p>

            <div class="flex gap-4">
                <button @click="showDeleteModal = false"
                    class="flex-1 py-4 rounded-2xl bg-white/5 border border-white/10 text-sm font-bold text-gray-400 hover:bg-white/10 hover:text-white transition-all">
                    <?php echo __('cancel'); ?>
                </button>
                <form method="POST" class="flex-1">
                    <input type="hidden" name="ticket_id" :value="ticketToDelete">
                    <button type="submit" name="delete_ticket"
                        class="w-full py-4 rounded-2xl bg-red-600 hover:bg-red-500 text-white text-sm font-bold shadow-xl shadow-red-500/20 transition-all hover:-translate-y-1">
                        <?php echo __('confirm_delete'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>