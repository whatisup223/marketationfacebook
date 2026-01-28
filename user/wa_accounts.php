<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Placeholder for WhatsApp accounts until DB is ready
$wa_accounts = [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{ openAddModal: false }">
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

                <button @click="openAddModal = true"
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white px-6 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-green-600/20 group">
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
                            <button @click="openAddModal = true"
                                class="bg-white text-black px-8 py-3 rounded-2xl font-bold hover:bg-green-50 transition-colors shadow-xl">
                                <?php echo __('wa_get_started'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Accounts will be listed here -->
            <?php endif; ?>
        </div>

        <!-- Informational Section -->
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
                <div x-show="openAddModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/80 backdrop-blur-sm"
                    @click="openAddModal = false"></div>

                <!-- Modal Content -->
                <div x-show="openAddModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative glass-card w-full max-w-lg rounded-[2.5rem] border border-white/10 p-8 shadow-2xl overflow-hidden">

                    <div class="absolute top-0 right-0 p-6">
                        <button @click="openAddModal = false" class="text-gray-400 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="text-center">
                        <h3 class="text-2xl font-black text-white mb-2"><?php echo __('wa_link_new'); ?></h3>
                        <p class="text-gray-400 text-sm mb-8"><?php echo __('wa_scan_desc'); ?></p>

                        <!-- QR Code Placeholder -->
                        <div class="relative inline-block p-4 bg-white rounded-3xl mb-8 group">
                            <div class="w-64 h-64 bg-gray-100 flex items-center justify-center relative">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg"
                                    class="w-56 h-56" alt="QR Code">
                                <!-- Refresh Overlay -->
                                <div
                                    class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="bg-green-600 text-white p-3 rounded-full shadow-lg mb-2">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <span
                                        class="text-[10px] font-bold text-gray-500 uppercase"><?php echo __('wa_click_refresh'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 text-left">
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>