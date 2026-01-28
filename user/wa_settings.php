<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Handle form submission (Simplified for UI demonstration)
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden" x-data="{ gatewayType: 'local' }">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-72 h-72 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-4000 pointer-events-none">
        </div>

        <!-- Header Section -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('wa_settings'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('wa_gateway_desc'); ?></p>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 animate-fade-in">
                <div
                    class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-2xl flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span><?php echo __('settings_updated'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST" class="max-w-3xl relative z-10 animate-fade-in" style="animation-delay: 100ms;">
            <div class="glass-card rounded-[2.5rem] border border-white/5 p-8 md:p-10 space-y-8">

                <!-- Gateway Type Selection -->
                <div class="space-y-4">
                    <label
                        class="text-sm font-bold text-gray-400 uppercase tracking-widest"><?php echo __('wa_gateway_type'); ?></label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="relative group cursor-pointer">
                            <input type="radio" name="gateway_type" value="local" x-model="gatewayType"
                                class="peer hidden">
                            <div
                                class="p-6 rounded-2xl bg-white/5 border border-white/10 peer-checked:border-green-500/50 peer-checked:bg-green-500/5 transition-all">
                                <p class="text-white font-bold mb-1"><?php echo __('wa_local_server'); ?></p>
                                <p class="text-gray-500 text-xs"><?php echo __('wa_local_desc'); ?></p>
                            </div>
                        </label>
                        <label class="relative group cursor-pointer">
                            <input type="radio" name="gateway_type" value="ultramsg" x-model="gatewayType"
                                class="peer hidden">
                            <div
                                class="p-6 rounded-2xl bg-white/5 border border-white/10 peer-checked:border-blue-500/50 peer-checked:bg-blue-500/5 transition-all">
                                <p class="text-white font-bold mb-1"><?php echo __('wa_cloud_api'); ?></p>
                                <p class="text-gray-500 text-xs"><?php echo __('wa_cloud_desc'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Dynamic Fields based on Type -->
                <div class="space-y-6 pt-6 border-t border-white/5">

                    <!-- Server URL -->
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-400"><?php echo __('wa_server_url'); ?></label>
                        <input type="text" name="wa_server_url" placeholder="https://your-server.com/api"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-green-500/50 transition-all">
                        <p class="text-[10px] text-gray-500" x-show="gatewayType === 'local'">
                            <?php echo __('wa_endpoint_hint'); ?></p>
                    </div>

                    <!-- Instance ID (Visible for third party) -->
                    <div class="space-y-2" x-show="gatewayType === 'ultramsg'">
                        <label class="text-sm font-bold text-gray-400"><?php echo __('wa_instance_id'); ?></label>
                        <input type="text" name="wa_instance_id" placeholder="instance12345"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-blue-500/50 transition-all">
                    </div>

                    <!-- API Key -->
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-400"><?php echo __('wa_api_key'); ?></label>
                        <input type="password" name="wa_api_key" placeholder="••••••••••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-green-500/50 transition-all">
                    </div>

                </div>

                <!-- Footer with Submit -->
                <div class="pt-6 border-t border-white/5 flex items-center justify-between">
                    <p class="text-xs text-gray-500 italic max-w-xs"><?php echo __('wa_settings_note'); ?></p>
                    <button type="submit"
                        class="bg-green-600 hover:bg-green-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-green-600/20">
                        <?php echo __('wa_save_settings'); ?>
                    </button>
                </div>
            </div>
        </form>

        <!-- Help Card -->
        <div class="mt-8 relative z-10 animate-fade-in" style="animation-delay: 200ms;">
            <div
                class="bg-blue-500/5 border border-blue-500/10 rounded-[2rem] p-8 flex flex-col md:flex-row items-center gap-6">
                <div
                    class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 shrink-0">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-1"><?php echo __('wa_help_title'); ?></h4>
                    <p class="text-gray-400 text-sm"><?php echo __('wa_help_desc'); ?></p>
                </div>
                <a href="support.php"
                    class="whitespace-nowrap bg-white/5 hover:bg-white/10 text-white px-6 py-3 rounded-xl transition-all border border-white/10"><?php echo __('wa_expert_help'); ?></a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>