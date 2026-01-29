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
    $page_id = $_POST['page_id'] ?? $_GET['page_id'] ?? $setup['page_id'] ?? 0;
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

// Fetch Campaign History
$page_history = [];
if (isset($page_id) && $page_id > 0) {
    // History for current page
    $histStmt = $pdo->prepare("SELECT * FROM campaigns WHERE page_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 5");
    $histStmt->execute([$page_id, $user_id]);
    $page_history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Global history for this user
    $histStmt = $pdo->prepare("SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $histStmt->execute([$user_id]);
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
            // COMPRESSION STRATEGY: NEW FILE + AGGRESSIVE JPG
            try {
                // Adjust max dimensions
                list($orig_w, $orig_h) = getimagesize($target_path);
                $max_w = 1200; // Optimal for Messenger

                // Threshold: Optimize if width > 1200 OR Size > 150KB
                if ($orig_w > $max_w || filesize($target_path) > 150000) {
                    $info = getimagesize($target_path);
                    $mime = $info['mime'];

                    $src_img = null;
                    if ($mime == 'image/jpeg')
                        $src_img = imagecreatefromjpeg($target_path);
                    elseif ($mime == 'image/png')
                        $src_img = imagecreatefrompng($target_path);
                    elseif ($mime == 'image/gif')
                        $src_img = imagecreatefromgif($target_path);

                    if ($src_img) {
                        $new_w = $orig_w;
                        $new_h = $orig_h;

                        // Resize
                        if ($orig_w > $max_w) {
                            $ratio = $max_w / $orig_w;
                            $new_w = $max_w;
                            $new_h = intval($orig_h * $ratio);
                        }

                        $dst_img = imagecreatetruecolor($new_w, $new_h);

                        // Check if we REALLY need transparency.
                        // For marketing, usually JPG is better.
                        $is_transparent_png = false;
                        if ($mime == 'image/png') {
                            // Let's assume user prefers JPG for size unless it's strictly a logo with transparency.
                            // But determining "real" transparency is hard.
                            // Strategy: If it was PNG, keep simple transparency logic but check size later?
                            // No, let's force JPG conversion unless requested (user uploads PNG often just because).
                            // We will convert to JPG to guarantee size reduction.
                            // If transparency is needed, white background is safer for Messenger anyway (Dark mode issues otherwise).
                            $is_transparent_png = false;
                        }

                        // Fill white background (Safe for JPG conversion)
                        $white = imagecolorallocate($dst_img, 255, 255, 255);
                        imagefilledrectangle($dst_img, 0, 0, $new_w, $new_h, $white);

                        if ($mime == 'image/png' && $is_transparent_png) {
                            // If we decide to support transparency later
                            imagecolortransparent($dst_img, $white);
                        }

                        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

                        // SAVE AS NEW OPTIMIZED FILE
                        $path_parts = pathinfo($target_path);
                        $new_filename = $path_parts['filename'] . '_opt.jpg'; // Always JPG
                        $new_target_path = $path_parts['dirname'] . '/' . $new_filename;

                        // Quality 60 is the sweet spot for Messenger marketing
                        $saved = imagejpeg($dst_img, $new_target_path, 60);

                        if ($saved) {
                            // Delete heavy original
                            @unlink($target_path);
                            // Update refs
                            $file_name = $new_filename;
                            $target_path = $new_target_path;
                        }

                        imagedestroy($src_img);
                        imagedestroy($dst_img);
                        clearstatcache();
                    }
                }
            } catch (Exception $e) { /* Ignore */
            }

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

    // Insert into Queue (Only for NEW campaigns to prevent duplication on edit/resubmit)
    if (!$edit_id) {
        // We assume incoming leads are Internal DB IDs
        $queueStmt = $pdo->prepare("INSERT IGNORE INTO campaign_queue (campaign_id, lead_id, status) VALUES (?, ?, 'pending')");

        $pdo->beginTransaction();
        $inserted_count = 0;

        foreach ($selected_leads as $lead_id) {
            if (filter_var($lead_id, FILTER_VALIDATE_INT)) {
                $queueStmt->execute([$campaign_id, $lead_id]);
                $inserted_count++;
            }
        }
        $pdo->commit();
    }

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
        <!-- Header Actions -->
        <div class="mb-6 flex items-center justify-between">
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
            <?php if (isset($show_empty_state) && $show_empty_state): ?>
                <!-- Empty State Content -->
                <div class="flex-1 max-w-4xl mx-auto">
                    <div
                        class="glass-card p-12 rounded-[2.5rem] text-center border border-white/10 flex flex-col items-center justify-center min-h-[500px] relative overflow-hidden shadow-2xl">

                        <!-- Decorative background elements -->
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent">
                        </div>
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-500/5 blur-[100px] rounded-full"></div>
                        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-purple-500/5 blur-[100px] rounded-full"></div>

                        <div
                            class="w-24 h-24 rounded-[2rem] bg-indigo-600/10 flex items-center justify-center mb-8 rotate-3 hover:rotate-0 transition-transform duration-500 shadow-inner group">
                            <svg class="w-12 h-12 text-indigo-400 group-hover:scale-110 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                        </div>

                        <h2 class="text-3xl font-black text-white mb-4 tracking-tight">
                            <?php echo ($lang == 'ar' ? 'ابدأ حملتك التسويقية الآن' : 'Start Your Marketing Campaign'); ?>
                        </h2>
                        <p class="text-gray-400 max-w-lg mx-auto mb-10 text-lg leading-relaxed">
                            <?php echo ($lang == 'ar' ? 'يرجى العودة لصندوق الوارد واختيار العملاء الذين ترغب في استهدافهم بجدول زمني أو رسائل فورية.' : 'Please go back to the inbox and select the leads you want to target with scheduled or instant messages.'); ?>
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="page_inbox.php<?php echo $page_id ? '?page_id=' . $page_id : ''; ?>"
                                class="inline-flex items-center gap-3 bg-indigo-600 hover:bg-indigo-700 text-white px-10 py-4 rounded-2xl font-bold transition-all shadow-xl shadow-indigo-600/20 active:scale-95">
                                <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                                    </path>
                                </svg>
                                <span><?php echo ($lang == 'ar' ? 'إدارة الرسائل' : 'Manage Messages'); ?></span>
                            </a>

                            <?php if (!empty($page_history)): ?>
                                <button onclick="document.getElementById('recent-history').scrollIntoView({behavior: 'smooth'})"
                                    class="inline-flex items-center gap-3 bg-white/5 hover:bg-white/10 text-white px-10 py-4 rounded-2xl font-bold transition-all border border-white/10 active:scale-95">
                                    <span><?php echo ($lang == 'ar' ? 'مشاهدة الحملات السابقة' : 'View Recent History'); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Campaign History in Empty State (NEW) -->
                        <div class="mt-20 w-full text-left border-t border-white/5 pt-12" id="recent-history">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.3em]">
                                    <?php echo ($lang == 'ar' ? 'آخر الحملات التي تم إنشاؤها' : 'Latest Created Campaigns'); ?>
                                </h3>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest"><?php echo count($page_history); ?>
                                        <?php echo ($lang == 'ar' ? 'حملات' : 'results'); ?></span>
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></div>
                                </div>
                            </div>

                            <?php if (!empty($page_history)): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <?php foreach ($page_history as $hist): ?>
                                        <div class="p-5 bg-white/[0.03] rounded-3xl border border-white/5 hover:bg-white/[0.07] hover:border-indigo-500/30 transition-all group flex flex-col gap-3 relative overflow-hidden shadow-lg shadow-black/20"
                                            id="camp-card-<?php echo $hist['id']; ?>">
                                            <div
                                                class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/5 blur-3xl -mr-16 -mt-16 opacity-0 group-hover:opacity-100 transition-opacity">
                                            </div>
                                            <div class="flex justify-between items-start relative z-10">
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-xs font-bold text-white group-hover:text-indigo-400 transition-colors truncate max-w-[180px] mb-0.5"><?php echo htmlspecialchars($hist['name']); ?></span>
                                                    <span
                                                        class="text-[10px] text-gray-500 font-medium"><?php echo date('M d, H:i', strtotime($hist['created_at'])); ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div
                                                        class="w-2 h-2 rounded-full <?php echo $hist['status'] === 'completed' ? 'bg-green-500' : 'bg-indigo-500'; ?> shadow-[0_0_8px_rgba(99,102,241,0.5)]">
                                                    </div>
                                                    <span
                                                        class="text-[10px] text-gray-400 font-black uppercase tracking-tighter"><?php echo __($hist['status'] ?: 'active'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Stats Section (NEW) -->
                                            <div
                                                class="flex items-center justify-between mt-1 bg-black/20 rounded-xl p-3 border border-white/5 relative z-10">
                                                <div class="text-center">
                                                    <div class="text-[8px] text-gray-500 uppercase font-black">
                                                        <?php echo __('sent'); ?>
                                                    </div>
                                                    <div class="text-xs font-bold text-green-400">
                                                        <?php echo $hist['sent_count'] ?? 0; ?>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="text-[8px] text-gray-500 uppercase font-black">
                                                        <?php echo __('failed'); ?>
                                                    </div>
                                                    <div class="text-xs font-bold text-red-400">
                                                        <?php echo $hist['failed_count'] ?? 0; ?>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="text-[8px] text-gray-500 uppercase font-black">
                                                        <?php echo __('total'); ?>
                                                    </div>
                                                    <div class="text-xs font-bold text-indigo-400">
                                                        <?php echo $hist['total_leads']; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions (NEW) -->
                                            <div class="flex items-center gap-2 mt-2 pt-3 border-t border-white/5 relative z-10">
                                                <a href="campaign_runner.php?id=<?php echo $hist['id']; ?>"
                                                    class="flex-1 flex justify-center items-center py-2 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white rounded-xl text-[10px] font-bold transition-all border border-indigo-500/20">
                                                    <?php echo ($lang == 'ar' ? 'فتح' : 'Open'); ?>
                                                </a>
                                                <a href="create_campaign.php?id=<?php echo $hist['id']; ?>"
                                                    class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-xl transition-all border border-white/5"
                                                    title="<?php echo __('edit'); ?>">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                        </path>
                                                    </svg>
                                                </a>
                                                <button onclick="window.deleteCampaign(<?php echo $hist['id']; ?>)"
                                                    class="p-2 text-red-500/50 hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all border border-red-500/10"
                                                    title="<?php echo __('delete'); ?>">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div
                                    class="p-12 bg-white/[0.02] rounded-[2rem] border border-white/5 border-dashed text-center">
                                    <div
                                        class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-500 italic font-medium">
                                        <?php echo ($lang == 'ar' ? 'لا توجد حملات سابقة لهذه الصفحة حتى الآن.' : 'No previous campaigns found for this page yet.'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- Main Setup Column (Left) -->
                <div class="flex-1">
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
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z">
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
                                                <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                                    </path>
                                                </svg>
                                                <p class="mb-2 text-sm text-gray-400 font-medium">
                                                    <?php echo __('upload_image'); ?>
                                                </p>
                                                <p id="file-name-display" class="text-xs text-gray-500 font-mono">PNG, JPG,
                                                    GIF</p>
                                            </div>
                                            <input type="file" name="image_file" id="image-file-input" class="hidden"
                                                accept="image/*" />
                                        </label>
                                    </div>
                                </div>

                                <!-- Meta Info -->
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

                <!-- Right Sidebar (Preview & Local History) -->
                <div class="w-full lg:w-[400px] shrink-0">
                    <div class="sticky top-8 space-y-6">
                        <!-- Preview Card -->
                        <div
                            class="glass-card rounded-[32px] border border-white/10 shadow-2xl overflow-hidden bg-[#0f172a]/80 backdrop-blur-2xl">
                            <div class="bg-white/5 border-b border-white/5 px-6 py-4 flex items-center justify-between">
                                <h3 class="text-xs font-bold text-white uppercase tracking-wider">
                                    <?php echo __('message_preview'); ?>
                                </h3>
                                <div class="flex gap-1">
                                    <div class="w-2 h-2 rounded-full bg-red-500/50"></div>
                                    <div class="w-2 h-2 rounded-full bg-yellow-500/50"></div>
                                    <div class="w-2 h-2 rounded-full bg-green-500/50"></div>
                                </div>
                            </div>

                            <div class="p-4 bg-black/40 h-[500px] flex flex-col">
                                <div class="flex flex-col items-center mb-6 mt-4">
                                    <div
                                        class="w-14 h-14 rounded-full bg-[#1877F2] border-2 border-white/10 mb-2 flex items-center justify-center text-white shadow-lg">
                                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                        </svg>
                                    </div>
                                    <div class="font-bold text-white text-sm">Marketation - ماركتيشن</div>
                                    <div class="text-[9px] text-gray-500 uppercase tracking-widest mt-1">Verified Business
                                    </div>
                                </div>

                                <div class="flex-1 space-y-4 overflow-y-auto px-2 messenger-scrollbar">
                                    <div class="flex justify-start">
                                        <div
                                            class="bg-white/10 text-gray-300 rounded-2xl rounded-tl-none px-4 py-2.5 max-w-[85%] text-[13px] border border-white/5">
                                            <?php echo __('customer_msg_sample'); ?>
                                        </div>
                                    </div>

                                    <div id="preview-image-container"
                                        class="hidden ml-auto max-w-[85%] rounded-2xl overflow-hidden border border-white/10 shadow-lg">
                                        <img id="preview-image" src="" class="w-full h-auto object-cover max-h-40">
                                    </div>

                                    <div class="flex justify-end">
                                        <div
                                            class="bg-[#0084ff] text-white rounded-2xl rounded-tr-none px-4 py-3 max-w-[85%] shadow-lg relative group">
                                            <p id="preview-text"
                                                class="text-[13px] leading-relaxed break-words whitespace-pre-wrap italic opacity-80">
                                                <?php echo __('preview_empty_msg'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mini History Card -->
                        <?php if (!empty($page_history)): ?>
                            <div class="glass-card rounded-[2rem] border border-white/10 overflow-hidden shadow-2xl">
                                <div class="bg-white/5 px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">
                                        <?php echo ($lang == 'ar' ? 'تاريخ حملات الصفحة' : 'Page Campaign History'); ?>
                                    </h3>
                                    <a href="page_inbox.php?page_id=<?php echo $page_id; ?>"
                                        class="text-[10px] text-indigo-400 hover:underline">
                                        <?php echo ($lang == 'ar' ? 'عرض الكل' : 'View All'); ?>
                                    </a>
                                </div>
                                <div class="p-4 space-y-2 max-h-[220px] overflow-y-auto messenger-scrollbar">
                                    <?php foreach ($page_history as $hist): ?>
                                        <div class="p-4 bg-white/[0.03] rounded-[1.5rem] border border-white/5 hover:bg-white/[0.07] hover:border-indigo-500/30 transition-all group flex flex-col gap-3 relative overflow-hidden shadow-lg"
                                            id="camp-card-sidebar-<?php echo $hist['id']; ?>">
                                            <div class="flex justify-between items-start relative z-10">
                                                <div class="flex flex-col min-w-0">
                                                    <span
                                                        class="text-[11px] font-bold text-white group-hover:text-indigo-400 transition-colors truncate mb-0.5"><?php echo htmlspecialchars($hist['name']); ?></span>
                                                    <span
                                                        class="text-[9px] text-gray-500 font-medium"><?php echo date('M d, H:i', strtotime($hist['created_at'])); ?></span>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <div
                                                        class="w-1.5 h-1.5 rounded-full <?php echo $hist['status'] === 'completed' ? 'bg-green-500' : 'bg-indigo-500'; ?> shadow-[0_0_5px_rgba(99,102,241,0.5)]">
                                                    </div>
                                                    <span
                                                        class="text-[9px] text-gray-400 font-black uppercase tracking-tighter"><?php echo __($hist['status'] ?: 'active'); ?></span>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 relative z-10">
                                                <a href="campaign_runner.php?id=<?php echo $hist['id']; ?>"
                                                    class="flex-1 flex justify-center items-center py-1.5 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white rounded-lg text-[9px] font-bold transition-all border border-indigo-500/20">
                                                    <?php echo ($lang == 'ar' ? 'فتح' : 'Open'); ?>
                                                </a>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <a href="create_campaign.php?id=<?php echo $hist['id']; ?>"
                                                        class="p-1.5 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-all border border-white/5"
                                                        title="<?php echo __('edit'); ?>">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                            </path>
                                                        </svg>
                                                    </a>
                                                    <button onclick="window.deleteCampaign(<?php echo $hist['id']; ?>)"
                                                        class="p-1.5 text-red-500/50 hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-all border border-red-500/10"
                                                        title="<?php echo __('delete'); ?>">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
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

    // --- CAMPAIGN MANAGEMENT LOGIC (GLOBAL) ---
    let campIdToDelete = null;

    window.deleteCampaign = function (campId) {
        campIdToDelete = campId;
        const modal = document.getElementById('delete-modal');
        if (modal) modal.classList.remove('hidden');
    };

    window.confirmDelete = async function () {
        if (!campIdToDelete) return;

        const btn = document.getElementById('btn-confirm-delete');
        if (!btn) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<?php echo __('please_wait'); ?>...';

        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('campaign_id', campIdToDelete);

            const res = await fetch('ajax_campaign_actions.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.status === 'success') {
                // Remove from all locations (grid AND sidebar)
                const selectors = [
                    '#camp-card-' + campIdToDelete,
                    '#camp-card-sidebar-' + campIdToDelete
                ];

                selectors.forEach(sel => {
                    const cards = document.querySelectorAll(sel);
                    cards.forEach(card => {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => card.remove(), 300);
                    });
                });

                window.closeDeleteModal();
            } else {
                alert(data.message || 'Error deleting campaign');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to delete campaign');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    }

    window.closeDeleteModal = function () {
        const modal = document.getElementById('delete-modal');
        if (modal) modal.classList.add('hidden');
        campIdToDelete = null;
    }

    // --- INPUT PREVIEW LOGIC ---
    if (messageInput) messageInput.addEventListener('input', updatePreview);
    if (imageInput) imageInput.addEventListener('input', updatePreview);
    if (fileInput) fileInput.addEventListener('change', updatePreview);

    // Run Now Toggle Logic (Safe Check)
    const runNow = document.getElementById('run-now');
    const scheduleContainer = document.getElementById('schedule-container');

    if (runNow && scheduleContainer) {
        runNow.addEventListener('change', function () {
            if (this.checked) {
                scheduleContainer.classList.add('hidden');
            } else {
                scheduleContainer.classList.remove('hidden');
            }
        });
    }

    updatePreview();
</script>

<!-- Delete Confirmation Modal -->
<div id="delete-modal"
    class="fixed inset-0 z-[110] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card max-w-sm w-full p-8 rounded-3xl border border-white/10 text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-red-500/50"></div>
        <div class="w-16 h-16 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500 mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                </path>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">
            <?php echo ($lang == 'ar' ? 'حذف الحملة؟' : 'Delete Campaign?'); ?>
        </h3>
        <p class="text-gray-400 text-sm mb-8">
            <?php echo ($lang == 'ar' ? 'هل أنت متأكد من حذف هذه الحملة؟ سيتم حذف جميع سجلات الإرسال المرتبطة بها نهائياً.' : 'Are you sure you want to delete this campaign? All associated sending records will be permanently removed.'); ?>
        </p>

        <div class="flex gap-3">
            <button onclick="window.closeDeleteModal()"
                class="flex-1 py-3 bg-white/5 hover:bg-white/10 text-white rounded-xl font-bold transition-all border border-white/5">
                <?php echo ($lang == 'ar' ? 'إلغاء' : 'Cancel'); ?>
            </button>
            <button id="btn-confirm-delete" onclick="window.confirmDelete()"
                class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-bold transition-all shadow-lg shadow-red-500/20">
                <?php echo ($lang == 'ar' ? 'تأكيد الحذف' : 'Confirm Delete'); ?>
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>