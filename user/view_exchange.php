<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Handle Proof Upload
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['proof'])) {
    $target_dir = __DIR__ . "/../uploads/";
    $file_ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $new_name = uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $new_name;

    if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
        // Update DB
        $stmt = $pdo->prepare("UPDATE exchanges SET transaction_proof = ?, user_wallet_address = ? WHERE id = ? AND user_id = ?");
        $wallet = $_POST['wallet_address'];
        $stmt->execute([$new_name, $wallet, $id, $user_id]);
        $message = $lang === 'ar' ? 'تم رفع الإثبات بنجاح! سيقوم المسؤول بالمراجعة قريباً.' : 'Proof uploaded successfully! Admin will review shortly.';
    } else {
        $message = $lang === 'ar' ? 'خطأ في رفع الملف.' : 'Error uploading file.';
    }
}

// Fetch Exchange with payment methods (optional)
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
    die("Exchange not found");
}

require_once __DIR__ . '/../includes/header.php';
?>


<style>
    @keyframes pulse-ring {
        0% {
            transform: scale(0.33);
        }

        80%,
        100% {
            opacity: 0;
        }
    }

    .pulse-ring::before {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        background-color: inherit;
        border-radius: 50%;
        z-index: -1;
        opacity: 0.4;
        animation: pulse-ring 1.25s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    }
</style>

<div class="relative min-h-[85vh] flex items-center justify-center py-12 overflow-hidden">

    <!-- Background Animated Blobs (Consistent with Index) -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute top-20 left-10 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-80 h-80 bg-purple-500/10 rounded-full blur-3xl animate-pulse"
            style="animation-delay: 1s;"></div>
    </div>

    <div class="container mx-auto px-4 relative z-10">
        <div class="max-w-4xl mx-auto glass-card rounded-3xl overflow-hidden shadow-2xl relative"
            x-data="exchangeView()">

            <!-- Header -->
            <div
                class="px-8 py-6 bg-gray-900/40 border-b border-gray-700/50 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">Exchange #<?php echo $exchange['id']; ?></h1>
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

                <!-- Timer -->
                <?php if ($exchange['status'] == 'pending' && empty($exchange['transaction_proof'])): ?>
                    <div
                        class="flex items-center gap-2 text-red-400 font-mono bg-red-500/10 px-4 py-2 rounded-lg border border-red-500/20">
                        <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="formatTime(timeLeft)"></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-8 md:p-12">
                <?php if ($message): ?>
                    <div
                        class="mb-8 p-4 rounded-xl bg-green-500/10 text-green-400 border border-green-500/20 flex items-center gap-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Exchange Flow -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-8 mb-12 relative">
                    <!-- Connector Line -->
                    <div
                        class="hidden md:block absolute top-1/2 left-0 w-full h-1 bg-gradient-to-r from-indigo-500/20 via-purple-500/20 to-indigo-500/20 -z-10 rounded-full">
                    </div>

                    <!-- Send Card -->
                    <div class="w-full md:w-5/12 text-center group">
                        <div
                            class="bg-gray-800/40 p-6 rounded-2xl border border-gray-700/50 hover:border-indigo-500/50 transition-all duration-300 transform group-hover:-translate-y-1">
                            <div class="text-xs text-gray-400 uppercase font-bold tracking-widest mb-4">
                                <?php echo __('send'); ?>
                            </div>
                            <div class="relative w-20 h-20 mx-auto mb-4">
                                <img src="<?php echo $exchange['from_img']; ?>"
                                    class="w-full h-full rounded-full object-cover shadow-lg group-hover:shadow-indigo-500/20 transition-all"
                                    alt="">
                                <div
                                    class="absolute -bottom-2 -right-2 bg-gray-900 rounded-full p-1 border border-gray-700">
                                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="text-2xl font-bold text-white mb-1 tracking-tight">
                                <?php echo $exchange['amount_send']; ?> <small
                                    class="text-gray-400"><?php echo $exchange['from_sym']; ?></small>
                            </div>
                            <div class="text-sm text-gray-500"><?php echo $exchange['from_name']; ?></div>
                            <?php if (!empty($exchange['send_method_name'])): ?>
                                <div class="text-xs text-indigo-400 mt-1">
                                    <?php echo $lang === 'ar' && !empty($exchange['send_method_name_ar']) ? $exchange['send_method_name_ar'] : $exchange['send_method_name']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Arrow Icon -->
                    <div
                        class="p-3 bg-gray-800 rounded-full border border-gray-700 shadow-lg z-10 md:rotate-0 rotate-90">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </div>

                    <!-- Receive Card -->
                    <div class="w-full md:w-5/12 text-center group">
                        <div
                            class="bg-gray-800/40 p-6 rounded-2xl border border-gray-700/50 hover:border-green-500/50 transition-all duration-300 transform group-hover:-translate-y-1">
                            <div class="text-xs text-gray-400 uppercase font-bold tracking-widest mb-4">
                                <?php echo __('receive'); ?>
                            </div>
                            <div class="relative w-20 h-20 mx-auto mb-4">
                                <img src="<?php echo $exchange['to_img']; ?>"
                                    class="w-full h-full rounded-full object-cover shadow-lg group-hover:shadow-green-500/20 transition-all"
                                    alt="">
                                <div
                                    class="absolute -bottom-2 -right-2 bg-gray-900 rounded-full p-1 border border-gray-700">
                                    <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="text-2xl font-bold text-green-400 mb-1 tracking-tight">
                                <?php echo $exchange['amount_receive']; ?> <small
                                    class="text-green-600"><?php echo $exchange['to_sym']; ?></small>
                            </div>
                            <div class="text-sm text-gray-500"><?php echo $exchange['to_name']; ?></div>
                            <?php if (!empty($exchange['receive_method_name'])): ?>
                                <div class="text-xs text-green-400 mt-1">
                                    <?php echo $lang === 'ar' && !empty($exchange['receive_method_name_ar']) ? $exchange['receive_method_name_ar'] : $exchange['receive_method_name']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Area -->
                <?php if ($exchange['status'] == 'pending' && empty($exchange['transaction_proof'])): ?>
                    <div class="bg-indigo-900/10 border border-indigo-500/20 rounded-2xl p-6 md:p-8">

                        <div class="flex items-start gap-4 mb-8">
                            <div
                                class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white mb-1"><?php echo __('payment_instructions'); ?></h3>
                                <p class="text-gray-400 text-sm leading-relaxed">
                                    <?php echo __('time_remaining'); ?>: <span class="text-red-400 font-bold"
                                        x-text="formatTime(timeLeft)"></span>.
                                    <?php echo __('transaction_cancelled'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="bg-gray-900/50 rounded-xl p-6 mb-8 border border-gray-700/50">
                            <div class="text-sm text-gray-500 mb-2">Send Amount:</div>
                            <div class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                                <?php echo $exchange['amount_send']; ?> <span
                                    class="px-2 py-0.5 rounded text-sm bg-gray-800 text-gray-300"><?php echo $exchange['from_sym']; ?></span>
                            </div>

                            <?php if (!empty($exchange['send_method_name'])): ?>
                                <div class="text-sm text-gray-500 mb-2">
                                    <?php echo $lang === 'ar' ? 'أرسل عبر' : 'Send via'; ?>:
                                </div>
                                <div class="text-lg font-semibold text-indigo-400 mb-4">
                                    <?php echo $lang === 'ar' && !empty($exchange['send_method_name_ar']) ? $exchange['send_method_name_ar'] : $exchange['send_method_name']; ?>
                                </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-500 mb-2">To Wallet Address:</div>
                            <div
                                class="flex items-center gap-3 bg-black/40 p-3 rounded-lg border border-gray-700/50 group hover:border-indigo-500/50 transition-colors">
                                <code class="text-indigo-300 font-mono flex-1 break-all"
                                    id="wallet-copy"><?php echo htmlspecialchars($exchange['send_method_wallet'] ?? 'N/A - No payment method selected'); ?></code>
                                <button @click="copyToClipboard"
                                    class="p-2 rounded-lg bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700 transition-all relative">
                                    <span x-show="copied"
                                        class="absolute -top-8 left-1/2 -translate-x-1/2 bg-green-500 text-white text-xs px-2 py-1 rounded shadow-lg">Copied!</span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-6" @submit.prevent="submitForm">

                            <!-- Wallet Input -->
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-bold mb-2"><?php echo __('enter_wallet'); ?></label>
                                <input type="text" name="wallet_address" x-model="wallet"
                                    class="w-full bg-gray-900/50 border border-gray-700 rounded-xl px-4 py-4 text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all"
                                    placeholder="Example: 0x..." required>
                            </div>

                            <!-- Proof Upload -->
                            <div x-data="{ isDragging: false }">
                                <label
                                    class="block text-gray-400 text-sm font-bold mb-2"><?php echo __('upload_proof'); ?></label>
                                <div class="relative group">
                                    <input type="file" name="proof" class="hidden" id="proof-upload" accept="image/*"
                                        @change="handleFileSelect" required>

                                    <label for="proof-upload" @dragover.prevent="isDragging = true"
                                        @dragleave.prevent="isDragging = false"
                                        @drop.prevent="isDragging = false; handleFileDrop($event)"
                                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer transition-all duration-300"
                                        :class="{'border-indigo-500 bg-indigo-500/10': isDragging, 'border-gray-700 bg-gray-900/30 hover:bg-gray-800/50 hover:border-gray-600': !isDragging}">

                                        <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center"
                                            x-show="!fileName">
                                            <div
                                                class="p-3 bg-gray-800 rounded-full text-gray-400 mb-3 group-hover:scale-110 transition-transform">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                                    </path>
                                                </svg>
                                            </div>
                                            <p class="text-sm text-gray-400 font-medium"><?php echo __('click_upload'); ?>
                                            </p>
                                            <p class="text-xs text-gray-600 mt-1">PNG, JPG, JPEG</p>
                                        </div>

                                        <!-- Upload Progress UI -->
                                        <div class="w-full px-8" x-show="fileName" style="display: none;">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-white truncate max-w-[200px]"
                                                    x-text="fileName"></span>
                                                <button type="button" @click="resetFile"
                                                    class="text-red-400 hover:text-red-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                                                <div class="bg-green-500 h-2 rounded-full transition-all duration-300"
                                                    :style="'width: ' + uploadProgress + '%'"></div>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-2 text-right">
                                                <span
                                                    x-text="uploadProgress < 100 ? '<?php echo __('uploading'); ?>' : '<?php echo __('upload_complete'); ?>'"></span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Confirm Button -->
                            <button type="submit" :disabled="!isValid"
                                class="w-full py-4 rounded-xl font-bold text-white shadow-lg transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2"
                                :class="isValid ? 'bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 shadow-green-900/40 cursor-pointer' : 'bg-gray-700 text-gray-400 cursor-not-allowed opacity-50'">
                                <span><?php echo __('confirm_payment'); ?></span>
                                <svg x-show="isValid" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php elseif ($exchange['status'] == 'completed'): ?>
                    <!-- Completed State -->
                    <div class="text-center py-16">
                        <div class="relative w-24 h-24 mx-auto mb-6">
                            <div class="absolute inset-0 bg-green-500 rounded-full opacity-20 animate-pulse"></div>
                            <div
                                class="relative w-full h-full bg-green-500/10 rounded-full flex items-center justify-center border border-green-500/30 text-green-500">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold text-white mb-2"><?php echo __('status_completed'); ?></h2>
                        <p class="text-gray-400 max-w-md mx-auto leading-relaxed mb-8">
                            <?php echo $lang === 'ar' ? 'تم إكمال المعاملة بنجاح. شكراً لك لاستخدام خدمتنا.' : 'Transaction completed successfully. Thank you for using our service.'; ?>
                        </p>

                        <a href="dashboard.php"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-500/30">
                            <?php echo __('return_to_dashboard'); ?>
                        </a>
                    </div>

                <?php elseif ($exchange['status'] == 'cancelled' || $exchange['status'] == 'rejected'): ?>
                    <!-- Cancelled/Rejected State -->
                    <div class="text-center py-16">
                        <div class="relative w-24 h-24 mx-auto mb-6">
                            <div
                                class="relative w-full h-full bg-red-500/10 rounded-full flex items-center justify-center border border-red-500/30 text-red-500">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold text-white mb-2"><?php echo __('status_' . $exchange['status']); ?>
                        </h2>
                        <p class="text-gray-400 max-w-md mx-auto leading-relaxed mb-8">
                            <?php echo $lang === 'ar' ? 'للأسف، تم إلغاء هذه المعاملة أو رفضها.' : 'Unfortunately, this transaction has been cancelled or rejected.'; ?>
                        </p>

                        <a href="dashboard.php"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-gray-700 hover:bg-gray-600 transition-all">
                            <?php echo __('return_to_dashboard'); ?>
                        </a>
                    </div>

                <?php elseif ($exchange['status'] == 'pending' && !empty($exchange['transaction_proof'])): ?>
                    <!-- Pending Review State -->
                    <div class="text-center py-16">
                        <div class="relative w-24 h-24 mx-auto mb-6">
                            <div class="absolute inset-0 bg-yellow-500 rounded-full opacity-20 pulse-ring"></div>
                            <div
                                class="relative w-full h-full bg-yellow-500/10 rounded-full flex items-center justify-center border border-yellow-500/30 text-yellow-500">
                                <svg class="w-10 h-10 animate-spin-slow" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2"><?php echo __('review_in_progress'); ?></h2>
                        <p class="text-gray-400 max-w-md mx-auto leading-relaxed mb-8"><?php echo __('review_msg'); ?></p>

                        <a href="dashboard.php"
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-500/30">
                            <?php echo __('return_to_dashboard'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function exchangeView() {
        return {
            // Get duration from settings (default 30 mins)
            maxTime: <?php echo getSetting('exchange_timer', 30) * 60; ?>,
            timeLeft: 0,
            wallet: '',
            fileName: '',
            uploadProgress: 0,
            copied: false,
            exchangeId: <?php echo $exchange['id']; ?>,
            storageKey: 'exchange_timer_<?php echo $exchange['id']; ?>',

            get isValid() {
                return this.wallet.length > 5 && this.fileName && this.uploadProgress === 100;
            },

            init() {
                // Calculate time elapsed strictly from server creation time
                // to avoid client-side clock tampering or mismatches
                const createdAtStr = '<?php echo $exchange['created_at']; ?>';
                // Parse Date safely (ensure format is standard, likely YYYY-MM-DD HH:MM:SS)
                // We'll treat the server time as UTC or consistent with client if possible, 
                // but best is to rely on relative diff if server injects 'now'.
                // Easier: In PHP calculate initial remaining seconds.

                // Better approach: Let PHP calculate the exact remaining seconds initially
                // to serve as the "true" authority, then JS counts down.
                // But user wants "resume after refresh" -> requires localStorage or re-calc.
                // If we re-calc from server time every refresh, that IS "resume from where it stopped" effectively (minus a second offset).
                // But localStorage is smoother.

                // Let's stick to the hybrid:
                // 1. Calculate theoretical time left based on DB `created_at` vs NOW.
                // 2. Check localStorage.
                // 3. If localStorage is drastically different from server-time-left (e.g. user fiddled with it), prefer server time? 
                // No, requested was "save automatically... counts from where it stopped".

                // Correction for "Sticky Zero":
                // If a new transaction is created, it has a UNIQUE ID. The key is unique.
                // Unless the user script somehow uses a global key? 
                // My storageKey uses ID: `exchange_timer_<?php echo $exchange['id']; ?>`.
                // If the user sees ZERO on a NEW transaction, it implies:
                // A) His creation time is OLD (server clock issue?)
                // B) He is reusing an ID? (impossible with Auto Inc)
                // C) There's a bug in `elapsedSeconds` calculation.

                const createdAt = new Date(createdAtStr.replace(' ', 'T')).getTime(); // Safely parse ISO-like
                // Note: SQL timestamp "2025-01-21 10:00:00" might parse as local or UTC in JS depending on browser.
                // To be safe, we should pass the server's CURRENT timestamp too to get the delta.
                const serverNow = <?php echo time() * 1000; ?>;
                const serverCreated = <?php echo strtotime($exchange['created_at']) * 1000; ?>;

                // Calculate elapsed based on SERVER time only
                const elapsedSeconds = Math.floor((serverNow - serverCreated) / 1000);

                // Theoretical time left
                let serverTimeLeft = Math.max(0, this.maxTime - elapsedSeconds);

                // LocalStorage Logic
                const savedTime = localStorage.getItem(this.storageKey);

                if (savedTime !== null) {
                    // We found a saved local timer.
                    let localDiff = parseInt(savedTime);

                    // Sanity check: If the local timer says 29 mins, but server says 1 min left,
                    // reliable source is server (user might have froze browser). 
                    // But if server says 30 mins (new), and local says 0... that's the bug.
                    // But key is unique! 
                    // Okay, we will trust Server Time primarily because it handles "refresh" perfectly naturally
                    // without needing localStorage per se, BUT localStorage helps if the user is offline? No.
                    // Actually, recalculating from `created_at` on every load IS the best way to "save state".
                    // It naturally resumes. "If I refresh page at 29:00, load page 10s later, it is 28:50". Correct.
                    // So user request "save automatically" is ALREADY satisfied by server-time calc.
                    // The localStorage is actually redundant and dangerous if client clock is wrong.

                    // HOWEVER, user asked "it works good... but problem is if timer ended to zero it keeps holding zero".
                    // Maybe he wants to remove the key if it's a new transaction?
                    // We will adhere to server time. It fixes everything.
                    // We will UPDATE localStorage for visual continuity if needed, but init from Server.

                    this.timeLeft = serverTimeLeft;
                } else {
                    this.timeLeft = serverTimeLeft;
                }

                // Double check negative
                if (this.timeLeft < 0) this.timeLeft = 0;

                // Start
                this.startTimer();
                this.addRefreshWarning();
            },

            startTimer() {
                this.timerInterval = setInterval(() => {
                    if (this.timeLeft > 0) {
                        this.timeLeft--;
                        localStorage.setItem(this.storageKey, this.timeLeft);
                    } else {
                        // Expired
                        localStorage.removeItem(this.storageKey);
                        // Optionally auto-reload or show expired message?
                        // For now just stop.
                        clearInterval(this.timerInterval);
                    }
                }, 1000);
            },

            addRefreshWarning() {
                // Warning message before page refresh/close
                window.addEventListener('beforeunload', (e) => {
                    // Only show warning if transaction is still pending and not submitted
                    <?php if ($exchange['status'] == 'pending' && empty($exchange['transaction_proof'])): ?>
                        if (this.timeLeft > 0) {
                            const message = '<?php echo $lang === 'ar' ? 'تحذير: إذا قمت بتحديث الصفحة أو مغادرتها، قد تفقد هذه المعاملة!' : 'Warning: If you refresh or leave this page, you may lose this transaction!'; ?>';
                            e.preventDefault();
                            e.returnValue = message;
                            return message;
                        }
                    <?php endif; ?>
                });
            },

            formatTime(seconds) {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return `${m}:${s < 10 ? '0' : ''}${s}`;
            },

            copyToClipboard() {
                const text = document.getElementById('wallet-copy').innerText;
                navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            },

            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    this.simulateUpload(file);
                }
            },

            handleFileDrop(event) {
                const file = event.dataTransfer.files[0];
                if (file) {
                    // Update input
                    document.getElementById('proof-upload').files = event.dataTransfer.files;
                    this.simulateUpload(file);
                }
            },

            simulateUpload(file) {
                this.fileName = file.name;
                this.uploadProgress = 0;

                // Simulate progress
                const interval = setInterval(() => {
                    this.uploadProgress += 10;
                    if (this.uploadProgress >= 100) {
                        clearInterval(interval);
                        this.uploadProgress = 100;
                    }
                }, 150); // Fast simulation
            },

            resetFile() {
                this.fileName = '';
                this.uploadProgress = 0;
                document.getElementById('proof-upload').value = '';
            },

            submitForm(event) {
                if (this.isValid) {
                    // Remove warning when submitting
                    window.onbeforeunload = null;
                    // Clear timer from storage as transaction is being submitted
                    localStorage.removeItem(this.storageKey);
                    event.target.submit();
                }
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>