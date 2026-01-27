<?php
require_once __DIR__ . '/../includes/functions.php';

// Force Timezone for accurate scheduling
date_default_timezone_set('Africa/Cairo');

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Handle PRG (Post-Redirect-Get) to avoid resubmission on refresh
// Handle PRG (Post-Redirect-Get) to avoid resubmission on refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['leads']) || isset($_POST['ids_json'])) && !isset($_POST['save_campaign'])) {

    // Handle JSON input (for large datasets bypass max_input_vars)
    if (isset($_POST['ids_json']) && !empty($_POST['ids_json'])) {
        $leads = json_decode($_POST['ids_json'], true);
        if (!is_array($leads))
            $leads = [];
    } else {
        $leads = $_POST['leads'] ?? [];
    }

    $_SESSION['campaign_setup'] = [
        'leads' => $leads,
        'page_id' => $_POST['page_id']
    ];
    header("Location: create_campaign.php");
    exit;
}

// Default Pre-fills
$pre_name = __('campaign_default_name') . " " . date('Y-m-d H:i');
$pre_text = "";
$pre_image = "";
$pre_scheduled = date('Y-m-d\TH:i');

// 1. Check for Edit Mode (Returning from runner)
$edit_id = $_GET['id'] ?? 0;
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $page_id = $existing['page_id'];

        // Load Leads from Queue (Fetch internal IDs)
        $stmt = $pdo->prepare("SELECT l.id FROM campaign_queue q JOIN fb_leads l ON q.lead_id = l.id WHERE q.campaign_id = ?");
        $stmt->execute([$edit_id]);
        $selected_leads = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Pre-populate variables
        $pre_name = $existing['name'];
        $pre_text = $existing['message_text'];
        $pre_image = $existing['image_url'];
        $pre_scheduled = date('Y-m-d\TH:i', strtotime($existing['scheduled_at']));
    }
}

// 2. Get data from session or POST (Standard flow)
$setup = $_SESSION['campaign_setup'] ?? [];
if (!$edit_id) {
    if (isset($_POST['ids_json']) && !empty($_POST['ids_json'])) {
        $selected_leads = json_decode($_POST['ids_json'], true);
        if (!is_array($selected_leads))
            $selected_leads = [];
    } else {
        $selected_leads = $_POST['leads'] ?? $setup['leads'] ?? [];
    }
    $page_id = $_POST['page_id'] ?? $setup['page_id'] ?? 0;
}

if (empty($selected_leads) || empty($page_id)) {
    $show_empty_state = true;
}

// Get Page Info
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    $show_empty_state = true;
}

// Fetch Page Campaign History
$page_history = [];
if (isset($page_id) && $page_id > 0) {
    $histStmt = $pdo->prepare("SELECT * FROM campaigns WHERE page_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 5");
    $histStmt->execute([$page_id, $user_id]);
    $page_history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
    $name = trim($_POST['campaign_name']);
    $text = trim($_POST['message_text']);
    $image_url = trim($_POST['image_url']);

    // Force Run Now Logic
    $run_now = true;
    $scheduled_at = date('Y-m-d H:i:s');

    // Handle Image Upload
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/campaigns/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('camp_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
            // Get the absolute URL or relative path for the frontend
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            // Since we are in /user/, we need to go up one level
            $image_url = $protocol . "://" . $host . dirname(dirname($_SERVER['PHP_SELF'])) . "/uploads/campaigns/" . $file_name;
        }
    }

    // Create or Update Campaign
    if ($edit_id) {
        $stmt = $pdo->prepare("UPDATE campaigns SET name = ?, message_text = ?, image_url = ?, scheduled_at = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $text, $image_url, $scheduled_at, $edit_id, $user_id]);
        $campaign_id = $edit_id;
    } else {
        $initial_status = 'active'; // Always active for new
        $stmt = $pdo->prepare("INSERT INTO campaigns (user_id, page_id, name, message_text, image_url, status, total_leads, scheduled_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $page_id, $name, $text, $image_url, $initial_status, 0, $scheduled_at]);
        $campaign_id = $pdo->lastInsertId();
    }

    // Increase limits for processing large batches
    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    // Insert into Queue (Optimized Bulk Insert)
    // We assume incoming leads are Internal DB IDs (since they come from our system)
    $queueStmt = $pdo->prepare("INSERT IGNORE INTO campaign_queue (campaign_id, lead_id, status) VALUES (?, ?, 'pending')");

    $pdo->beginTransaction();
    $inserted_count = 0;

    // Chunk processing to avoid memory issues and gigantic queries
    // Although we are using prepared statements row-by-row here inside transaction for safety, 
    // we can optimize further by multi-value insert if needed, but transaction + simple insert is usually fast enough for 10k.
    // Let's stick to transaction to speed it up significantly compared to auto-commit.

    foreach ($selected_leads as $lead_id) {
        if (filter_var($lead_id, FILTER_VALIDATE_INT)) {
            $queueStmt->execute([$campaign_id, $lead_id]);
            $inserted_count++;
        }
    }

    $pdo->commit();

    // Update campaign with actual count of queue items (current total)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    $actual_total = $stmt->fetchColumn();
    $pdo->prepare("UPDATE campaigns SET total_leads = ? WHERE id = ?")->execute([$actual_total, $campaign_id]);

    // Clear setup session
    unset($_SESSION['campaign_setup']);

    // Redirect to Runner
    // Redirect to Runner
    header("Location: campaign_runner.php?id=$campaign_id");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .messenger-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .messenger-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.2);
        border-radius: 20px;
    }

    .messenger-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(99, 102, 241, 0.5);
    }
</style>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex-1 min-w-0 p-4 md:p-8">
            <!-- Back to Inbox -->
            <div class="mb-6">
                <a href="page_inbox.php?page_id=<?php echo $page_id; ?>"
                    class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors group">
                    <div
                        class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center border border-white/5 group-hover:border-indigo-500/30 group-hover:bg-indigo-500/10 transition-all">
                        <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold"><?php echo __('manage_messages'); ?></span>
                </a>
            </div>

            <!-- Breadcrumb -->
            <div class="flex items-center text-sm text-gray-400 mb-6">
                <a href="page_inbox.php?page_id=<?php echo $page_id; ?>" class="hover:text-white transition-colors">
                    <?php echo __('manage_messages'); ?>
                </a>
                <span class="mx-2 text-gray-600">/</span>
                <span class="text-white font-bold tracking-wide"><?php echo __('setup_campaign'); ?></span>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                <div class="flex-1">
                    <?php if (isset($show_empty_state) && $show_empty_state): ?>
                        <div
                            class="glass-card p-12 rounded-3xl text-center border border-white/5 flex flex-col items-center justify-center min-h-[400px]">
                            <div class="w-20 h-20 rounded-full bg-indigo-500/10 flex items-center justify-center mb-6">
                                <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                    </path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-white mb-3">
                                <?php echo __('no_leads_selected'); ?>
                            </h2>
                            <p class="text-gray-400 max-w-md mx-auto mb-8">
                                <?php echo __('please_select_leads_first'); ?>
                            </p>
                            <a href="page_inbox.php"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-bold transition-all shadow-lg hover:shadow-indigo-500/25">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                                    </path>
                                </svg>
                                <span><?php echo __('go_to_inbox'); ?></span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div
                            class="glass-card p-6 md:p-8 rounded-3xl relative overflow-hidden border border-white/5 shadow-2xl">
                            <!-- Background Glow -->
                            <div
                                class="absolute top-0 right-0 w-64 h-64 bg-indigo-600/10 blur-[100px] rounded-full -mr-20 -mt-20 pointer-events-none">
                            </div>

                            <div class="relative z-10">
                                <div class="flex items-center gap-4 mb-8">
                                    <div
                                        class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3 0 01-1.564-.317z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h1 class="text-2xl font-bold text-white tracking-tight">
                                            <?php echo __('setup_campaign'); ?>
                                        </h1>
                                        <p class="text-gray-400 text-sm">
                                            <?php echo sprintf(__('selected_leads_from'), count($selected_leads), htmlspecialchars($page['page_name'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <form method="POST" class="space-y-6" enctype="multipart/form-data">
                                    <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page_id); ?>">

                                    <!-- Send Leads as JSON to avoid max_input_vars limit (1000 items) -->
                                    <input type="hidden" name="ids_json"
                                        value="<?php echo htmlspecialchars(json_encode($selected_leads)); ?>">

                                    <!-- Campaign Name -->
                                    <div class="relative group">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1"><?php echo __('campaign_name'); ?></label>
                                        <input type="text" name="campaign_name"
                                            class="w-full bg-black/20 border border-white/10 rounded-2xl px-5 py-3.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                                            value="<?php echo htmlspecialchars($pre_name); ?>" required>
                                    </div>

                                    <!-- Message Text -->
                                    <div class="relative group">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1"><?php echo __('message_text'); ?></label>
                                        <textarea name="message_text" id="message-input" rows="6"
                                            class="w-full bg-black/20 border border-white/10 rounded-2xl px-5 py-3.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all resize-none"
                                            placeholder="<?php echo __('message_placeholder'); ?>"
                                            required><?php echo htmlspecialchars($pre_text); ?></textarea>
                                        <div class="flex justify-between items-center mt-2 px-1">
                                            <p class="text-[10px] text-gray-500"><?php echo __('vars_hint'); ?>: <span
                                                    class="text-indigo-400 font-mono">{{name}}</span></p>
                                            <div id="char-count" class="text-[10px] text-gray-500 font-mono">0
                                                <?php echo __('chars_unit'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Image Selection -->
                                    <div class="relative group">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 ml-1"><?php echo __('upload_image'); ?>
                                            / <?php echo __('image_url'); ?></label>

                                        <div
                                            class="flex gap-2 p-1 bg-black/30 rounded-2xl border border-white/5 mb-4 max-w-fit">
                                            <button type="button" onclick="setImageMode('url')" id="btn-img-url"
                                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-indigo-600 text-white"><?php echo __('use_url'); ?></button>
                                            <button type="button" onclick="setImageMode('file')" id="btn-img-file"
                                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-gray-400 hover:text-white"><?php echo __('local_file'); ?></button>
                                        </div>

                                        <div id="container-img-url" class="relative transition-all duration-300">
                                            <input type="url" name="image_url" id="image-input"
                                                class="w-full bg-black/20 border border-white/10 rounded-2xl px-5 py-3.5 pl-12 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                                                placeholder="<?php echo __('image_url_placeholder'); ?>"
                                                value="<?php echo htmlspecialchars($pre_image); ?>">
                                            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.828 10.122a3 3 0 00-4.242 0l-1.414 1.414a3 3 0 01-4.242 0 3 3 0 010-4.242l1.414-1.414a5 5 0 017.071 0l1.414 1.414a5 5 0 010 7.071l-1.414 1.414a5 5 0 01-7.071 0">
                                                </path>
                                            </svg>
                                        </div>

                                        <div id="container-img-file" class="hidden relative transition-all duration-300">
                                            <label
                                                class="flex flex-col items-center justify-center w-full h-32 border-2 border-white/10 border-dashed rounded-2xl cursor-pointer bg-black/10 hover:bg-black/20 transition-all hover:border-indigo-500/50">
                                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <svg class="w-8 h-8 mb-3 text-gray-400" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                                        </path>
                                                    </svg>
                                                    <p class="mb-2 text-sm text-gray-400 font-medium">
                                                        <?php echo __('upload_image'); ?>
                                                    </p>
                                                    <p id="file-name-display" class="text-xs text-gray-500 font-mono">PNG,
                                                        JPG,
                                                        GIF</p>
                                                </div>
                                                <input type="file" name="image_file" id="image-file-input" class="hidden"
                                                    accept="image/*" />
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Schedule & Timing -->
                                    <!-- Quick Launch Info -->
                                    <div
                                        class="glass-card p-4 rounded-2xl border border-white/5 bg-indigo-500/10 flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-white">
                                                <?php echo ($lang == 'ar' ? 'إرسال فوري ومباشر' : 'Immediate Direct Sending'); ?>
                                            </p>
                                            <p class="text-xs text-indigo-200">
                                                <?php echo ($lang == 'ar' ? 'سيتم بدء الإرسال فور الضغط على الزر أدناه.' : 'Sending will start immediately after clicking below.'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="run_now" value="1">

                                    <!-- Submit -->
                                    <button type="submit" name="save_campaign"
                                        class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-4 rounded-2xl shadow-xl shadow-indigo-600/20 transition-all transform active:scale-95 flex items-center justify-center gap-3 group">
                                        <span><?php echo __('go_to_runner'); ?></span>
                                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform rtl:group-hover:-translate-x-1"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Preview (Messenger Style) -->
                    <div class="w-full lg:w-[400px] shrink-0">
                        <div class="sticky top-24">
                            <div
                                class="glass-card rounded-[32px] border border-white/10 shadow-2xl overflow-hidden bg-[#0f172a]/80 backdrop-blur-2xl">
                                <!-- Preview Header -->
                                <div class="bg-white/5 border-b border-white/5 px-6 py-4 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                        <?php echo __('message_preview'); ?>
                                    </h3>
                                    <div class="flex gap-1">
                                        <div class="w-2 h-2 rounded-full bg-red-500/50"></div>
                                        <div class="w-2 h-2 rounded-full bg-yellow-500/50"></div>
                                        <div class="w-2 h-2 rounded-full bg-green-500/50"></div>
                                    </div>
                                </div>

                                <!-- Messenger UI Simulator -->
                                <div class="p-4 bg-black/40 h-[600px] flex flex-col">
                                    <!-- Recipient Header -->
                                    <div class="flex flex-col items-center mb-8 mt-4">
                                        <div
                                            class="w-16 h-16 rounded-full bg-[#1877F2] border-2 border-white/10 mb-2 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                                            <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                            </svg>
                                        </div>
                                        <div class="flex items-center gap-1.5 direction-ltr">
                                            <div class="font-bold text-white text-base">ماركتيشن - Marketation</div>
                                            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                                <path
                                                    d="M10 15.172L9.172 14.344L5.586 10.758L4.172 12.172L9.172 17.172L20.172 6.172L18.758 4.758L10 13.516L10 15.172Z" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10.59 16.6L6 12L7.41 10.59L10.59 13.77L16.59 7.77L18 9.18L10.59 16.6Z"
                                                    display="none" />
                                                <circle cx="12" cy="12" r="10" fill="currentColor" class="text-blue-500" />
                                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="white"
                                                    transform="scale(0.6) translate(8, 8)" />
                                            </svg>
                                        </div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">
                                            Verfied Business
                                        </div>
                                    </div>

                                    <!-- Messages Area -->
                                    <div class="flex-1 space-y-4 overflow-y-auto px-2 messenger-scrollbar">
                                        <!-- Received (The User) -->
                                        <div class="flex justify-start">
                                            <div
                                                class="bg-white/10 text-gray-300 rounded-2xl rounded-tl-none px-4 py-2.5 max-w-[85%] text-sm border border-white/5">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                                                    <span
                                                        class="text-[10px] font-bold text-indigo-400"><?php echo __('online_status'); ?></span>
                                                </div>
                                                <?php echo __('customer_msg_sample'); ?>
                                            </div>
                                        </div>

                                        <!-- Sent (The Page Preview) -->
                                        <div class="flex flex-col items-end gap-2">
                                            <!-- Image Preview if exists -->
                                            <div id="preview-image-container"
                                                class="hidden max-w-[85%] rounded-2xl overflow-hidden border border-white/10 shadow-lg">
                                                <img id="preview-image" src="" class="w-full h-auto object-cover max-h-48">
                                            </div>

                                            <!-- Text Bubble -->
                                            <div
                                                class="bg-[#0084ff] text-white rounded-2xl rounded-tr-none px-4 py-3 max-w-[85%] shadow-lg relative group">
                                                <p id="preview-text"
                                                    class="text-sm leading-relaxed break-words whitespace-pre-wrap italic opacity-80">
                                                    <?php echo __('preview_empty_msg'); ?>
                                                </p>
                                                <!-- Checkmark -->
                                                <div class="absolute -bottom-5 right-0 flex items-center gap-1">
                                                    <span
                                                        class="text-[9px] text-gray-500"><?php echo __('just_now'); ?></span>
                                                    <div
                                                        class="w-3 h-3 rounded-full bg-[#0084ff] flex items-center justify-center">
                                                        <svg class="w-2 h-2 text-white" fill="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="text-[10px] text-gray-500 text-center mt-12 mb-4 px-6 italic">
                                        <?php echo __('messenger_preview_hint'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Page Campaign History Card (NEW) -->
                            <?php if (!empty($page_history)): ?>
                                <div class="mt-6 animate-in slide-in-from-bottom duration-700">
                                    <div class="glass-card rounded-[2rem] border border-white/10 overflow-hidden shadow-2xl">
                                        <div
                                            class="bg-white/5 px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                                                <h3 class="text-xs font-bold text-white uppercase tracking-wider">
                                                    <?php echo ($lang == 'ar' ? 'تاريخ حملات الصفحة' : 'Page Campaign History'); ?>
                                                </h3>
                                            </div>
                                            <a href="page_inbox.php?page_id=<?php echo $page_id; ?>"
                                                class="text-[10px] text-indigo-400 font-bold hover:text-indigo-300 transition-colors flex items-center gap-1">
                                                <?php echo ($lang == 'ar' ? 'عرض الكل' : 'View All'); ?>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                </svg>
                                            </a>
                                        </div>
                                        <div class="p-4 space-y-3">
                                            <?php foreach ($page_history as $hist): ?>
                                                <div
                                                    class="p-3 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-all group">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <h4 class="text-xs font-bold text-white truncate max-w-[150px]">
                                                            <?php echo htmlspecialchars($hist['name']); ?></h4>
                                                        <span
                                                            class="text-[9px] text-gray-500 font-mono"><?php echo date('d/m H:i', strtotime($hist['created_at'])); ?></span>
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        <div class="flex items-center gap-1">
                                                            <div
                                                                class="w-1.5 h-1.5 rounded-full <?php echo $hist['status'] === 'completed' ? 'bg-green-500' : ($hist['status'] === 'running' ? 'bg-blue-500 animate-pulse' : 'bg-gray-500'); ?>">
                                                            </div>
                                                            <span
                                                                class="text-[9px] text-gray-400 font-bold uppercase"><?php echo __($hist['status']); ?></span>
                                                        </div>
                                                        <div class="text-[9px] text-gray-500 px-2 border-l border-white/10">
                                                            <b class="text-indigo-400"><?php echo $hist['total_leads']; ?></b>
                                                            <?php echo ($lang == 'ar' ? 'عميل' : 'leads'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
    <script>
        const messageInput = document.getElementById('message-input');
        const imageInput = document.getElementById('image-input');
        const fileInput = document.getElementById('image-file-input');
        const fileNameDisplay = document.getElementById('file-name-display');
        const previewText = document.getElementById('preview-text');
        const previewImage = document.getElementById('preview-image');
        const previewImageContainer = document.getElementById('preview-image-container');
        const charCount = document.getElementById('char-count');

        const defaultName = "<?php echo __('preview_name_placeholder'); ?>";
        const emptyMsg = "<?php echo __('preview_empty_msg'); ?>";
        const charsUnit = "<?php echo __('chars_unit'); ?>";

        let currentMode = 'url';

        function setImageMode(mode) {
            currentMode = mode;
            const urlContainer = document.getElementById('container-img-url');
            const fileContainer = document.getElementById('container-img-file');
            const urlBtn = document.getElementById('btn-img-url');
            const fileBtn = document.getElementById('btn-img-file');

            if (mode === 'url') {
                urlContainer.classList.remove('hidden');
                fileContainer.classList.add('hidden');
                urlBtn.classList.add('bg-indigo-600', 'text-white');
                urlBtn.classList.remove('text-gray-400');
                fileBtn.classList.remove('bg-indigo-600', 'text-white');
                fileBtn.classList.add('text-gray-400');
            } else {
                urlContainer.classList.add('hidden');
                fileContainer.classList.remove('hidden');
                fileBtn.classList.add('bg-indigo-600', 'text-white');
                fileBtn.classList.remove('text-gray-400');
                urlBtn.classList.remove('bg-indigo-600', 'text-white');
                urlBtn.classList.add('text-gray-400');
            }
            updatePreview();
        }

        function updatePreview() {
            let text = messageInput.value;
            charCount.innerText = `${text.length} ${charsUnit}`;

            if (!text.trim()) {
                previewText.innerText = emptyMsg;
                previewText.classList.add('opacity-50', 'italic');
            } else {
                let processed = text.replace(/{{name}}/g, `<span class="bg-white/20 px-1 rounded font-bold text-white">${defaultName}</span>`);
                previewText.innerHTML = processed;
                previewText.classList.remove('opacity-50', 'italic');
            }

            // Image Preview Logic
            if (currentMode === 'url') {
                if (imageInput.value.trim()) {
                    previewImage.src = imageInput.value;
                    previewImageContainer.classList.remove('hidden');
                    previewImage.onerror = () => previewImageContainer.classList.add('hidden');
                } else {
                    previewImageContainer.classList.add('hidden');
                }
            } else {
                if (fileInput.files && fileInput.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewImage.src = e.target.result;
                        previewImageContainer.classList.remove('hidden');
                    }
                    reader.readAsDataURL(fileInput.files[0]);
                    fileNameDisplay.innerText = fileInput.files[0].name;
                    fileNameDisplay.classList.remove('text-gray-500');
                    fileNameDisplay.classList.add('text-indigo-400', 'font-bold');
                } else {
                    previewImageContainer.classList.add('hidden');
                    fileNameDisplay.innerText = "PNG, JPG, GIF";
                    fileNameDisplay.classList.add('text-gray-500');
                    fileNameDisplay.classList.remove('text-indigo-400', 'font-bold');
                }
            }
        }

        messageInput.addEventListener('input', updatePreview);
        imageInput.addEventListener('input', updatePreview);
        fileInput.addEventListener('change', updatePreview);

        // Run Now Toggle Logic
        const runNow = document.getElementById('run-now');
        const scheduleContainer = document.getElementById('schedule-container');

        runNow.addEventListener('change', function () {
            if (this.checked) {
                scheduleContainer.classList.add('hidden');
            } else {
                scheduleContainer.classList.remove('hidden');
            }
        });

        updatePreview();
    </script>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>