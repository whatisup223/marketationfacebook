<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Handle Actions
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = $_POST['id'];

    // Mark as read whenever an action is taken
    markExchangeAsRead($id);

    if ($_POST['action'] == 'delete') {
        // Delete the exchange permanently
        $stmt = $pdo->prepare("DELETE FROM exchanges WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Update status (approve or reject)
        $status = $_POST['action'] == 'approve' ? 'completed' : 'cancelled';
        $stmt = $pdo->prepare("UPDATE exchanges SET status = ? WHERE id = ?");

        if ($stmt->execute([$status, $id])) {
            // --- Email Notification ---
            require_once __DIR__ . '/../includes/MailService.php';
            require_once __DIR__ . '/../includes/email_templates.php';

            if (getSetting('notify_exchange_status_user', '1') == '1') {
                // Fetch User Info & Exchange Info
                $stmtInfo = $pdo->prepare("SELECT e.user_id, u.name, u.email, u.preferences FROM exchanges e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $stmtInfo->execute([$id]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

                if ($info) {
                    // Check User Preference
                    $user_prefs = json_decode($info['preferences'] ?? '{}', true);
                    if ($user_prefs['notify_exchange_status'] ?? true) { // Default true if not set
                        $data = [
                            'id' => $id,
                            'name' => $info['name'],
                            'status' => $status,
                            'view_url' => getSetting('site_url') . "/user/view_exchange.php?id=$id"
                        ];
                        $tpl = getEmailTemplate('exchange_status_update', $data);
                        sendEmail($info['email'], $tpl['subject'], $tpl['body']);
                    }

                    // --- Internal Notification to User ---
                    $msgKey = $status == 'completed' ? 'exchange_approved_msg' : 'exchange_rejected_msg';
                    addNotification(
                        $info['user_id'],
                        'exchange_status_title',
                        json_encode(['key' => $msgKey, 'params' => [$id]]),
                        'user/view_exchange.php?id=' . $id
                    );
                    // -------------------------------------
                }
            }
            // --------------------------
        }
    }

    // Redirect to avoid resubmission
    header("Location: exchanges.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <h1 class="text-3xl font-bold mb-8"><?php echo __('manage_exchanges'); ?></h1>

        <div class="glass-card rounded-2xl p-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-3 pl-2"><?php echo __('id'); ?></th>
                            <th class="pb-3"><?php echo __('user'); ?></th>
                            <th class="pb-3"><?php echo __('send_user'); ?></th>
                            <th class="pb-3"><?php echo __('receive_user'); ?></th>
                            <th class="pb-3"><?php echo __('proof'); ?></th>
                            <th class="pb-3"><?php echo __('wallet'); ?></th>
                            <th class="pb-3"><?php echo __('status'); ?></th>
                            <th class="pb-3"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        $stmt = $pdo->query("SELECT e.*, u.name as user_name, c1.symbol as from_sym, c2.symbol as to_sym,
                                            pm1.name as send_method_name, pm1.name_ar as send_method_name_ar,
                                            pm2.name as receive_method_name, pm2.name_ar as receive_method_name_ar
                                            FROM exchanges e 
                                            LEFT JOIN users u ON e.user_id = u.id 
                                            LEFT JOIN currencies c1 ON e.currency_from_id = c1.id 
                                            LEFT JOIN currencies c2 ON e.currency_to_id = c2.id 
                                            LEFT JOIN payment_methods pm1 ON e.payment_method_send_id = pm1.id
                                            LEFT JOIN payment_methods pm2 ON e.payment_method_receive_id = pm2.id
                                            ORDER BY FIELD(e.status, 'pending', 'completed', 'cancelled'), e.created_at DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $sendMethod = $lang === 'ar' && !empty($row['send_method_name_ar']) ? $row['send_method_name_ar'] : $row['send_method_name'];
                            $recMethod = $lang === 'ar' && !empty($row['receive_method_name_ar']) ? $row['receive_method_name_ar'] : $row['receive_method_name'];
                            ?>
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="py-4 pl-2 font-mono text-sm text-gray-500">#
                                    <?php echo $row['id']; ?>
                                </td>
                                <td class="py-4 text-sm">
                                    <?php echo htmlspecialchars($row['user_name'] ?? __('guest')); ?>
                                </td>
                                <td class="py-4 text-red-300 text-sm">
                                    <?php echo $row['amount_send'] . ' ' . $row['from_sym']; ?>
                                </td>
                                <td class="py-4 text-green-300 text-sm">
                                    <?php echo $row['amount_receive'] . ' ' . $row['to_sym']; ?>
                                </td>
                                <td class="py-4">
                                    <?php if ($row['transaction_proof']): ?>
                                        <button
                                            onclick="showProofModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['user_name'] ?? __('guest')); ?>', '<?php echo $row['amount_send'] . ' ' . $row['from_sym']; ?>', '<?php echo $row['amount_receive'] . ' ' . $row['to_sym']; ?>', '<?php echo htmlspecialchars($row['user_wallet_address']); ?>', '<?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['transaction_proof']; ?>', '<?php echo htmlspecialchars($sendMethod ?? ''); ?>', '<?php echo htmlspecialchars($recMethod ?? ''); ?>')"
                                            class="p-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-indigo-400 transition-colors border border-gray-700 flex items-center space-x-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                            <span class="text-xs"><?php echo __('view'); ?></span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-600 text-xs italic"><?php echo __('none'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-gray-400 text-xs truncate max-w-[120px]"
                                    title="<?php echo $row['user_wallet_address']; ?>">
                                    <?php echo $row['user_wallet_address']; ?>
                                </td>
                                <td class="py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php
                                    if ($row['status'] == 'completed')
                                        echo 'bg-green-500/20 text-green-400';
                                    elseif ($row['status'] == 'pending')
                                        echo 'bg-yellow-500/20 text-yellow-400';
                                    else
                                        echo 'bg-red-500/20 text-red-400';
                                    ?>">
                                        <?php echo __('status_' . strtolower($row['status'])); ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <button onclick="submitAction(<?php echo $row['id']; ?>, 'approve')"
                                                class="bg-green-500/20 hover:bg-green-500/30 text-green-400 p-2 rounded-lg transition-colors border border-green-500/20"
                                                title="<?php echo __('approve'); ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </button>
                                            <button onclick="submitAction(<?php echo $row['id']; ?>, 'reject')"
                                                class="bg-red-500/20 hover:bg-red-500/30 text-red-400 p-2 rounded-lg transition-colors border border-red-500/20"
                                                title="<?php echo __('reject'); ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                        <button onclick="submitAction(<?php echo $row['id']; ?>, 'delete')"
                                            class="bg-gray-800 hover:bg-red-900/40 text-gray-500 hover:text-red-400 p-2 rounded-lg transition-colors border border-gray-700 hover:border-red-500/30"
                                            title="<?php echo __('delete'); ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Proof Modal -->
<div id="proofModal"
    class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-4 lg:p-8"
    onclick="closeProofModal(event)">
    <div class="glass-card rounded-3xl p-6 lg:p-8 max-w-5xl w-full flex flex-col lg:flex-row gap-8 max-h-[90vh] overflow-y-auto lg:overflow-visible"
        onclick="event.stopPropagation()">
        <!-- Image Section -->
        <div
            class="lg:w-1/2 flex items-center justify-center bg-gray-900/50 rounded-2xl border border-gray-700 p-2 group relative overflow-hidden">
            <img id="modalProofImg" src="" alt="Proof"
                class="max-w-full max-h-[60vh] object-contain rounded-xl shadow-2xl transition-transform duration-500 group-hover:scale-105">
            <a id="modalDownloadLink" href="" target="_blank"
                class="absolute bottom-4 right-4 bg-indigo-600 hover:bg-indigo-500 text-white p-2 rounded-full shadow-lg transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </a>
        </div>

        <!-- Details Section -->
        <div class="lg:w-1/2 flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <span class="bg-indigo-500/20 text-indigo-400 p-2 rounded-xl mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                    </span>
                    <?php echo __('exchange_details'); ?>
                </h3>
                <button onclick="closeProofModal()"
                    class="text-gray-400 hover:text-white transition-transform hover:rotate-90">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4 flex-1">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-800/40 p-4 rounded-2xl border border-gray-700/50">
                        <span
                            class="text-xs text-gray-500 block mb-1 uppercase tracking-wider"><?php echo __('order_id'); ?></span>
                        <span id="modalID" class="text-lg font-mono text-white"></span>
                    </div>
                    <div class="bg-gray-800/40 p-4 rounded-2xl border border-gray-700/50">
                        <span
                            class="text-xs text-gray-500 block mb-1 uppercase tracking-wider"><?php echo __('status'); ?></span>
                        <span id="modalStatus" class="inline-block px-3 py-1 rounded-full text-xs font-medium"></span>
                    </div>
                </div>

                <div class="bg-gray-800/40 p-5 rounded-2xl border border-gray-700/50 space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-400 text-sm"><?php echo __('user'); ?>:</span>
                        <span id="modalUserName" class="text-white font-bold"></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-700 pt-3">
                        <span class="text-gray-400 text-sm"><?php echo __('currency_sent'); ?>:</span>
                        <span id="modalSent" class="text-red-400 font-bold"></span>
                    </div>
                    <span class="text-gray-400 text-sm"><?php echo __('currency_received'); ?>:</span>
                    <span id="modalRec" class="text-green-400 font-bold"></span>
                </div>
                <div class="flex justify-between border-t border-gray-700 pt-3" id="modalMethodSendContainer">
                    <span
                        class="text-gray-400 text-sm"><?php echo $lang === 'ar' ? 'وسيلة الإرسال' : 'Sent Via'; ?>:</span>
                    <span id="modalMethodSend" class="text-indigo-300 font-medium"></span>
                </div>
                <div class="flex justify-between border-t border-gray-700 pt-3" id="modalMethodRecContainer">
                    <span
                        class="text-gray-400 text-sm"><?php echo $lang === 'ar' ? 'وسيلة الاستقبال' : 'Received Via'; ?>:</span>
                    <span id="modalMethodRec" class="text-green-300 font-medium"></span>
                </div>
                <div class="flex flex-col border-t border-gray-700 pt-3">
                    <span class="text-gray-400 text-sm mb-1"><?php echo __('wallet'); ?>:</span>
                    <span id="modalWallet"
                        class="text-xs font-mono text-indigo-300 break-all bg-gray-900/50 p-2 rounded-lg mt-1"></span>
                </div>
                <div class="flex justify-between border-t border-gray-700 pt-3">
                    <span class="text-gray-400 text-sm"><?php echo __('exchange_date'); ?>:</span>
                    <span id="modalDate" class="text-gray-400 text-sm italic"></span>
                </div>
            </div>
        </div>

        <div class="mt-8 flex gap-3" id="modalActionButtons">
            <!-- Actions will be injected here if pending -->
        </div>
    </div>
</div>
<script>
    function showProofModal(id, user, sent, rec, wallet, date, status, proof, sendMethod, recMethod) {
        // Mark as read via AJAX
        fetch('../includes/api/mark_exchange_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Optimistically update badge count if successful
                    // Note: Since we don't know if this specific item was unread without passing that info,
                    // we might decrement blindly or just rely on reload. 
                    // But for better UX, let's just reload on close actions or assume the user will navigate.
                    // Actually, let's try to decrement the badges if they exist.
                    const badges = document.querySelectorAll('.bg-red-500.text-white.rounded-full');
                    badges.forEach(badge => {
                        let count = parseInt(badge.textContent);
                        if (!isNaN(count) && count > 0) {
                            count--;
                            if (count === 0) {
                                badge.remove();
                            } else {
                                badge.textContent = count > 9 ? '9+' : count;
                            }
                        }
                    });
                }
            });

        document.getElementById('modalID').textContent = '#' + id;
        document.getElementById('modalUserName').textContent = user;
        document.getElementById('modalSent').textContent = sent;
        document.getElementById('modalRec').textContent = rec;
        document.getElementById('modalWallet').textContent = wallet;
        document.getElementById('modalDate').textContent = date;
        document.getElementById('modalProofImg').src = '../uploads/' + proof;
        document.getElementById('modalDownloadLink').href = '../uploads/' + proof;

        // Payment Methods
        const mSend = document.getElementById('modalMethodSend');
        const mRec = document.getElementById('modalMethodRec');
        if (sendMethod) {
            mSend.textContent = sendMethod;
            document.getElementById('modalMethodSendContainer').classList.remove('hidden');
        } else {
            document.getElementById('modalMethodSendContainer').classList.add('hidden');
        }

        if (recMethod) {
            mRec.textContent = recMethod;
            document.getElementById('modalMethodRecContainer').classList.remove('hidden');
        } else {
            document.getElementById('modalMethodRecContainer').classList.add('hidden');
        }

        // Status styling
        const statusEl = document.getElementById('modalStatus');
        statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusEl.className = 'inline-block px-3 py-1 rounded-full text-xs font-medium ';
        if (status === 'completed') statusEl.classList.add('bg-green-500/20', 'text-green-400');
        else if (status === 'pending') statusEl.classList.add('bg-yellow-500/20', 'text-yellow-400');
        else statusEl.classList.add('bg-red-500/20', 'text-red-400');

        // Action Buttons in Modal
        const actionContainer = document.getElementById('modalActionButtons');
        actionContainer.innerHTML = '';
        if (status === 'pending') {
            actionContainer.innerHTML = `
                <button onclick="submitAction(${id}, 'approve')" class="flex-1 bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-green-600/20 flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span><?php echo __('approve'); ?></span>
                </button>
                <button onclick="submitAction(${id}, 'reject')" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-red-600/20 flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    <span><?php echo __('reject'); ?></span>
                </button>
            `;
        }

        document.getElementById('proofModal').classList.remove('hidden');
    }

    function closeProofModal(event) {
        if (!event || event.target.id === 'proofModal') {
            document.getElementById('proofModal').classList.add('hidden');
        }
    }

    // Confirmation Modal Logic
    let currentActionId = null;
    let currentActionType = null;

    function submitAction(id, action) {
        currentActionId = id;
        currentActionType = action;

        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        const btnEl = document.getElementById('confirmBtn');
        const iconContainer = document.getElementById('confirmIcon');

        if (action === 'approve') {
            titleEl.textContent = '<?php echo __('approve'); ?>';
            msgEl.textContent = '<?php echo __('approve_confirm'); ?>';
            btnEl.textContent = '<?php echo __('approve'); ?>';
            btnEl.className = 'px-5 py-2.5 rounded-xl bg-green-600 text-white font-bold hover:bg-green-500 shadow-lg shadow-green-600/20 transition-colors';
            
            iconContainer.className = 'w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center mx-auto mb-4';
            iconContainer.innerHTML = '<svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        
        } else if (action === 'reject') {
            titleEl.textContent = '<?php echo __('reject'); ?>';
            msgEl.textContent = '<?php echo __('reject_confirm'); ?>';
            btnEl.textContent = '<?php echo __('reject'); ?>';
            btnEl.className = 'px-5 py-2.5 rounded-xl bg-red-600 text-white font-bold hover:bg-red-500 shadow-lg shadow-red-600/20 transition-colors';
            
            iconContainer.className = 'w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4';
            iconContainer.innerHTML = '<svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        
        } else if (action === 'delete') {
            titleEl.textContent = '<?php echo __('delete'); ?>';
            msgEl.textContent = '<?php echo __('delete_confirm'); ?>';
            btnEl.textContent = '<?php echo __('delete'); ?>';
            btnEl.className = 'px-5 py-2.5 rounded-xl bg-gray-700 text-white font-bold hover:bg-red-600 shadow-lg shadow-red-600/20 transition-colors';
             
            iconContainer.className = 'w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4';
            iconContainer.innerHTML = '<svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
        }

        document.getElementById('confirmModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.add('hidden');
        currentActionId = null;
        currentActionType = null;
    }

    function executeAction() {
        if (!currentActionId || !currentActionType) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'exchanges.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = currentActionId;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = currentActionType;
        
        form.appendChild(idInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        
        form.submit();
    }
</script>

<!-- Confirmation Modal HTML -->
<div id="confirmModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-[60] flex items-center justify-center p-4"  onclick="closeConfirmModal()">
    <div class="glass-card rounded-2xl p-6 md:p-8 max-w-md w-full text-center relative" onclick="event.stopPropagation()">
        <div class="mb-6">
            <div class="w-16 h-16 rounded-full bg-gray-700 flex items-center justify-center mx-auto mb-4" id="confirmIcon">
                <!-- Icon will be injected -->
            </div>
            <h3 class="text-xl font-bold text-white mb-2" id="confirmTitle">Confirm</h3>
            <p class="text-gray-400 text-sm" id="confirmMessage">Are you sure?</p>
        </div>

        <div class="flex gap-3 justify-center">
            <button onclick="closeConfirmModal()" 
                class="px-5 py-2.5 rounded-xl border border-gray-700 text-gray-300 font-medium hover:bg-gray-800 transition-colors">
                <?php echo __('cancel'); ?>
            </button>
            <button onclick="executeAction()" id="confirmBtn"
                class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-colors">
                <?php echo __('approve'); ?>
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>