<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch current user settings
$stmt = $pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist yet, set defaults
if (!$user_settings) {
    $user_settings = [
        'active_gateway' => 'qr',
        'external_provider' => 'meta',
        'external_config' => json_encode([])
    ];
}

$config = json_decode($user_settings['external_config'], true) ?: [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_gateway = $_POST['active_gateway'] ?? 'qr';
    $external_provider = $_POST['external_provider'] ?? 'meta';

    // Build config JSON based on provider
    $new_config = [];
    if ($external_provider === 'meta') {
        $new_config = [
            'app_id' => $_POST['meta_app_id'] ?? '',
            'phone_id' => $_POST['meta_phone_id'] ?? '',
            'token' => $_POST['meta_token'] ?? ''
        ];
    } elseif ($external_provider === 'twilio') {
        $new_config = [
            'sid' => $_POST['twilio_sid'] ?? '',
            'token' => $_POST['twilio_token'] ?? '',
            'phone' => $_POST['twilio_phone'] ?? ''
        ];
    } elseif ($external_provider === 'ultramsg') {
        $new_config = [
            'instance_id' => $_POST['ultra_instance_id'] ?? '',
            'token' => $_POST['ultra_token'] ?? ''
        ];
    }

    $config_json = json_encode($new_config);

    // Upsert logic
    $stmt = $pdo->prepare("INSERT INTO user_wa_settings (user_id, active_gateway, external_provider, external_config) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE active_gateway = ?, external_provider = ?, external_config = ?");
    $stmt->execute([
        $user_id,
        $active_gateway,
        $external_provider,
        $config_json,
        $active_gateway,
        $external_provider,
        $config_json
    ]);

    $_SESSION['wa_settings_success'] = true;
    header("Location: wa_settings.php");
    exit;
}

$success = false;
if (isset($_SESSION['wa_settings_success'])) {
    $success = true;
    unset($_SESSION['wa_settings_success']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden" x-data="{ 
            activeGateway: '<?php echo $user_settings['active_gateway']; ?>',
            provider: '<?php echo $user_settings['external_provider']; ?>'
         }">

        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-72 h-72 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-4000 pointer-events-none">
        </div>

        <!-- Header -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('wa_settings'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('wa_active_mode'); ?>:
                <span class="text-green-400 font-bold"
                    x-text="activeGateway === 'qr' ? '<?php echo __('wa_gateway_qr'); ?>' : '<?php echo __('wa_gateway_official'); ?>'"></span>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 animate-fade-in">
                <div
                    class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-2xl flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span><?php echo __('wa_settings_saved'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8 relative z-10">
            <!-- Mode Switcher -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- QR Card -->
                <div @click="activeGateway = 'qr'"
                    class="glass-card cursor-pointer p-8 rounded-[2.5rem] border transition-all duration-300 group overflow-hidden relative"
                    :class="activeGateway === 'qr' ? 'border-green-500/50 bg-green-500/5' : 'border-white/5 hover:border-white/20'">
                    <input type="radio" name="active_gateway" value="qr" class="hidden"
                        :checked="activeGateway === 'qr'">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center transition-colors"
                            :class="activeGateway === 'qr' ? 'bg-green-500 text-white shadow-lg shadow-green-500/20' : 'bg-white/5 text-gray-400 group-hover:text-white'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1l-3 3h2v5h2v-5h2l-3-3V4zM8 4h8a2 2 0 012 2v12a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold" :class="activeGateway === 'qr' ? 'text-white' : 'text-gray-400'">
                            <?php echo __('wa_gateway_qr'); ?>
                        </h3>
                    </div>
                    <p class="text-sm leading-relaxed"
                        :class="activeGateway === 'qr' ? 'text-green-100/70' : 'text-gray-500'">
                        <?php echo __('wa_gateway_qr_desc'); ?>
                    </p>

                    <!-- Active Indicator -->
                    <div x-show="activeGateway === 'qr'" class="absolute top-4 right-4 animate-scale-in">
                        <div class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_10px_#22c55e]"></div>
                    </div>
                </div>

                <!-- Official Card -->
                <div @click="activeGateway = 'external'"
                    class="glass-card cursor-pointer p-8 rounded-[2.5rem] border transition-all duration-300 group overflow-hidden relative"
                    :class="activeGateway === 'external' ? 'border-indigo-500/50 bg-indigo-500/5' : 'border-white/5 hover:border-white/20'">
                    <input type="radio" name="active_gateway" value="external" class="hidden"
                        :checked="activeGateway === 'external'">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center transition-colors"
                            :class="activeGateway === 'external' ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-500/20' : 'bg-white/5 text-gray-400 group-hover:text-white'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold"
                            :class="activeGateway === 'external' ? 'text-white' : 'text-gray-400'">
                            <?php echo __('wa_gateway_official'); ?>
                        </h3>
                    </div>
                    <p class="text-sm leading-relaxed"
                        :class="activeGateway === 'external' ? 'text-indigo-100/70' : 'text-gray-500'">
                        <?php echo __('wa_gateway_official_desc'); ?>
                    </p>

                    <!-- Active Indicator -->
                    <div x-show="activeGateway === 'external'" class="absolute top-4 right-4 animate-scale-in">
                        <div class="w-2 h-2 rounded-full bg-indigo-500 shadow-[0_0_10px_#6366f1]"></div>
                    </div>
                </div>
            </div>

            <!-- External Config Section (Only if external selected) -->
            <div x-show="activeGateway === 'external'"
                x-transition:enter="transition ease-out duration-300 translate-y-4 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                class="glass-card rounded-[2.5rem] border border-white/5 p-8 md:p-10 space-y-8 animate-fade-in">

                <div class="flex items-center justify-between mb-4 border-b border-white/5 pb-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white"><?php echo __('wa_external_config'); ?></h3>
                            <p class="text-gray-500 text-xs mt-1"><?php echo __('wa_switch_mode_warning'); ?></p>
                        </div>
                    </div>

                    <select name="external_provider" x-model="provider"
                        class="bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-indigo-500/50 transition-all font-bold text-sm">
                        <option value="meta" class="bg-[#1a1a1a]"><?php echo __('wa_provider_meta'); ?></option>
                        <option value="twilio" class="bg-[#1a1a1a]"><?php echo __('wa_provider_twilio'); ?></option>
                        <option value="ultramsg" class="bg-[#1a1a1a]"><?php echo __('wa_provider_ultramsg'); ?></option>
                    </select>
                </div>

                <!-- Provider Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Meta Fields -->
                    <template x-if="provider === 'meta'">
                        <div class="col-span-2 grid md:grid-cols-2 gap-x-6 gap-y-8">
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_app_id'); ?></label>
                                <input type="text" name="meta_app_id"
                                    value="<?php echo htmlspecialchars($config['app_id'] ?? ''); ?>"
                                    placeholder="XXXXXXXXXXXX"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_phone_number_id'); ?></label>
                                <input type="text" name="meta_phone_id"
                                    value="<?php echo htmlspecialchars($config['phone_id'] ?? ''); ?>"
                                    placeholder="XXXXXXXXXXXX"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                            <div class="space-y-2 col-span-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_access_token'); ?></label>
                                <input type="password" name="meta_token"
                                    value="<?php echo htmlspecialchars($config['token'] ?? ''); ?>"
                                    placeholder="EAAG..."
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                        </div>
                    </template>

                    <!-- Twilio Fields -->
                    <template x-if="provider === 'twilio'">
                        <div class="col-span-2 grid md:grid-cols-2 gap-x-6 gap-y-8">
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_sid'); ?></label>
                                <input type="text" name="twilio_sid"
                                    value="<?php echo htmlspecialchars($config['sid'] ?? ''); ?>" placeholder="AC..."
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_auth_token'); ?></label>
                                <input type="password" name="twilio_token"
                                    value="<?php echo htmlspecialchars($config['token'] ?? ''); ?>"
                                    placeholder="••••••••"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                            <div class="space-y-2 col-span-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_sender_phone'); ?></label>
                                <input type="text" name="twilio_phone"
                                    value="<?php echo htmlspecialchars($config['phone'] ?? ''); ?>"
                                    placeholder="+1234567890"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                        </div>
                    </template>

                    <!-- UltraMsg Fields -->
                    <template x-if="provider === 'ultramsg'">
                        <div class="col-span-2 grid md:grid-cols-2 gap-x-6 gap-y-8">
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_instance_id'); ?></label>
                                <input type="text" name="ultra_instance_id"
                                    value="<?php echo htmlspecialchars($config['instance_id'] ?? ''); ?>"
                                    placeholder="instanceXXXX"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('wa_token'); ?></label>
                                <input type="password" name="ultra_token"
                                    value="<?php echo htmlspecialchars($config['token'] ?? ''); ?>"
                                    placeholder="••••••••"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all">
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-4">
                <button type="submit"
                    class="w-full md:w-auto bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-5 px-16 rounded-[2rem] shadow-xl shadow-indigo-600/20 transition-all transform active:scale-95 flex items-center justify-center gap-3 group">
                    <span class="text-lg font-black uppercase tracking-widest"><?php echo __('save_changes'); ?></span>
                    <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </form>

        <!-- Dynamic Context Note -->
        <div class="mt-8 p-6 glass-card rounded-2xl border border-white/5 bg-white/[0.02] flex items-start gap-4 animate-fade-in"
            style="animation-delay: 200ms;">
            <div
                class="w-10 h-10 rounded-xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h4 class="text-white font-bold mb-1"
                    x-text="activeGateway === 'qr' ? '<?php echo __('wa_gateway_qr'); ?>' : '<?php echo __('wa_gateway_official'); ?>'">
                </h4>
                <p class="text-gray-400 text-xs leading-relaxed" x-show="activeGateway === 'qr'">
                    <?php echo __('wa_gateway_qr_note'); ?>
                </p>
                <p class="text-gray-400 text-xs leading-relaxed" x-show="activeGateway === 'external'">
                    <?php echo __('wa_gateway_external_note'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>