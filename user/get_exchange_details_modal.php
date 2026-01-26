<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    exit('Unauthorized');
}

$pdo = getDB();
$id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch Exchange
$stmt = $pdo->prepare("SELECT e.*, 
                              c1.name_en as from_name, c1.symbol as from_sym, c1.image as from_img,
                              c2.name_en as to_name, c2.symbol as to_sym, c2.image as to_img,
                              pm1.name as send_method_name, pm1.name_ar as send_method_name_ar, pm1.wallet_info as send_method_wallet,
                              pm2.name as receive_method_name, pm2.name_ar as receive_method_name_ar, pm2.wallet_info as receive_method_wallet
                       FROM exchanges e 
                       LEFT JOIN currencies c1 ON e.currency_from_id = c1.id 
                       LEFT JOIN currencies c2 ON e.currency_to_id = c2.id 
                       LEFT JOIN payment_methods pm1 ON e.payment_method_send_id = pm1.id
                       LEFT JOIN payment_methods pm2 ON e.payment_method_receive_id = pm2.id
                       WHERE e.id = ? AND e.user_id = ?");
$stmt->execute([$id, $user_id]);
$exchange = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exchange) {
    echo '<div class="p-8 text-center text-red-400">Exchange not found</div>';
    exit;
}
?>

<div class="relative w-full max-w-4xl mx-auto"
    x-data="exchangeViewModal(<?php echo $exchange['id']; ?>, <?php echo strtotime($exchange['created_at']) * 1000; ?>)">

    <!-- Patience Message -->
    <div class="bg-indigo-900/40 border-b border-indigo-500/20 px-8 py-4">
        <div class="flex items-center gap-3 text-indigo-300">
            <svg class="w-5 h-5 flex-shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm font-medium">
                <?php echo $lang === 'ar' ? 'يرجى التحلي بالصبر، عادة ما تستغرق مراجعة المعاملات بضع دقائق فقط.' : 'Please be patient, transaction reviews usually take just a few minutes.'; ?>
            </p>
        </div>
    </div>

    <!-- Header -->
    <div class="px-8 py-6 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-white">Exchange #
                <?php echo $exchange['id']; ?>
            </h1>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide 
                <?php
                if ($exchange['status'] == 'completed')
                    echo 'bg-green-500/20 text-green-400 border border-green-500/30';
                elseif ($exchange['status'] == 'pending')
                    echo 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
                else
                    echo 'bg-red-500/20 text-red-400 border border-red-500/30';
                ?>">
                <?php echo __($exchange['status'] == 'pending' ? 'status_pending' : ($exchange['status'] == 'completed' ? 'status_completed' : 'status_cancelled')); ?>
            </span>
        </div>

        <?php if ($exchange['status'] == 'pending' && empty($exchange['transaction_proof'])): ?>
            <div
                class="flex items-center gap-2 text-red-400 font-mono bg-red-500/10 px-3 py-1.5 rounded-lg border border-red-500/20 text-sm">
                <span x-text="formatTime(timeLeft)"></span>
            </div>
        <?php endif; ?>

        <button @click="showModal = false" class="text-gray-400 hover:text-white transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="p-8 pt-2">
        <!-- Exchange Flow -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-8 mb-8 relative">
            <div
                class="hidden md:block absolute top-1/2 left-0 w-full h-1 bg-gradient-to-r from-indigo-500/20 via-purple-500/20 to-indigo-500/20 -z-10 rounded-full">
            </div>

            <!-- Send -->
            <div class="text-center group w-full md:w-auto flex-1">
                <div class="bg-gray-800/40 p-4 rounded-xl border border-gray-700/50">
                    <div class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-3">
                        <?php echo __('send'); ?>
                    </div>
                    <div class="relative w-12 h-12 mx-auto mb-3">
                        <img src="<?php echo $exchange['from_img']; ?>" class="w-full h-full rounded-full object-cover">
                    </div>
                    <div class="text-lg font-bold text-white mb-0.5">
                        <?php echo $exchange['amount_send']; ?> <small class="text-gray-400">
                            <?php echo $exchange['from_sym']; ?>
                        </small>
                    </div>
                    <?php if (!empty($exchange['send_method_name'])): ?>
                        <div class="text-xs text-indigo-400">
                            <?php echo $lang === 'ar' && !empty($exchange['send_method_name_ar']) ? $exchange['send_method_name_ar'] : $exchange['send_method_name']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-2 bg-gray-800 rounded-full border border-gray-700 md:rotate-0 rotate-90 shrink-0 z-10">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6">
                    </path>
                </svg>
            </div>

            <!-- Receive -->
            <div class="text-center group w-full md:w-auto flex-1">
                <div class="bg-gray-800/40 p-4 rounded-xl border border-gray-700/50">
                    <div class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-3">
                        <?php echo __('receive'); ?>
                    </div>
                    <div class="relative w-12 h-12 mx-auto mb-3">
                        <img src="<?php echo $exchange['to_img']; ?>" class="w-full h-full rounded-full object-cover">
                    </div>
                    <div class="text-lg font-bold text-green-400 mb-0.5">
                        <?php echo $exchange['amount_receive']; ?> <small class="text-green-600">
                            <?php echo $exchange['to_sym']; ?>
                        </small>
                    </div>
                    <?php if (!empty($exchange['receive_method_name'])): ?>
                        <div class="text-xs text-green-400">
                            <?php echo $lang === 'ar' && !empty($exchange['receive_method_name_ar']) ? $exchange['receive_method_name_ar'] : $exchange['receive_method_name']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($exchange['status'] == 'pending' && empty($exchange['transaction_proof'])): ?>
            <div class="bg-indigo-900/10 border border-indigo-500/20 rounded-2xl p-6">
                <!-- Payment Info -->
                <div class="mb-6">
                    <div class="text-xs text-gray-500 mb-1">To Wallet:</div>
                    <div class="flex items-center gap-2 bg-black/40 p-2 rounded-lg border border-gray-700/50">
                        <code
                            class="text-indigo-300 font-mono text-sm flex-1 break-all select-all"><?php echo htmlspecialchars($exchange['send_method_wallet'] ?? 'N/A'); ?></code>
                    </div>
                </div>

                <form action="view_exchange.php?id=<?php echo $exchange['id']; ?>" method="POST"
                    enctype="multipart/form-data" class="space-y-4">
                    <!-- Wallet Input -->
                    <div>
                        <label class="block text-gray-400 text-xs font-bold mb-1">
                            <?php echo __('enter_wallet'); ?>
                        </label>
                        <input type="text" name="wallet_address" x-model="wallet"
                            class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-indigo-500 transition-all"
                            placeholder="Example: 0x..." required>
                    </div>

                    <!-- Simple Upload -->
                    <div>
                        <label class="block text-gray-400 text-xs font-bold mb-1">
                            <?php echo __('upload_proof'); ?>
                        </label>
                        <input type="file" name="proof"
                            class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer"
                            accept="image/*" required>
                    </div>

                    <button type="submit"
                        class="w-full py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-all shadow-lg text-sm mt-4">
                        <?php echo __('confirm_payment'); ?>
                    </button>
                    <!-- Button to full view -->
                    <a href="view_exchange.php?id=<?php echo $exchange['id']; ?>"
                        class="block text-center text-xs text-indigo-400 hover:text-white mt-2">
                        Open full page to upload
                    </a>
                </form>
            </div>
        <?php elseif ($exchange['status'] == 'completed'): ?>
            <div class="text-center py-8">
                <div class="relative w-16 h-16 mx-auto mb-4">
                    <div class="absolute inset-0 bg-green-500 rounded-full opacity-20 animate-pulse"></div>
                    <div
                        class="relative w-full h-full bg-green-500/10 rounded-full flex items-center justify-center border border-green-500/30 text-green-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-white mb-1"><?php echo __('status_completed'); ?></h2>
                <p class="text-gray-400 text-sm max-w-sm mx-auto">
                    <?php echo $lang === 'ar' ? 'تم إكمال المعاملة بنجاح.' : 'Transaction completed successfully.'; ?>
                </p>
                <div class="mt-6">
                    <a href="view_exchange.php?id=<?php echo $exchange['id']; ?>"
                        class="text-indigo-400 hover:text-white text-sm underline"><?php echo __('view_full_details'); ?></a>
                </div>
            </div>

        <?php elseif ($exchange['status'] == 'cancelled' || $exchange['status'] == 'rejected'): ?>
            <div class="text-center py-8">
                <div class="relative w-16 h-16 mx-auto mb-4">
                    <div
                        class="relative w-full h-full bg-red-500/10 rounded-full flex items-center justify-center border border-red-500/30 text-red-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-white mb-1"><?php echo __('status_' . $exchange['status']); ?></h2>
                <p class="text-gray-400 text-sm max-w-sm mx-auto">
                    <?php echo $lang === 'ar' ? 'تم إلغاء أو رفض المعاملة.' : 'Transaction cancelled or rejected.'; ?>
                </p>
                <div class="mt-6">
                    <a href="view_exchange.php?id=<?php echo $exchange['id']; ?>"
                        class="text-indigo-400 hover:text-white text-sm underline"><?php echo __('view_full_details'); ?></a>
                </div>
            </div>

        <?php elseif ($exchange['status'] == 'pending' && !empty($exchange['transaction_proof'])): ?>
            <div class="text-center py-8">
                <div class="relative w-16 h-16 mx-auto mb-4">
                    <div class="absolute inset-0 bg-yellow-500 rounded-full opacity-20 animate-ping"></div>
                    <div
                        class="relative w-full h-full bg-yellow-500/10 rounded-full flex items-center justify-center border border-yellow-500/30 text-yellow-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-white mb-1">
                    <?php echo __('review_in_progress'); ?>
                </h2>
                <p class="text-gray-400 text-sm max-w-sm mx-auto">
                    <?php echo __('review_msg'); ?>
                </p>
                <div class="mt-6">
                    <a href="view_exchange.php?id=<?php echo $exchange['id']; ?>"
                        class="text-indigo-400 hover:text-white text-sm underline"><?php echo __('view_full_details'); ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function exchangeViewModal(id, createdAtTimestamp) {
        return {
            timeLeft: 0,
            wallet: '',
            init() {
                const now = Date.now();
                const elapsedSeconds = Math.floor((now - createdAtTimestamp) / 1000);
                const maxTime = 1800; // 30 mins
                this.timeLeft = Math.max(0, maxTime - elapsedSeconds);

                setInterval(() => {
                    if (this.timeLeft > 0) this.timeLeft--;
                }, 1000);
            },
            formatTime(seconds) {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return `${m}:${s < 10 ? '0' : ''}${s}`;
            }
        }
    }
</script>