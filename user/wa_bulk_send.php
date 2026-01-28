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
$gateway_mode = $user_settings['active_gateway'] ?? 'qr';

// Fetch WhatsApp accounts for selection (only for QR mode)
$wa_accounts = [];
if ($gateway_mode === 'qr') {
    $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE user_id = ? AND status = 'connected' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $wa_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_campaign'])) {
    try {
        // Validate required fields
        if (empty($_POST['campaign_name']) || empty($_POST['message'])) {
            throw new Exception("Campaign name and message are required");
        }

        // Parse numbers from textarea (one per line or comma-separated)
        $numbers_raw = $_POST['numbers'] ?? '';
        $numbers = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $numbers_raw)));

        if (empty($numbers)) {
            throw new Exception("Please provide at least one phone number");
        }

        // Handle file upload for local media
        $media_file_path = null;
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/wa_media/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('wa_media_') . '.' . $file_extension;
            $media_file_path = 'uploads/wa_media/' . $file_name;

            if (!move_uploaded_file($_FILES['media_file']['tmp_name'], __DIR__ . '/../' . $media_file_path)) {
                throw new Exception("Failed to upload media file");
            }
        }

        // Prepare selected accounts (for QR mode)
        $selected_accounts = null;
        if ($gateway_mode === 'qr' && isset($_POST['selected_accounts'])) {
            $selected_accounts = json_encode($_POST['selected_accounts']);
        }

        // Insert campaign into database
        $stmt = $pdo->prepare("
            INSERT INTO wa_campaigns (
                user_id, campaign_name, gateway_mode, selected_accounts,
                message, media_type, media_url, media_file_path,
                numbers, delay_min, delay_max, switch_every,
                total_count, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $user_id,
            $_POST['campaign_name'],
            $gateway_mode,
            $selected_accounts,
            $_POST['message'],
            $_POST['media_type'] ?? 'text',
            $_POST['media_url'] ?? null,
            $media_file_path,
            json_encode($numbers),
            $_POST['delay_min'] ?? 10,
            $_POST['delay_max'] ?? 25,
            $_POST['switch_every'] ?? null,
            count($numbers)
        ]);

        $campaign_id = $pdo->lastInsertId();

        // Redirect to campaign runner
        header("Location: wa_campaign_runner.php?id=$campaign_id");
        exit;

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{ 
    message: '', 
    numbers: '', 
    campaignName: 'حملة واتساب جديدة ' + new Date().toLocaleString(),
    selectedAccounts: [],
    importMode: 'paste',
    mediaType: 'text',
    mediaUrl: '',
    mediaPreviewUrl: '',
    lat: '',
    lng: '',

    updateMediaPreview(e) {
        if (this.mediaType.endsWith('_local')) {
            const file = e.target.files[0];
            if (file) {
                this.mediaPreviewUrl = URL.createObjectURL(file);
            }
        }
    },

    processPreview(text) {
        if (!text) return '';
        let processed = text;
        // Replace {{name}} with a sample name
        processed = processed.replace(/\{\{name\}\}/g, 'أحمد / Ahmed');
        // Handle {hi|hello} spin syntax - pick first one for preview
        processed = processed.replace(/\{([^{}]+)\}/g, (match, options) => {
            return options.split('|')[0];
        });
        return processed;
    }
}">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <form method="POST" enctype="multipart/form-data" class="flex-1 min-w-0 p-4 md:p-8 relative">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-96 h-96 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 -right-4 w-96 h-96 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000 pointer-events-none">
        </div>

        <!-- Header & Account Selection Unified -->
        <div class="flex flex-col xl:flex-row gap-6 mb-10 relative z-10 animate-fade-in">
            <div class="flex-1">
                <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                    <?php echo __('wa_bulk_send'); ?>
                </h1>
                <p class="text-gray-400 text-lg"><?php echo __('wa_bulk_send_desc'); ?></p>
            </div>

            <!-- Minified Account Selection Card -->
            <div class="xl:w-[450px] glass-card rounded-3xl border border-white/5 p-4 flex flex-col justify-center">
                <div class="flex items-center justify-between mb-3 px-2">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span
                            class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </span>
                        <?php echo __('wa_select_accounts'); ?>
                    </h3>
                    <span class="text-[10px] text-gray-500 font-bold bg-white/5 px-2 py-1 rounded-lg"
                        x-text="selectedAccounts.length + ' selected'"></span>
                </div>

                <div class="flex flex-wrap gap-2 max-h-[100px] overflow-y-auto custom-scrollbar p-1">
                    <?php if ($gateway_mode === 'qr'): ?>
                        <?php if (empty($wa_accounts)): ?>
                            <a href="wa_accounts.php"
                                class="text-[10px] text-yellow-500 bg-yellow-500/10 border border-yellow-500/20 px-3 py-2 rounded-xl w-full text-center">
                                لم نجد حسابات مرتبطة. اربط حساب الآن
                            </a>
                        <?php endif; ?>
                        <?php foreach ($wa_accounts as $acc): ?>
                            <label class="relative cursor-pointer">
                                <input type="checkbox" name="selected_accounts[]" value="<?php echo $acc['id']; ?>"
                                    x-model="selectedAccounts" class="peer hidden">
                                <div
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 peer-checked:border-green-500/50 peer-checked:bg-green-500/10 transition-all">
                                    <div class="w-6 h-6 rounded-lg bg-gray-800 flex items-center justify-center text-gray-400">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                        </svg>
                                    </div>
                                    <p class="text-[10px] text-white font-bold truncate max-w-[80px]">
                                        <?php echo $acc['account_name'] ?: '+' . $acc['phone']; ?>
                                    </p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div
                            class="px-3 py-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-[10px] text-indigo-400 w-full flex items-center justify-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Official API: <?php echo strtoupper($user_settings['external_provider']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Side: Config -->
            <div class="flex-1 space-y-8">

                <!-- Campaign Name Card -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                    <div class="flex flex-col gap-4">
                        <label
                            class="text-xs font-bold text-gray-500 uppercase tracking-widest pl-2"><?php echo __('campaign_name'); ?></label>
                        <input type="text" x-model="campaignName" name="campaign_name"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white text-lg font-bold focus:outline-none focus:border-indigo-500/50 transition-all font-sans"
                            placeholder="Campaign Name" required>
                    </div>
                </div>

                <!-- Mode Context Card -->
                <div
                    class="glass-card rounded-[2.5rem] border border-white/5 p-6 bg-indigo-500/5 relative overflow-hidden">
                    <div class="flex items-start gap-4 relative z-10">
                        <div
                            class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-white font-bold mb-1">
                                <?php echo ($gateway_mode === 'qr' ? __('wa_gateway_qr') : __('wa_gateway_official')); ?>
                            </h4>
                            <p class="text-gray-500 text-xs leading-relaxed">
                                <?php echo ($gateway_mode === 'qr' ? __('wa_gateway_qr_note') : __('wa_gateway_external_note')); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Numbers List Card -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white flex items-center gap-3">
                            <span
                                class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            <?php echo __('wa_numbers_list'); ?>
                        </h3>
                        <div class="flex rounded-xl bg-white/5 p-1 border border-white/10">
                            <button type="button" @click="importMode = 'paste'"
                                :class="importMode === 'paste' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all">
                                <?php echo __('wa_paste_numbers'); ?>
                            </button>
                            <button type="button" @click="importMode = 'csv'"
                                :class="importMode === 'csv' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all">
                                <?php echo __('wa_import_csv'); ?>
                            </button>
                        </div>
                    </div>

                    <div x-show="importMode === 'paste'" class="animate-fade-in">
                        <textarea x-model="numbers" name="numbers" rows="6"
                            placeholder="<?php echo __('wa_numbers_placeholder'); ?>"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all font-mono text-sm"></textarea>
                        <p class="mt-2 text-xs text-gray-500"><?php echo __('wa_numbers_hint'); ?></p>
                    </div>

                    <div x-show="importMode === 'csv'" class="animate-fade-in">
                        <div
                            class="border-2 border-dashed border-white/10 rounded-2xl p-8 text-center hover:border-indigo-500/30 transition-colors cursor-pointer relative group">
                            <input type="file" class="absolute inset-0 opacity-0 cursor-pointer">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4 group-hover:scale-110 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-white font-bold"><?php echo __('wa_csv_drop'); ?></p>
                            <p class="text-gray-500 text-xs mt-1"><?php echo __('wa_csv_hint'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Message Content & Media -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </span>
                        <?php echo __('wa_message_content'); ?>
                    </h3>

                    <div class="space-y-6">
                        <!-- Media Type Selector -->
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                            <template x-for="type in ['text', 'image', 'video', 'document', 'location']">
                                <button type="button"
                                    @click="mediaType = (mediaType.includes('_local') ? type + '_local' : type)"
                                    :class="(mediaType.replace('_local','')) === type ? 'bg-indigo-600 shadow-lg shadow-indigo-600/20 text-white border-transparent' : 'bg-white/5 text-gray-400 border-white/5'"
                                    class="p-4 rounded-2xl border transition-all flex flex-col items-center gap-2 group hover:bg-white/10">
                                    <span class="text-xs font-black uppercase tracking-widest text-center" x-text="
                                        type === 'text' ? '<?php echo __('text'); ?>' : 
                                        type === 'image' ? '<?php echo __('image'); ?>' : 
                                        type === 'video' ? '<?php echo __('video'); ?>' : 
                                        type === 'document' ? '<?php echo __('document'); ?>' : 
                                        '<?php echo __('location'); ?>'
                                    "></span>
                                </button>
                            </template>
                        </div>

                        <!-- URL / File Input -->
                        <div x-show="['image', 'video', 'document'].includes(mediaType.replace('_local',''))"
                            class="space-y-4 animate-fade-in">
                            <div class="flex rounded-xl bg-white/5 p-1 border border-white/10 w-fit">
                                <button type="button" @click="if(!mediaType.endsWith('_local')) mediaType += '_local'"
                                    :class="mediaType.endsWith('_local') ? 'bg-indigo-600 text-white' : 'text-gray-400'"
                                    class="px-4 py-1.5 rounded-lg text-[10px] font-bold transition-all"><?php echo __('wa_media_local'); ?></button>
                                <button type="button" @click="mediaType = mediaType.replace('_local', '')"
                                    :class="!mediaType.endsWith('_local') ? 'bg-indigo-600 text-white' : 'text-gray-400'"
                                    class="px-4 py-1.5 rounded-lg text-[10px] font-bold transition-all"><?php echo __('wa_media_url'); ?></button>
                            </div>

                            <div x-show="!mediaType.endsWith('_local')" class="relative">
                                <input type="text" x-model="mediaUrl" name="media_url"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm"
                                    placeholder="https://example.com/file.jpg">
                            </div>
                            <div x-show="mediaType.endsWith('_local')" class="relative">
                                <label
                                    class="w-full flex flex-col items-center justify-center px-4 py-6 bg-white/5 border-2 border-dashed border-white/10 rounded-2xl cursor-pointer hover:border-indigo-500/30 transition-all">
                                    <svg class="w-8 h-8 text-gray-500 mb-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    <span class="text-xs text-gray-400"><?php echo __('wa_media_local'); ?></span>
                                    <input type="file" name="media_file" @change="updateMediaPreview" class="hidden">
                                </label>
                            </div>
                        </div>

                        <!-- Location Input -->
                        <div x-show="mediaType === 'location'" class="animate-fade-in">
                            <label
                                class="text-[10px] font-bold text-gray-500 uppercase block mb-1"><?php echo __('wa_location_url'); ?></label>
                            <input type="text" x-model="mediaUrl" name="location_url"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm"
                                placeholder="https://maps.google.com/?q=...">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-bold text-gray-400"><?php echo __('message_text'); ?></label>
                                <div class="flex gap-2">
                                    <button type="button" @click="message += '{{name}}'"
                                        class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] text-gray-400 hover:text-white"
                                        title="<?php echo __('wa_insert_var'); ?>">{{name}}</button>
                                    <button type="button" @click="message += '{hi|hello}'"
                                        class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] text-gray-400 hover:text-white"
                                        title="<?php echo __('wa_spin_syntax'); ?>">{hi|hello}</button>
                                </div>
                            </div>
                            <input type="hidden" name="media_type" :value="mediaType">
                            <textarea x-model="message" name="message" rows="5"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-purple-500/50 transition-all font-sans"
                                placeholder="<?php echo __('wa_msg_placeholder'); ?>"></textarea>
                        </div>

                        <!-- Settings Dividers -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-4 border-t border-white/5">
                            <div>
                                <label
                                    class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest"><?php echo __('wa_delay_min'); ?></label>
                                <input type="number" name="delay_min" value="10"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none">
                            </div>
                            <div>
                                <label
                                    class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest"><?php echo __('wa_delay_max'); ?></label>
                                <input type="number" name="delay_max" value="25"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none">
                            </div>
                            <?php if ($gateway_mode === 'qr'): ?>
                                <div x-show="selectedAccounts.length > 1" class="animate-fade-in">
                                    <label
                                        class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest"><?php echo __('wa_switch_every'); ?></label>
                                    <input type="number" name="switch_every" value="5"
                                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Immediate Direct Sending Card -->
                <div
                    class="glass-card p-6 rounded-3xl border border-white/5 bg-indigo-500/10 flex items-center gap-4 animate-fade-in relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-transparent"></div>
                    <div
                        class="w-14 h-14 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-400 shrink-0 relative z-10">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="relative z-10">
                        <p class="text-lg font-bold text-white">
                            <?php echo ($_SESSION['lang'] == 'ar' ? 'إرسال فوري ومباشر' : 'Immediate Direct Sending'); ?>
                        </p>
                        <p class="text-sm text-indigo-200/70">
                            <?php echo ($_SESSION['lang'] == 'ar' ? 'سيتم بدء الإرسال فور الضغط على الزر أدناه.' : 'Sending will start immediately after clicking the button below.'); ?>
                        </p>
                    </div>
                </div>
                <!-- Submit Section -->
                <div class="flex justify-end pt-8 pb-32">
                    <button type="submit" name="start_campaign"
                        class="w-full md:w-auto bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-5 px-20 rounded-[2rem] shadow-xl shadow-indigo-600/20 transition-all transform active:scale-95 flex items-center justify-center gap-3 group">
                        <span
                            class="text-lg font-black uppercase tracking-widest"><?php echo __('wa_start_campaign'); ?></span>
                        <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform rtl:group-hover:-translate-x-1"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Right Side: Live Preview -->
            <div class="w-full lg:w-[400px] shrink-0 relative">
                <div class="sticky top-10 h-fit space-y-6">
                    <div
                        class="relative w-full max-w-[340px] mx-auto aspect-[9/18.5] bg-[#ece5dd] rounded-[3rem] border-[12px] border-zinc-900 shadow-2xl overflow-hidden ring-1 ring-white/10">
                        <!-- WhatsApp Header Mockup -->
                        <div class="bg-[#075E54] p-4 pt-10 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-zinc-200 overflow-hidden shrink-0">
                                <img src="<?php echo $prefix; ?>assets/images/logo_icon.png"
                                    onerror="this.src='https://ui-avatars.com/api/?name=M&background=075E54&color=fff'"
                                    class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="text-white text-[11px] font-bold leading-tight truncate">ماركتيشن -
                                        Marketation</p>
                                    <svg class="w-3 h-3 text-[#25D366] shrink-0" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                    </svg>
                                </div>
                                <p class="text-white/70 text-[9px]">online</p>
                            </div>
                            <div class="flex gap-1">
                                <div class="w-0.5 h-0.5 rounded-full bg-white/80"></div>
                                <div class="w-0.5 h-0.5 rounded-full bg-white/80"></div>
                                <div class="w-0.5 h-0.5 rounded-full bg-white/80"></div>
                            </div>
                        </div>

                        <!-- Chat Background -->
                        <div class="absolute inset-0 top-20 bg-[#e5ddd5] opacity-100 -z-10"
                            style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: cover; background-repeat: no-repeat;">
                        </div>

                        <!-- Preview Bubbles -->
                        <div class="p-4 pt-10 space-y-4 h-[calc(100%-80px)] overflow-y-auto custom-scrollbar">
                            <!-- Media Preview Bubble -->
                            <div class="flex justify-end animate-fade-in"
                                x-show="mediaType.replace('_local','') !== 'text'">
                                <div
                                    class="bg-white p-1 rounded-lg shadow-sm max-w-[85%] relative border border-black/5 overflow-hidden">
                                    <template x-if="mediaType.startsWith('image')">
                                        <img :src="mediaPreviewUrl || mediaUrl"
                                            class="w-full h-auto max-h-40 object-cover rounded"
                                            onerror="this.src='https://placehold.co/400x300?text=Image+Preview'">
                                    </template>
                                    <template x-if="mediaType.startsWith('video')">
                                        <div
                                            class="relative flex items-center justify-center bg-black/10 h-32 w-48 rounded">
                                            <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                    </template>
                                    <template x-if="mediaType.startsWith('document')">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span class="text-[10px] text-gray-600 font-bold">Document.pdf</span>
                                        </div>
                                    </template>
                                    <template x-if="mediaType === 'location'">
                                        <div
                                            class="relative h-24 w-48 bg-gray-200 rounded flex flex-col items-center justify-center text-red-500">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5" />
                                            </svg>
                                            <span class="text-[8px] text-gray-500 mt-1 truncate px-2 w-full text-center"
                                                x-text="mediaUrl || 'Google Maps Link'"></span>
                                        </div>
                                    </template>
                                    <!-- Tail -->
                                    <div class="absolute -right-1.5 top-0 w-2 h-2 bg-white"
                                        style="clip-path: polygon(0 0, 0% 100%, 100% 0);"></div>
                                </div>
                            </div>

                            <div class="flex justify-end animate-fade-in" x-show="message.length > 0">
                                <div
                                    class="bg-[#dcf8c6] p-2.5 rounded-lg rounded-tr-none shadow-sm max-w-[85%] relative border border-black/5">
                                    <p class="text-zinc-800 text-[11px] leading-relaxed whitespace-pre-wrap"
                                        x-text="processPreview(message)"></p>
                                    <div class="flex justify-end items-center gap-1 mt-1">
                                        <span class="text-[8px] text-zinc-500">10:45 AM</span>
                                        <svg class="w-2.5 h-2.5 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M0 0h24v24H0z" fill="none" />
                                            <path
                                                d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-4.24l-1.41-1.41L9 13.17 5.17 9.34 3.76 10.75 9 16l13.24-13.24zM1 10.5L2.5 9l5.5 5.5L6.5 16 1 10.5z" />
                                        </svg>
                                    </div>
                                    <!-- Tail -->
                                    <div class="absolute -right-1.5 top-0 w-2 h-2 bg-[#dcf8c6]"
                                        style="clip-path: polygon(0 0, 0% 100%, 100% 0);"></div>
                                </div>
                            </div>
                            <div x-show="message.length === 0"
                                class="text-center mt-20 italic text-zinc-400 text-[10px] p-6">
                                <?php echo __('wa_msg_placeholder'); ?>
                            </div>
                        </div>

                        <!-- Bottom Input Mockup -->
                        <div
                            class="absolute bottom-0 inset-x-0 p-3 bg-[#f0f0f0] border-t border-zinc-200 flex items-center gap-2">
                            <div
                                class="flex-1 bg-white rounded-full px-4 py-1.5 flex items-center gap-2 shadow-sm border border-zinc-200">
                                <div class="text-zinc-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="flex-1 text-[9px] text-zinc-400">Type a message</div>
                                <div class="text-zinc-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                </div>
                            </div>
                            <div
                                class="w-9 h-9 rounded-full bg-[#128C7E] flex items-center justify-center text-white shadow-md">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
                                    <path
                                        d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 glass-card p-6 rounded-3xl border border-white/5 bg-white/5 mb-20">
                        <h5 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?php echo __('wa_preview'); ?>
                        </h5>
                        <p class="text-gray-400 text-xs"><?php echo __('wa_preview_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>