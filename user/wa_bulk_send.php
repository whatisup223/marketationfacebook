<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch current user settings
$user_settings = [];
$gateway_mode = 'qr';
try {
    $stmt = $pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $gateway_mode = $user_settings['active_gateway'] ?? 'qr';
} catch (PDOException $e) {
    error_log("DB Error in wa_bulk_send settings: " . $e->getMessage());
}

// Fetch WhatsApp accounts for selection (only for QR mode check)
$wa_accounts = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE user_id = ? AND status = 'connected' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $wa_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB Error in wa_bulk_send accounts: " . $e->getMessage());
}

// Smart Default: If no QR accounts but Twilio is configured, switch to Twilio
if (empty($wa_accounts) && $gateway_mode === 'qr') {
    if (!empty($user_settings['twilio_account_sid'])) {
        $gateway_mode = 'twilio';
    }
}

// Handle API Status Check (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'check_api_status') {
    header('Content-Type: application/json');

    // Helper to make curl requests
    function check_url($url, $auth_header = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        // Disable SSL verify only for localhost testing if needed, but risky for prod
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        if ($auth_header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $auth_header);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_msg = curl_error($ch);
        curl_close($ch);

        return ['code' => $http_code, 'body' => $response, 'error' => $error_msg];
    }

    try {
        if ($gateway_mode === 'qr') {
            throw new Exception(__('wa_err_qr_mode'));
        }

        $config = json_decode($user_settings['external_config'] ?? '{}', true);
        $provider = $user_settings['external_provider'] ?? 'meta';
        $status_data = [];

        if ($provider === 'meta') {
            if (empty($config['phone_id']) || empty($config['token']))
                throw new Exception(__('wa_err_missing_meta'));
            $url = "https://graph.facebook.com/v17.0/" . $config['phone_id'] . "?access_token=" . $config['token'];
            $res = check_url($url);

            if ($res['code'] === 200) {
                $body = json_decode($res['body'], true);
                $status_data = ['name' => $body['verified_name'] ?? 'Meta Account', 'number' => $body['display_phone_number'] ?? ''];
            } else {
                $err_body = json_decode($res['body'], true);
                $api_msg = $err_body['error']['message'] ?? __('wa_unknown_error');
                throw new Exception(__('wa_err_meta_api') . " ({$res['code']}): " . $api_msg);
            }

        } elseif ($provider === 'twilio') {
            if (empty($config['sid']) || empty($config['token']))
                throw new Exception(__('wa_err_missing_twilio'));
            $url = "https://api.twilio.com/2010-04-01/Accounts/" . $config['sid'] . ".json";
            $auth = ["Authorization: Basic " . base64_encode($config['sid'] . ":" . $config['token'])];
            $res = check_url($url, $auth);

            if ($res['code'] === 200) {
                $body = json_decode($res['body'], true);
                if (isset($body['status']) && $body['status'] == 404)
                    throw new Exception(__('wa_err_twilio_404'));
                $status_data = ['name' => $body['friendly_name'] ?? 'Twilio Account', 'number' => 'Active'];
            } else {
                $err_body = json_decode($res['body'], true);
                // Check if message is generic "Check credentials" and translate it, otherwise keep API msg
                $api_msg_raw = $err_body['message'] ?? 'Check Credentials';
                $api_msg = ($api_msg_raw === 'Check Credentials') ? __('wa_err_check_credentials') : $api_msg_raw;

                throw new Exception(__('wa_err_twilio_api') . " ({$res['code']}): " . $api_msg);
            }

        } elseif ($provider === 'ultramsg') {
            if (empty($config['instance_id']) || empty($config['token']))
                throw new Exception(__('wa_err_missing_ultra'));
            $url = "https://api.ultramsg.com/" . $config['instance_id'] . "/instance/status?token=" . $config['token'];
            $res = check_url($url);

            if ($res['code'] === 200) {
                $body = json_decode($res['body'], true);
                $status_data = ['name' => 'UltraMsg Instance', 'number' => $body['status']['text'] ?? 'Connected'];
            } else {
                throw new Exception(__('wa_err_ultra_api') . " ({$res['code']}): " . $res['error']);
            }
        }

        echo json_encode(['status' => 'connected', 'data' => $status_data]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_campaign'])) {
    try {
        // Validate required fields
        if (empty($_POST['campaign_name']) || empty($_POST['message'])) {
            throw new Exception(__('wa_error_missing_fields'));
        }

        // Get selected gateway mode from form
        $gateway_mode = $_POST['gateway_mode'] ?? 'qr';

        // Parse numbers from textarea
        $numbers_raw = $_POST['numbers'] ?? '';
        $numbers = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $numbers_raw)));

        // Handle TXT Upload for Numbers (Robust Regex Method)
        if (isset($_FILES['numbers_txt']) && $_FILES['numbers_txt']['error'] === UPLOAD_ERR_OK) {
            $txtFile = $_FILES['numbers_txt']['tmp_name'];

            // Read entire file content
            $fileContent = file_get_contents($txtFile);

            if ($fileContent !== false) {
                // Extract sequences of 10 to 15 digits
                preg_match_all('/\d{10,15}/', $fileContent, $matches);

                if (!empty($matches[0])) {
                    $numbers = array_merge($numbers, $matches[0]);
                }
            }
        }

        // Unique and Filter Empty
        $numbers = array_unique(array_filter($numbers));

        if (empty($numbers)) {
            throw new Exception(__('wa_no_leads_selected') ?: "Please provide at least one phone number");
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

        // Check for empty post-processing
        if (empty($numbers)) {
            throw new Exception(__('wa_no_leads_selected') ?: "No valid numbers found in the provided file.");
        }

        // Re-index array to ensure it encodes as a JSON list [..], not object {..}
        $numbers = array_values($numbers);

        // Insert campaign into database
        $stmt = $pdo->prepare("
            INSERT INTO wa_campaigns (
                user_id, campaign_name, gateway_mode, selected_accounts,
                message, media_type, media_url, media_file_path,
                numbers, delay_min, delay_max, switch_every,
                batch_size, batch_delay,
                total_count, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        // Encode Numbers safely
        // Ensure all numbers are strings before encoding
        $numbers_as_strings = array_map('strval', $numbers);
        $json_numbers = json_encode($numbers_as_strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json_numbers === false) {
            throw new Exception("Error encoding numbers: " . json_last_error_msg());
        }

        $stmt->execute([
            $user_id,
            $_POST['campaign_name'],
            $gateway_mode,
            $selected_accounts,
            $_POST['message'],
            $_POST['media_type'] ?? 'text',
            $_POST['media_url'] ?? null,
            $media_file_path,
            $json_numbers,
            $_POST['delay_min'] ?? 10,
            $_POST['delay_max'] ?? 25,
            $_POST['switch_every'] ?? null,
            $_POST['batch_size'] ?? 50,
            $_POST['batch_delay'] ?? 60,
            count($numbers)
        ]);

        $campaign_id = $pdo->lastInsertId();

        // Handle AJAX Request (for Progress Bar)
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'redirect' => "wa_campaign_runner.php?id=$campaign_id"]);
            exit;
        }

        // Redirect to campaign runner (Standard POST)
        header("Location: wa_campaign_runner.php?id=$campaign_id");
        exit;

    } catch (Exception $e) {
        $error_message = $e->getMessage();

        // Handle AJAX Error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error_message]);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>


<style>
    [x-cloak] {
        display: none !important;
    }
</style>
<div id="main-user-container" class="main-user-container flex min-h-screen pt-4" x-data="{ 
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
    gateway: '<?php echo $gateway_mode; ?>',
    provider: '<?php echo $user_settings['external_provider'] ?? 'meta'; ?>',
    
    // API Status Logic
    apiStatus: 'idle', // idle, checking, connected, error
    apiDetails: null,
    apiError: '',

    init() {
        this.gateway = '<?php echo $gateway_mode; ?>';
        if (this.gateway !== 'qr') {
            this.checkApiStatus();
        }
    },

    async checkApiStatus() {
        this.apiStatus = 'checking';
        this.apiError = '';
        
        const formData = new FormData();
        formData.append('action', 'check_api_status');

        try {
            const res = await fetch('wa_bulk_send.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.status === 'connected') {
                this.apiStatus = 'connected';
                this.apiDetails = data.data;
            } else {
                this.apiStatus = 'error';
                this.apiError = data.message || 'Unknown Error';
            }
        } catch (e) {
            this.apiStatus = 'error';
            this.apiError = 'Network or Server Error';
        }
    },
    
    // Upload State
    isUploading: false,
    uploadProgress: 0,
    errorMessage: '<?php echo htmlspecialchars($error_message ?? ''); ?>',

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
        processed = processed.replace(/\{\{name\}\}/g, 'أحمد / Ahmed');
        processed = processed.replace(/\{([^{}]+)\}/g, (match, options) => {
            return options.split('|')[0];
        });
        return processed;
    },

    submitCampaign(e) {
        this.isUploading = true;
        this.uploadProgress = 0;
        this.errorMessage = '';

        const formData = new FormData(e.target);
        formData.append('start_campaign', '1');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                this.uploadProgress = Math.round((event.loaded / event.total) * 100);
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.status === 'success') {
                        window.location.href = res.redirect;
                    } else {
                        this.errorMessage = res.message || 'Unknown error occurred';
                        this.isUploading = false;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                } catch (err) {
                    this.errorMessage = 'Invalid Server Response';
                    this.isUploading = false;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } else {
                try {
                    const res = JSON.parse(xhr.responseText);
                    this.errorMessage = res.message || 'Server Error';
                } catch (err) {
                    this.errorMessage = 'Server Error (' + xhr.status + ')';
                }
                this.isUploading = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };

        xhr.onerror = () => {
            this.errorMessage = 'Network Connection Error';
            this.isUploading = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        xhr.send(formData);
    }
}" x-init="init()">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <form method="POST" enctype="multipart/form-data" class="flex-1 min-w-0 p-4 md:p-8 relative"
        @submit.prevent="submitCampaign">
        <!-- Error Message (Alpine) -->
        <div x-show="errorMessage" x-cloak
            class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-red-400 text-sm animate-fade-in flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span x-text="errorMessage"></span>
            </div>
            <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <!-- Upload Progress Overlay -->
        <div x-show="isUploading" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm transition-all">
            <div
                class="bg-gray-900 border border-white/10 rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl relative overflow-hidden">
                <!-- Animated Blob -->
                <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-32 bg-indigo-500 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-pulse">
                </div>

                <div class="relative z-10">
                    <div class="w-16 h-16 mx-auto mb-6 relative">
                        <svg class="animate-spin w-16 h-16 text-indigo-500" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </div>

                    <h3 class="text-xl font-bold text-white mb-2"><?php echo __('wa_uploading'); ?></h3>
                    <p class="text-gray-400 text-sm mb-6"><?php echo __('wa_processing_campaign'); ?></p>

                    <!-- Progress Bar -->
                    <div class="w-full bg-white/10 rounded-full h-2 mb-2 overflow-hidden">
                        <div class="bg-indigo-500 h-2 rounded-full transition-all duration-300 ease-out"
                            :style="'width: ' + uploadProgress + '%'"></div>
                    </div>
                    <p class="text-xs text-indigo-400 font-bold" x-text="uploadProgress + '%'"></p>
                </div>
            </div>
        </div>

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


        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Side: Config -->
            <div class="flex-1 space-y-8">

                <!-- Active Gateway Info Card (Read-Only) -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8 animate-fade-in mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg transition-colors"
                            :class="gateway === 'qr' ? 'bg-green-500 shadow-green-500/20' : 'bg-indigo-500 shadow-indigo-500/20'">
                            <!-- QR Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                x-show="gateway === 'qr'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1l-3 3h2v5h2v-5h2l-3-3V4zM8 4h8a2 2 0 012 2v12a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z" />
                            </svg>
                            <!-- Official Icon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                x-show="gateway !== 'qr'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white mb-1">
                                <span x-show="gateway === 'qr'"><?php echo __('wa_gateway_qr'); ?></span>
                                <span x-show="gateway !== 'qr'"><?php echo __('wa_gateway_official'); ?></span>
                                <span
                                    class="text-xs font-normal text-gray-500 ml-2 border border-white/10 px-2 py-0.5 rounded-lg bg-white/5"><?php echo __('wa_active_mode'); ?></span>
                            </h3>
                            <p class="text-sm text-gray-400">
                                <span x-show="gateway === 'qr'"><?php echo __('wa_gateway_qr_desc'); ?></span>
                                <span x-show="gateway !== 'qr'"><?php echo __('wa_gateway_official_desc'); ?></span>
                            </p>
                        </div>
                        <!-- Hidden Input to pass gateway mode -->
                        <input type="hidden" name="gateway_mode" :value="gateway">
                    </div>
                </div>

                <!-- Account Selection Card (Moved & Enhanced) -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8 animate-fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center text-green-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">
                                    <span x-show="gateway === 'qr'"><?php echo __('wa_select_accounts'); ?></span>
                                    <span x-show="gateway !== 'qr'"><?php echo __('api_status'); ?></span>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1"
                                    x-text="gateway === 'qr' ? '<?php echo __('wa_select_qr_accs'); ?>' : '<?php echo __('wa_official_api_info'); ?>'">
                                </p>
                            </div>
                        </div>

                        <!-- Counter for QR -->
                        <div x-show="gateway === 'qr'"
                            class="bg-indigo-500/10 px-3 py-1.5 rounded-lg border border-indigo-500/20">
                            <span class="text-xs font-bold text-indigo-400"
                                x-text="selectedAccounts.length + ' selected'"></span>
                        </div>
                    </div>

                    <!-- Content for QR Mode -->
                    <div x-show="gateway === 'qr'" class="space-y-4">
                        <?php if (empty($wa_accounts)): ?>
                            <div class="text-center p-6 border-2 border-dashed border-white/5 rounded-2xl">
                                <p class="text-gray-400 text-sm mb-4">لا توجد حسابات واتساب مرتبطة</p>
                                <a href="wa_accounts.php"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-xl text-sm font-bold transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    ربط حساب جديد
                                </a>
                            </div>
                        <?php else: ?>
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[200px] overflow-y-auto custom-scrollbar pr-1">
                                <?php foreach ($wa_accounts as $acc): ?>
                                    <label class="group relative cursor-pointer">
                                        <input type="checkbox" name="selected_accounts[]" value="<?php echo $acc['id']; ?>"
                                            x-model="selectedAccounts" class="peer hidden">
                                        <div
                                            class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/10 peer-checked:border-green-500/50 peer-checked:bg-green-500/10 transition-all group-hover:bg-white/10">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 shrink-0">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm text-white font-bold truncate">
                                                    <?php echo $acc['account_name'] ?: 'WhatsApp Account'; ?>
                                                </p>
                                                <p class="text-[10px] text-gray-500 font-mono">
                                                    +<?php echo $acc['phone']; ?>
                                                </p>
                                            </div>
                                            <div
                                                class="mr-auto w-5 h-5 rounded-full border-2 border-white/20 peer-checked:border-green-500 peer-checked:bg-green-500 flex items-center justify-center transition-all">
                                                <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content for Official API (Dynamic Status) -->
                    <div x-show="gateway !== 'qr'" class="relative">
                        <!-- Checking State -->
                        <div x-show="apiStatus === 'checking'"
                            class="bg-indigo-500/5 rounded-2xl p-8 border border-indigo-500/10 text-center animate-pulse">
                            <div class="w-16 h-16 mx-auto mb-4 relative">
                                <div class="absolute inset-0 rounded-full border-4 border-indigo-500/20"></div>
                                <div class="absolute inset-0 rounded-full border-4 border-t-indigo-500 animate-spin">
                                </div>
                            </div>
                            <h4 class="text-white font-bold mb-1">
                                <?php echo __('wa_api_checking'); ?>
                            </h4>
                            <p class="text-xs text-gray-500">
                                <?php echo __('wa_api_verifying'); ?>
                            </p>
                        </div>

                        <!-- Connected State -->
                        <div x-show="apiStatus === 'connected'"
                            class="bg-emerald-500/10 rounded-2xl p-6 border border-emerald-500/20 text-center relative overflow-hidden group">
                            <div
                                class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl -mr-10 -mt-10 pointer-events-none">
                            </div>

                            <div
                                class="w-16 h-16 rounded-full bg-emerald-500/20 mx-auto mb-4 flex items-center justify-center text-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.2)]">
                                <!-- Meta Icon -->
                                <svg x-show="provider === 'meta'" class="w-8 h-8" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M16.797 20.465C15.657 21.053 14.288 21.6 12.006 21.6 6.366 21.6 2.41 17.886 2.41 12.215c0-6 4.316-10.465 10.37-10.465 5.922 0 8.674 4.162 8.674 8.766 0 5.446-4.992 6.848-7.792 6.848-2.618 0-3.328-1.572-3.328-1.572.766-3.056 1.488-5.322 1.956-6.606.58-1.59 1.15-2.074 2.126-2.074 1.112 0 1.96.906 1.96 2.502 0 3.39-2.222 7.794-5.114 7.794-1.636 0-2.28-1.396-2.28-2.586 0-2.738 2.05-6.236 4.336-6.236.95 0 1.544.52 1.544 1.396 0 1.076-1.042 3.194-2.28 3.194-.486 0-.668-.224-.668-.224.282-1.082.686-2.31 1.258-2.31.066 0 .098.01.126.02-.3-.504-.556-.632-.906-.632-1.054 0-1.874 1.636-1.874 3.016 0 .8.27 1.58.916 1.58.986 0 2.92-1.748 2.92-4.254 0-.89-.576-1.37-1.326-1.37-1.168 0-2.392 1.168-2.392 2.768 0 .546.126 1.07.288 1.488-.936 3.636-1.556 5.564-1.556 5.564-.32 1.484-.044 2.124.088 2.378-1.812-.662-3.666-2.454-3.666-5.836 0-4.636 3.396-7.818 7.82-7.818 4.284 0 6.036 3.298 6.036 6.332 0 4.198-2.384 6.34-5.366 6.34-1.92 0-2.906-1.272-2.906-1.272s.542 2.668.644 3.036c.142.504.792 2.3 2.152 2.3 2.158 0 3.864-1.22 4.902-2.348l-1.054-1.666z" />
                                </svg>
                                <!-- Twilio Icon -->
                                <svg x-show="provider === 'twilio'" class="w-8 h-8" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm-2.079 17.585c-.947.548-2.146.223-2.693-.725-.548-.946-.224-2.145.724-2.693.948-.548 2.147-.223 2.694.725.548.947.224 2.146-.725 2.693zm-2.71-3.774c-1.424-.044-2.544-1.238-2.499-2.663.044-1.425 1.238-2.545 2.662-2.5 1.426.044 2.546 1.238 2.501 2.663-.045 1.425-1.239 2.545-2.664 2.5zm6.477 3.315c-1.378.369-2.802-.44-3.171-1.819-.37-1.378.439-2.802 1.818-3.171 1.379-.37 2.802.439 3.171 1.818.37 1.379-.439 2.802-1.818 3.172zm2.083-3.08c-1.121 1.054-2.895 1.01-3.95-.112-1.055-1.121-1.011-2.894.111-3.949 1.121-1.055 2.895-1.011 3.95.111 1.054 1.122 1.01 2.895-.111 3.95z" />
                                </svg>
                                <!-- UltraMsg/Generic Icon -->
                                <svg x-show="provider === 'ultramsg' || !['meta','twilio'].includes(provider)"
                                    class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                </svg>
                            </div>

                            <h4 class="text-xl font-black text-white mb-1 tracking-tight">
                                <?php echo __('wa_api_connected'); ?>
                            </h4>
                            <p class="text-emerald-400 font-bold text-sm mb-1"
                                x-text="apiDetails?.name || 'Official Account'"></p>
                            <p class="text-gray-400 text-xs font-mono"
                                x-text="apiDetails?.number ? 'ID: ' + apiDetails.number : 'Ready to Send'"></p>

                            <div
                                class="mt-4 inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 rounded-full border border-emerald-500/20">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] uppercase font-bold text-emerald-300">
                                    <?php echo __('wa_api_operational'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Error State -->
                        <div x-show="apiStatus === 'error'"
                            class="bg-red-500/5 rounded-2xl p-6 border border-red-500/20 text-center relative">
                            <div
                                class="w-16 h-16 rounded-full bg-red-500/10 mx-auto mb-4 flex items-center justify-center text-red-500">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>

                            <h4 class="text-lg font-bold text-white mb-2">
                                <?php echo __('wa_api_failed'); ?>
                            </h4>
                            <p class="text-red-300 text-sm mb-4 px-4 bg-red-500/5 py-2 rounded-lg break-all font-mono"
                                x-text="apiError"></p>

                            <button type="button" @click="checkApiStatus()"
                                class="px-6 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-sm font-bold text-white transition-all flex items-center gap-2 mx-auto">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                    </path>
                                </svg>
                                <?php echo __('wa_retry_connection'); ?>
                            </button>

                            <p class="text-[10px] text-gray-500 mt-4">
                                <?php echo __('wa_check_settings'); ?> <a href="wa_settings.php"
                                    class="text-indigo-400 hover:underline">
                                    <?php echo __('whatsapp_settings'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

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
                            <button type="button" @click="importMode = 'txt'"
                                :class="importMode === 'txt' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all">
                                <?php echo __('wa_import_txt'); ?>
                            </button>
                        </div>
                    </div>

                    <div x-show="importMode === 'paste'" class="animate-fade-in">
                        <textarea x-model="numbers" name="numbers" rows="6"
                            placeholder="<?php echo __('wa_numbers_placeholder'); ?>"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all font-mono text-sm"></textarea>
                        <p class="mt-2 text-xs text-gray-500"><?php echo __('wa_numbers_hint'); ?></p>
                    </div>

                    <div x-show="importMode === 'txt'" class="animate-fade-in">
                        <div
                            class="border-2 border-dashed border-white/10 rounded-2xl p-8 text-center hover:border-indigo-500/30 transition-colors cursor-pointer relative group">
                            <input type="file" name="numbers_txt" accept=".txt"
                                class="absolute inset-0 opacity-0 cursor-pointer">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4 group-hover:scale-110 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-white font-bold"><?php echo __('wa_txt_drop'); ?></p>
                            <p class="text-gray-500 text-xs mt-1"><?php echo __('wa_txt_hint'); ?></p>
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
                                <div
                                    class="animate-fade-in col-span-1 md:col-span-2 lg:col-span-3 border-t border-white/5 pt-4 mt-2">
                                    <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-4">Advanced
                                        Sending Options</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label
                                                class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest"><?php echo __('wa_switch_every'); ?></label>
                                            <input type="number" name="switch_every" value="5" min="1"
                                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none"
                                                title="Switch sender account after every X messages">
                                            <p class="text-[9px] text-gray-600 mt-1">Leave empty to disable rotation</p>
                                        </div>
                                        <div>
                                            <label
                                                class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest">Messages
                                                per Batch</label>
                                            <input type="number" name="batch_size" value="50" min="1"
                                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none">
                                        </div>
                                        <div>
                                            <label
                                                class="text-xs font-bold text-gray-500 block mb-2 uppercase tracking-widest">Delay
                                                After Batch (Sec)</label>
                                            <input type="number" name="batch_delay" value="60" min="0"
                                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-indigo-500/50 outline-none">
                                        </div>
                                    </div>
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
