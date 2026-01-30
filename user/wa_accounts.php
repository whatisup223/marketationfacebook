<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch Real Accounts
try {
    $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $wa_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If table doesn't exist or other DB error, prevent crash
    error_log("Database Error in wa_accounts.php: " . $e->getMessage());
    $wa_accounts = []; // Default to empty state
    // Optional: display user friendly error or check if table exists
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="waAccountManager()"
    @open-reconnect-modal.window="startLinking($event.detail.instance)">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-72 h-72 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute top-0 -right-4 w-72 h-72 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000 pointer-events-none">
        </div>

        <!-- Header Section -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <div class="flex items-center justify-between mb-4">
                <div
                    class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                    <span
                        class="text-green-300 text-xs font-bold uppercase tracking-widest"><?php echo __('whatsapp'); ?></span>
                </div>

                <button type="button" @click="startLinking()"
                    class="flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white px-6 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-indigo-600/20 group">
                    <svg class="w-5 h-5 group-hover:rotate-90 transition-transform" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span><?php echo __('wa_link_new'); ?></span>
                </button>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('wa_accounts'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('wa_accounts_desc'); ?></p>
        </div>

        <!-- Accounts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 relative z-10">
            <?php if (empty($wa_accounts)): ?>
                <!-- Empty State Card -->
                <div class="lg:col-span-3">
                    <div
                        class="glass-card rounded-[2.5rem] border border-white/5 p-12 text-center relative overflow-hidden group">
                        <div
                            class="absolute inset-0 bg-gradient-to-b from-green-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="relative z-10 flex flex-col items-center">
                            <div
                                class="w-24 h-24 rounded-3xl bg-green-500/10 flex items-center justify-center text-green-400 mb-6 border border-green-500/20">
                                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-2"><?php echo __('wa_no_accounts'); ?></h3>
                            <p class="text-gray-400 mb-8 max-w-sm"><?php echo __('wa_no_accounts_desc'); ?></p>
                            <button type="button" @click="startLinking()"
                                class="bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white px-10 py-4 rounded-2xl font-black transition-all shadow-xl hover:scale-105 active:scale-95">
                                <?php echo __('wa_get_started'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($wa_accounts as $acc): ?>
                    <div x-data="waAccountCard({
                            id: <?php echo $acc['id']; ?>,
                            instanceName: '<?php echo $acc['instance_name']; ?>',
                            initialStatus: '<?php echo $acc['status']; ?>'
                        })"
                        class="glass-card rounded-[2rem] border border-white/5 overflow-hidden animate-fade-in shadow-lg relative group">

                        <!-- Overlay for processing actions -->
                        <div x-show="processing"
                            class="absolute inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center rounded-[2rem]"
                            x-transition.opacity x-cloak>
                            <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        </div>

                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center transition-colors duration-300"
                                    :class="status === 'connected' ? 'text-green-500 bg-green-500/10' : (status === 'pairing' ? 'text-orange-500 bg-orange-500/10 animate-pulse' : 'text-red-500 bg-red-500/10')">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                    </svg>
                                </div>
                                <div class="flex gap-2">
                                    <span
                                        class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border border-current/10"
                                        :class="status === 'connected' ? 'bg-green-500/10 text-green-500' : (status === 'pairing' ? 'bg-orange-500/10 text-orange-500' : 'bg-red-500/10 text-red-500')"
                                        x-text="statusText">
                                    </span>
                                </div>
                            </div>
                            <div class="mb-6">
                                <h4 class="text-white font-bold text-lg mb-1">
                                    <?php echo htmlspecialchars($acc['account_name'] ?: __('whatsapp_account')); ?>
                                </h4>
                                <p class="text-gray-500 text-sm">
                                    <?php echo htmlspecialchars($acc['phone'] ?: $acc['instance_name']); ?>
                                </p>
                            </div>

                            <!-- Action Buttons Grid -->
                            <div class="grid grid-cols-2 gap-2" x-show="status === 'connected'">
                                <button type="button" @click="handleAction('restart')"
                                    class="py-2.5 px-4 rounded-xl bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white transition-all text-xs font-bold border border-blue-500/20 flex items-center justify-center gap-2 group/btn">
                                    <svg class="w-4 h-4 group-hover/btn:rotate-180 transition-transform" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Restart
                                </button>
                                <button type="button" @click="handleAction('logout')"
                                    class="py-2.5 px-4 rounded-xl bg-orange-500/10 text-orange-400 hover:bg-orange-500 hover:text-white transition-all text-xs font-bold border border-orange-500/20 flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Disconnect
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-2" x-show="status !== 'connected'">
                                <button type="button" @click="reconnect()"
                                    class="py-3 px-4 rounded-xl bg-green-600 hover:bg-green-500 text-white transition-all text-xs font-bold shadow-lg shadow-green-600/20 flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Scan QR Again
                                </button>
                            </div>

                            <!-- Footer Actions -->
                            <div class="mt-3 pt-3 border-t border-white/5 flex gap-2">
                                <button type="button" @click="handleAction('delete')"
                                    class="w-full py-2 rounded-lg text-red-400/60 hover:text-red-400 text-[10px] font-bold uppercase tracking-wider hover:bg-red-500/5 transition-all">
                                    Remove Account
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Guidelines Section -->
        <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10 animate-fade-in"
            style="animation-delay: 400ms;">
            <div class="glass-card p-8 rounded-[2rem] border border-white/5 bg-white/[0.02]">
                <h4 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <?php echo __('wa_guidelines'); ?>
                </h4>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3 text-gray-400 text-sm">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5"></div>
                        <span><?php echo __('wa_guide_1'); ?></span>
                    </li>
                    <li class="flex items-start gap-3 text-gray-400 text-sm">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5"></div>
                        <span><?php echo __('wa_guide_2'); ?></span>
                    </li>
                    <li class="flex items-start gap-3 text-gray-400 text-sm">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5"></div>
                        <span><?php echo __('wa_guide_3'); ?></span>
                    </li>
                </ul>
            </div>

            <div class="glass-card p-8 rounded-[2rem] border border-white/5 bg-white/[0.02]">
                <h4 class="text-xl font-bold text-white mb-4 flex items-center gap-3">
                    <span
                        class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </span>
                    <?php echo __('wa_security_privacy'); ?>
                </h4>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?php echo __('wa_security_desc'); ?>
                </p>
                <div
                    class="mt-6 p-4 rounded-xl bg-yellow-500/5 border border-yellow-500/10 text-yellow-400/80 text-[10px] font-medium uppercase tracking-wider">
                    <?php echo __('wa_security_note'); ?>
                </div>
            </div>
        </div>

        <!-- Add Account Modal -->
        <div x-show="openAddModal" class="fixed inset-0 z-[100] overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="closeModal()"></div>

                <!-- Modal Content -->
                <div
                    class="relative glass-card w-full max-w-lg rounded-[2.5rem] border border-white/10 p-8 shadow-2xl overflow-hidden text-center">
                    <button @click="closeModal()"
                        class="absolute top-6 right-6 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <h3 class="text-2xl font-black text-white mb-2"><?php echo __('wa_link_new'); ?></h3>
                    <p class="text-gray-400 text-sm mb-8"><?php echo __('wa_scan_desc'); ?></p>

                    <!-- QR Code Display Area -->
                    <div class="relative inline-block p-4 bg-white rounded-3xl mb-8 shadow-inner">
                        <div
                            class="w-64 h-64 flex items-center justify-center relative bg-gray-50 overflow-hidden rounded-2xl mb-4">
                            <!-- Loading State -->
                            <div x-show="loading" class="flex flex-col items-center">
                                <div
                                    class="w-10 h-10 border-4 border-green-500 border-t-transparent rounded-full animate-spin mb-4">
                                </div>
                                <span class="text-xs text-gray-400 font-bold uppercase tracking-widest">Generating
                                    QR...</span>
                            </div>

                            <!-- Success: QR Code -->
                            <img x-show="qrCode && !loading" :src="qrCode" class="w-full h-full object-contain p-2"
                                alt="QR Code">

                            <!-- Connected Overlay -->
                            <div x-show="connected"
                                class="absolute inset-0 bg-white flex flex-col items-center justify-center animate-fade-in z-20">
                                <div
                                    class="w-20 h-20 bg-green-500/10 text-green-500 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-lg font-bold text-green-600">Connected!</span>
                            </div>
                        </div>

                        <!-- Timer moved outside -->
                        <div x-show="qrCode && !loading && !connected" class="text-center">
                            <span
                                class="px-3 py-1 rounded-full bg-gray-100 text-[10px] text-gray-500 font-bold uppercase tracking-wider">
                                Code refreshes in <span x-text="countdown" class="text-indigo-500"></span>s
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 text-left px-4">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center text-xs font-bold shrink-0">
                                1</div>
                            <p class="text-xs text-gray-400"><?php echo __('wa_scan_step_1'); ?></p>
                        </div>
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center text-xs font-bold shrink-0">
                                2</div>
                            <p class="text-xs text-gray-400"><?php echo __('wa_scan_step_2'); ?></p>
                        </div>
                        <div class="flex items-start gap-4">
                            <div
                                class="w-6 h-6 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center text-xs font-bold shrink-0">
                                3</div>
                            <p class="text-xs text-gray-400"><?php echo __('wa_scan_step_3'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function waAccountManager() {
        return {
            openAddModal: false,
            qrCode: null,
            loading: false,
            connected: false,
            countdown: 60,
            timer: null,
            instanceName: null,
            statusInterval: null,

            // Modified to accept optional instance name (for reconnection)
            async startLinking(existingInstance = null) {
                this.openAddModal = true;
                this.loading = true;
                this.qrCode = null;
                this.connected = false;
                this.instanceName = existingInstance;

                // Stop any previous intervals
                this.cleanup();

                if (existingInstance) {
                    // Reconnect mode: Just fetch QR for existing instance
                    this.refreshQR();
                } else {
                    // New instance mode
                    try {
                        const response = await fetch('ajax_wa_accounts.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=init_instance'
                        });
                        const data = await response.json();

                        if (data.status === 'success') {
                            this.qrCode = data.qr;
                            this.instanceName = data.instance_name;
                            this.loading = false;
                            this.startStatusCheck();
                            this.startCountdown();
                        } else {
                            if (data.debug && data.debug.instance_created && data.debug.instance_name) {
                                console.log('Instance created, starting QR polling...');
                                this.instanceName = data.debug.instance_name;
                                this.startQRPolling();
                            } else {
                                alert(data.message || 'Error connecting to Evolution API');
                                this.closeModal();
                            }
                        }
                    } catch (e) {
                        console.error('Fetch Error:', e);
                        alert('Connection failed');
                        this.closeModal();
                    }
                }
            },

            startCountdown() {
                this.countdown = 60;
                if (this.timer) clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (this.countdown > 0) this.countdown--;
                    else this.refreshQR();
                }, 1000);
            },

            async refreshQR() {
                if (!this.openAddModal || this.connected) return;
                this.loading = true;
                if (this.timer) clearInterval(this.timer);

                try {
                    const response = await fetch('ajax_wa_accounts.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=get_qr&instance_name=${this.instanceName}`
                    });
                    const data = await response.json();

                    if (data.status === 'success' && data.qr) {
                        this.qrCode = data.qr;
                        this.loading = false;
                        this.startCountdown();
                        // Start checking status if not already running
                        if (!this.statusInterval) this.startStatusCheck();
                    } else {
                        console.warn('QR Refresh failed or not ready');
                        this.loading = false;
                        this.countdown = 10;
                        this.timer = setInterval(() => {
                            if (this.countdown > 0) this.countdown--;
                            else this.refreshQR();
                        }, 1000);
                    }
                } catch (e) {
                    console.error('Refresh Error:', e);
                    this.loading = false;
                    this.countdown = 10;
                    this.timer = setInterval(() => {
                        if (this.countdown > 0) this.countdown--;
                        else this.refreshQR();
                    }, 1000);
                }
            },

            startStatusCheck() {
                if (this.statusInterval) clearInterval(this.statusInterval);

                // Aggressive check: every 1 second
                this.statusInterval = setInterval(async () => {
                    if (!this.openAddModal || this.connected) return;

                    try {
                        const response = await fetch('ajax_wa_accounts.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=check_status&instance_name=${this.instanceName}`
                        });
                        const data = await response.json();

                        console.log('Status Check Response:', data); // Debug log

                        if (data.connected) {
                            console.log('Connected! Reloading...');
                            this.connected = true;
                            // Success sound or visual feedback could be here
                            this.cleanup();

                            // Close modal after brief success message
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    } catch (e) {
                        console.error('Status Check Error:', e);
                    }
                }, 1000);
            },

            startQRPolling() {
                let pollAttempts = 0;
                const maxAttempts = 30;

                const pollInterval = setInterval(async () => {
                    pollAttempts++;
                    if (pollAttempts > maxAttempts) {
                        clearInterval(pollInterval);
                        this.loading = false;
                        alert('QR code generation timeout.');
                        this.closeModal();
                        return;
                    }

                    try {
                        const response = await fetch('ajax_wa_accounts.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=poll_qr&instance_name=${this.instanceName}`
                        });
                        const data = await response.json();

                        if (data.status === 'success' && data.qr) {
                            clearInterval(pollInterval);
                            this.qrCode = data.qr;
                            this.loading = false;
                            this.startStatusCheck();
                            this.startCountdown();
                        } else if (data.status === 'error') {
                            clearInterval(pollInterval);
                            this.loading = false;
                            alert(data.message || 'Failed to get QR code');
                            this.closeModal();
                        }
                    } catch (e) { }
                }, 2000);
                this.qrPollInterval = pollInterval;
            },

            cleanup() {
                if (this.timer) clearInterval(this.timer);
                if (this.statusInterval) clearInterval(this.statusInterval);
                if (this.qrPollInterval) clearInterval(this.qrPollInterval);
            },

            closeModal() {
                this.openAddModal = false;
                this.cleanup();
            }
        }
    }

    // Individual Account Card Component
    function waAccountCard(props) {
        return {
            id: props.id,
            instanceName: props.instanceName,
            status: props.initialStatus,
            processing: false,

            get statusText() {
                if (this.status === 'connected') return 'Connected';
                if (this.status === 'pairing') return 'Pairing...';
                return 'Disconnected';
            },

            init() {
                // If status is not connected/disconnected, poll for updates
                if (this.status === 'pairing' || this.status === 'connecting') {
                    this.startCardPolling();
                }
            },

            startCardPolling() {
                const interval = setInterval(async () => {
                    // Stop polling if we navigated away or component destroyed (safety check)
                    try {
                        const response = await fetch('ajax_wa_accounts.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=check_status&instance_name=${this.instanceName}`
                        });
                        const data = await response.json();

                        if (data.connected) {
                            this.status = 'connected';
                            clearInterval(interval);
                        } else {
                            // If status is definitely disconnected/closed, update UI
                            // But usually we just wait for 'connected'
                        }
                    } catch (e) { console.error('Card Poll Error', e); }
                }, 5000); // Check every 5s
            },

            reconnect() {
                // Call the main manager to open modal with this instance
                // We access the parent component data via $dispatch or global event, 
                // but since waAccountManager is on the root, we can try to find it.
                // However, cleanest way is to use Alpine event.
                window.dispatchEvent(new CustomEvent('open-reconnect-modal', {
                    detail: { instance: this.instanceName }
                }));
            },

            async handleAction(action) {
                if (!confirm(`Are you sure you want to ${action} this account?`)) return;

                this.processing = true;
                try {
                    const response = await fetch('ajax_wa_accounts.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=${action === 'delete' ? 'delete_account' : action}&id=${this.id}`
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        if (action === 'delete') {
                            window.location.reload();
                        } else if (action === 'logout') {
                            this.status = 'disconnected';
                        } else if (action === 'restart') {
                            alert('Instance restarted successfully');
                        }
                    } else {
                        alert(data.message || 'Action failed');
                    }
                } catch (e) {
                    alert('Network error');
                } finally {
                    this.processing = false;
                }
            }
        }
    }


</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>