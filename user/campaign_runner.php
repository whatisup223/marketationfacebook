<?php
require_once __DIR__ . '/../includes/functions.php';

// Prevent Resubmission
if (!empty($_POST)) {
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
    exit;
}

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Release session lock to prevent blocking parallel requests (Crucial for Localhost/Single Thread)
session_write_close();

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$campaign_id = $_GET['id'] ?? 0;

// Fetch Campaign Info
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    die(__('campaign_not_found'));
}

// OPTIMIZED: Use cached counters for instant page load
// The aggregate SUM query on campaign_queue is too slow for large datasets
$total = (int) ($campaign['total_leads'] ?? 0);
$sent = (int) ($campaign['sent_count'] ?? 0);
$failed = (int) ($campaign['failed_count'] ?? 0);
$pending = max(0, $total - ($sent + $failed));

// OPTIMIZED: Fetch ONLY current page rows
$per_page = 50;
$page_num = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page_num - 1) * $per_page;
$total_pages = ceil($total / $per_page);

$qStmt = $pdo->prepare("SELECT q.*, l.fb_user_name, l.fb_user_id 
                        FROM campaign_queue q 
                        JOIN fb_leads l ON q.lead_id = l.id 
                        WHERE q.campaign_id = ? 
                        ORDER BY q.id ASC LIMIT $per_page OFFSET $offset");
$qStmt->execute([$campaign_id]);
$queue_items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Check Schedule
$scheduled_time = strtotime($campaign['scheduled_at']);
$current_time = time();
$seconds_to_wait = max(0, $scheduled_time - $current_time);

// Check Autostart
$is_running = $campaign['status'] === 'running';
$auto_start_flag = (isset($_GET['autostart'])) ? 'true' : 'false';

// If already running, ignore schedule wait
if ($is_running) {
    $seconds_to_wait = 0;
}

// DEBUG: Log loaded values for persistent tracking
$debug_conf = json_encode([
    'id' => $campaign_id,
    'status' => $campaign['status'],
    'waiting_interval' => $campaign['waiting_interval'] ?? 30,
    'retry_count' => $campaign['retry_count'] ?? 1,
    'retry_delay' => $campaign['retry_delay'] ?? 10
]);
// Write to a hidden div or console log
echo "<script>console.log('Server DB Load:', $debug_conf);</script>";

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
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .status-transition {
        transition: all 0.3s ease;
    }

    .row-processing {
        background-color: rgba(59, 130, 246, 0.05);
        border-left: 3px solid #3b82f6;
    }

    .row-success {
        background-color: rgba(34, 197, 94, 0.05);
        border-left: 3px solid #22c55e;
    }

    .row-error {
        background-color: rgba(239, 68, 68, 0.05);
        border-left: 3px solid #ef4444;
    }

    @keyframes pulse-ring {
        0% {
            transform: scale(.33);
        }

        80%,
        100% {
            opacity: 0;
        }
    }

    .pulse-dot::before {
        content: '';
        position: absolute;
        left: -100%;
        top: -100%;
        width: 300%;
        height: 300%;
        background-color: currentColor;
        border-radius: 50%;
        opacity: 0.4;
        animation: pulse-ring 1.25s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    }
</style>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <!-- Back to Setup -->
        <div class="mb-6">
            <a href="create_campaign.php?id=<?php echo $campaign_id; ?>"
                class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors group">
                <div
                    class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center border border-white/5 group-hover:border-indigo-500/30 group-hover:bg-indigo-500/10 transition-all">
                    <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                        </path>
                    </svg>
                </div>
                <span class="text-sm font-bold"><?php echo __('setup_campaign'); ?></span>
            </a>
        </div>

        <!-- Dashboard Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                    <span class="p-2 bg-indigo-500 rounded-lg shadow-lg shadow-indigo-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </span>
                    <?php echo __('runner_title'); ?>
                </h1>
                <div class="flex items-center gap-2 text-gray-500 text-sm">
                    <span class="w-2 h-2 rounded-full bg-gray-600"></span>
                    <?php echo __('campaign_label'); ?> <span
                        class="font-bold text-gray-300"><?php echo htmlspecialchars($campaign['name']); ?></span>
                </div>
            </div>

            <!-- Controls -->
            <div class="w-full lg:w-auto flex flex-wrap items-center gap-4">
                <!-- Group 1: Interval & Real-time Status -->
                <!-- Group 1: Interval & Real-time Status -->
                <div
                    class="flex-1 min-w-[280px] h-14 bg-gray-800/40 backdrop-blur-md rounded-2xl border border-white/5 flex items-center p-1.5 shadow-inner">
                    <!-- Interval Input Section -->
                    <div class="h-full flex items-center px-4 border-r border-white/5 gap-3 shrink-0">
                        <div
                            class="w-8 h-8 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-[8px] text-gray-500 uppercase font-black tracking-widest leading-none mb-1"><?php echo __('interval'); ?></span>
                            <div class="flex items-center gap-1">
                                <input type="number" id="interval"
                                    value="<?php echo intval($campaign['waiting_interval'] ?? 30); ?>" min="0" step="1"
                                    class="w-10 bg-transparent text-white font-black text-sm focus:outline-none p-0 h-4 selection:bg-indigo-500">
                                <span
                                    class="text-[9px] text-gray-600 font-bold uppercase"><?php echo __('unit_s'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Batch Size Input Section (NEW) -->
                    <div class="h-full flex items-center px-4 border-r border-white/5 gap-3 shrink-0">
                        <div class="w-8 h-8 rounded-xl bg-green-500/10 flex items-center justify-center text-green-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-[8px] text-gray-500 uppercase font-black tracking-widest leading-none mb-1"><?php echo __('batch_size'); ?></span>
                            <div class="flex items-center gap-1">
                                <input type="number" id="batch_size"
                                    value="<?php echo intval($campaign['batch_size'] ?? 10); ?>" min="1" max="50"
                                    step="1"
                                    class="w-10 bg-transparent text-white font-black text-sm focus:outline-none p-0 h-4 selection:bg-green-500">
                                <span
                                    class="text-[9px] text-gray-600 font-bold uppercase"><?php echo __('unit_msg'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Status Text -->
                    <div id="status-msg"
                        class="flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 truncate italic">
                        <?php echo __('ready_msg'); ?>
                    </div>
                </div>

                <!-- Group 2: Action Controls -->
                <div class="flex items-center gap-2 h-14">
                    <button type="button" id="btn-save-settings" onclick="saveSettings()"
                        class="h-full px-8 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-[11px] uppercase tracking-widest rounded-2xl shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center gap-3 whitespace-nowrap active:scale-95">
                        <?php echo __('save_settings'); ?>
                    </button>
                    <button id="btn-start" onclick="startCampaign()"
                        class="h-full px-8 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-[11px] uppercase tracking-widest rounded-2xl shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center gap-3 whitespace-nowrap active:scale-95">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" />
                        </svg>
                        <span><?php echo __('btn_start'); ?></span>
                    </button>

                    <button id="btn-pause" onclick="pauseCampaign()"
                        class="hidden h-full px-8 bg-amber-500/10 hover:bg-amber-500/20 text-amber-500 font-black text-[11px] uppercase tracking-widest rounded-2xl border border-amber-500/20 transition-all flex items-center justify-center gap-3 whitespace-nowrap">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" />
                        </svg>
                        <span><?php echo __('btn_pause'); ?></span>
                    </button>

                    <button onclick="stopCampaign()"
                        class="h-full w-14 bg-red-500/10 hover:bg-red-500/20 text-red-500 rounded-2xl border border-red-500/20 transition-all flex items-center justify-center active:scale-90 group">
                        <svg class="w-5 h-5 shrink-0 group-hover:rotate-90 transition-transform duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="lg:col-span-2 bg-gray-900/40 rounded-3xl p-8 border border-white/5 relative overflow-hidden">
                <div class="flex justify-between items-end mb-4">
                    <div>
                        <span
                            class="text-[10px] uppercase font-bold text-gray-500 tracking-widest block mb-1"><?php echo __('campaign_progress'); ?></span>
                        <div id="progress-text" class="text-5xl font-black text-white">0%</div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-gray-500 block"><?php echo __('estimated_time'); ?></span>
                        <span id="eta-text" class="text-sm font-bold text-indigo-400">---</span>
                    </div>
                </div>
                <!-- Progress Bar -->
                <div class="w-full bg-gray-800 rounded-2xl h-8 p-1 relative mb-6">
                    <div id="progress-bar"
                        class="h-full bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl transition-all duration-700 ease-out flex items-center justify-end pr-2 overflow-hidden"
                        style="width: 0%">
                        <div class="w-full h-full opacity-10 animate-pulse bg-white/20"></div>
                    </div>
                </div>

                <!-- Retry Controls Inside Card -->
                <div class="flex items-center gap-6 pt-4 border-t border-white/5">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                                <?php echo __('retry_count_label'); ?>
                            </p>
                            <div class="flex items-center gap-1">
                                <input type="number" id="retry_count"
                                    value="<?php echo intval($campaign['retry_count'] ?? 1); ?>" min="0" step="1"
                                    class="w-10 bg-transparent text-white font-black focus:outline-none text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                                <?php echo __('retry_delay_label'); ?>
                            </p>
                            <div class="flex items-center gap-1">
                                <input type="number" id="retry_delay"
                                    value="<?php echo intval($campaign['retry_delay'] ?? 10); ?>" min="1"
                                    class="w-10 bg-transparent text-white font-black focus:outline-none text-sm bg-none">
                                <span
                                    class="text-[10px] text-gray-600 font-bold uppercase"><?php echo __('unit_s'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-4">
                <div class="bg-green-500/5 p-4 rounded-2xl border border-green-500/10 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center text-green-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" />
                        </svg>
                    </div>
                    <div>
                        <div id="count-sent" class="text-xl font-bold text-white"><?php echo $sent; ?></div>
                        <div class="text-[10px] text-gray-500 uppercase"><?php echo __('sent'); ?></div>
                    </div>
                </div>
                <div class="bg-red-500/5 p-4 rounded-2xl border border-red-500/10 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center text-red-500"><svg
                            class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 11-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" />
                        </svg></div>
                    <div>
                        <div id="count-failed" class="text-xl font-bold text-white"><?php echo $failed; ?></div>
                        <div class="text-[10px] text-gray-500 uppercase"><?php echo __('failed'); ?></div>
                    </div>
                </div>
                <div class="bg-blue-500/5 p-4 rounded-2xl border border-blue-500/10 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" />
                        </svg>
                    </div>
                    <div>
                        <div id="count-pending" class="text-xl font-bold text-white"><?php echo $pending; ?></div>
                        <div class="text-[10px] text-gray-500 uppercase"><?php echo __('pending'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-gray-900/40 rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
            <div class="px-8 py-5 border-b border-white/5 bg-white/5 flex justify-between items-center flex-wrap gap-4">
                <h2 class="text-lg font-bold text-white"><?php echo __('queue_list'); ?></h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-mono">ID Trace: #<?php echo $campaign_id; ?></span>
                    <div class="w-px h-4 bg-gray-700"></div>
                    <span class="text-xs text-gray-400"><?php echo __('total_leads'); ?>:
                        <b><?php echo $total; ?></b></span>
                </div>
            </div>

            <div class="overflow-x-auto overflow-y-auto max-h-[500px] messenger-scrollbar">
                <table class="w-full text-left">
                    <thead
                        class="bg-gray-900 text-gray-500 text-[10px] uppercase font-black tracking-[0.2em] sticky top-0 z-10 shadow-lg">
                        <tr>
                            <th class="px-8 py-4"><?php echo __('status'); ?></th>
                            <th class="px-8 py-4"><?php echo __('lead_info'); ?></th>
                            <th class="px-8 py-4 text-right"><?php echo __('last_update'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="queue-body" class="divide-y divide-white/5">
                        <?php
                        // Pagination verified via SQL LIMIT/OFFSET earlier.
                        // $queue_items now contains ONLY the 50 items for this page.
                        
                        if (empty($queue_items)): ?>
                            <tr>
                                <td colspan="3" class="px-8 py-20 text-center">
                                    <div class="text-gray-600 mb-2 italic"><?php echo __('empty_queue_title'); ?></div>
                                    <div class="text-xs text-gray-700"><?php echo __('empty_queue_desc'); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($queue_items as $item): ?>
                            <tr id="row-<?php echo $item['id']; ?>"
                                class="task-row status-transition hover:bg-white/5 transition-all"
                                data-id="<?php echo $item['id']; ?>" data-status="<?php echo $item['status']; ?>">
                                <td class="px-8 py-5 status-cell">
                                    <?php if ($item['status'] == 'sent'): ?>
                                        <div class="flex items-center gap-2 text-green-500 font-bold text-xs">
                                            <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                            <?php echo __('status_sent_small'); ?>
                                        </div>
                                    <?php elseif ($item['status'] == 'failed'): ?>
                                        <div class="flex items-center gap-2 text-red-500 font-bold text-xs">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                                            <?php echo __('status_failed_small'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2 text-gray-500 font-bold text-xs">
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-700"></div>
                                            <?php echo __('status_pending_small'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="text-sm font-bold text-white">
                                        <?php echo htmlspecialchars($item['fb_user_name']); ?>
                                    </div>
                                    <div class="text-[10px] text-gray-500 font-mono"><?php echo $item['fb_user_id']; ?>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-right msg-cell font-mono text-[10px] text-gray-500">
                                    <?php if ($item['status'] == 'failed'): ?>
                                        <div class="text-red-400 max-w-[200px] ml-auto overflow-hidden text-ellipsis">
                                            <?php echo $item['error_message']; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php echo $item['sent_at'] ? date('H:i:s d/m', strtotime($item['sent_at'])) : '--:--'; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Fixed Pagination Footer -->
            <?php if ($total_pages > 1): ?>
                <div class="px-8 py-4 bg-white/5 border-t border-white/5 backdrop-blur-xl">
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            <?php echo __('page'); ?>     <?php echo $page_num; ?> / <?php echo $total_pages; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page_num > 1): ?>
                                <a href="?id=<?php echo $campaign_id; ?>&page=<?php echo $page_num - 1; ?>"
                                    class="px-3 py-1 bg-white/10 rounded hover:bg-white/20 text-xs text-white transition-colors border border-white/5"><?php echo __('prev'); ?></a>
                            <?php endif; ?>
                            <?php if ($page_num < $total_pages): ?>
                                <a href="?id=<?php echo $campaign_id; ?>&page=<?php echo $page_num + 1; ?>"
                                    class="px-3 py-1 bg-white/10 rounded hover:bg-white/20 text-xs text-white transition-colors border border-white/5"><?php echo __('next'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- html2canvas for Report Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Token Update Modal -->
<div id="token-modal"
    class="fixed inset-0 z-[100] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-gray-900 border border-white/10 w-full max-w-md rounded-3xl p-8 shadow-2xl animate-in fade-in zoom-in duration-300">
        <div class="w-20 h-20 rounded-full bg-red-500/20 flex items-center justify-center text-red-500 mx-auto mb-6">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-white text-center mb-3"><?php echo __('token_expired_title'); ?></h3>
        <p class="text-gray-400 text-center text-sm mb-8 leading-relaxed">
            <?php echo __('token_expired_msg'); ?>
        </p>

        <div class="space-y-4">
            <div class="relative">
                <input type="text" id="new-token-input" placeholder="<?php echo __('new_token_placeholder'); ?>"
                    class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all font-mono">
            </div>

            <button onclick="updateToken()" id="btn-update-token"
                class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-500/20 transition-all flex items-center justify-center gap-2 group">
                <span><?php echo __('update_token_btn'); ?></span>
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="currentColor"
                    viewBox="0 0 20 20">
                    <path
                        d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 111.414-1.414z" />
                </svg>
            </button>
            <button onclick="document.getElementById('token-modal').classList.add('hidden')"
                class="w-full text-gray-500 text-sm font-medium hover:text-white transition-colors">
                <?php echo __('cancel'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Campaign Finished Report Modal -->
<div id="report-modal"
    class="fixed inset-0 z-[100] hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
    <div class="w-full max-w-lg animate-in fade-in zoom-in duration-500">
        <!-- The Card to Export -->
        <div id="report-card"
            class="bg-[#0f172a] border border-white/10 rounded-[2.5rem] overflow-hidden shadow-2xl relative">
            <!-- Header Pattern -->
            <div
                class="absolute top-0 right-0 w-48 h-48 bg-indigo-600/20 blur-[80px] -mr-16 -mt-16 rounded-full pointer-events-none">
            </div>

            <div class="p-10 relative z-10">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-3xl font-black text-white italic tracking-tighter mb-1">ماركتيشن - Marketation
                        </h2>
                        <p class="text-xs text-indigo-400 font-bold uppercase tracking-[0.2em]">
                            <?php echo __('report_title'); ?>
                        </p>
                    </div>
                    <div
                        class="w-16 h-16 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-500 border border-indigo-500/10">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <div class="mb-10 space-y-1">
                    <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest">
                        <?php echo __('campaign_name'); ?>
                    </p>
                    <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($campaign['name']); ?></h3>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-6 mb-10">
                    <div class="bg-white/5 border border-white/5 p-6 rounded-3xl relative group transition-all">
                        <div
                            class="absolute top-4 right-4 w-2 h-2 rounded-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]">
                        </div>
                        <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-2 font-mono">
                            <?php echo __('report_success'); ?>
                        </p>
                        <h4 id="report-sent" class="text-4xl font-black text-white leading-none">0</h4>
                        <p class="text-[10px] text-green-500 mt-2 font-bold tracking-tighter italic">
                            <?php echo __('report_delivered'); ?>
                        </p>
                    </div>
                    <div class="bg-white/5 border border-white/5 p-6 rounded-3xl relative group transition-all">
                        <div
                            class="absolute top-4 right-4 w-2 h-2 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]">
                        </div>
                        <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-2 font-mono">
                            <?php echo __('report_failed'); ?>
                        </p>
                        <h4 id="report-failed" class="text-4xl font-black text-white leading-none">0</h4>
                        <p class="text-[10px] text-red-400 mt-2 font-bold tracking-tighter italic">
                            <?php echo __('report_needs_re_routing'); ?>
                        </p>
                    </div>
                </div>

                <div
                    class="bg-indigo-600/10 border border-indigo-500/20 p-5 rounded-3xl flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-lg">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400"><?php echo __('report_total_recipients'); ?></p>
                            <p id="report-total" class="font-bold text-white font-mono">0
                                <?php echo __('report_people'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500"><?php echo __('report_date_logged'); ?></p>
                        <p class="font-bold text-gray-300 text-xs"><?php echo date('Y-m-d H:i'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Branding Bar -->
            <div class="bg-indigo-600 px-10 py-3 flex justify-between items-center">
                <span
                    class="text-[9px] text-white/60 font-medium tracking-[0.2em]"><?php echo __('report_powered_by'); ?></span>
                <div class="flex gap-1">
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                </div>
            </div>
        </div>

        <!-- Meta Controls (Outside Capture) -->
        <div class="mt-8 flex flex-col sm:flex-row gap-4">
            <button onclick="downloadReportImage()" id="btn-download-report"
                class="flex-1 py-4 bg-white text-black font-black rounded-2xl shadow-xl hover:bg-gray-100 transition-all flex items-center justify-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <?php echo __('report_save_image'); ?>
            </button>
            <button onclick="window.location.reload()"
                class="px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl transition-all border border-white/10">
                <?php echo __('report_close'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    let isRunning = false;
    let isWaiting = false;
    let currentIndex = 0;
    let queue = [];
    let sentCount = <?php echo $sent; ?>;
    let failedCount = <?php echo $failed; ?>;
    let total = <?php echo $total; ?>;
    let pendingCount = <?php echo $total - ($sent + $failed); ?>;

    // Schedule Logic
    let secondsToWait = <?php echo $seconds_to_wait; ?>;
    let shouldAutoStart = <?php echo $auto_start_flag; ?>;

    // Load queue correctly (Only pending items)
    document.querySelectorAll('.task-row').forEach(row => {
        if (row.dataset.status === 'pending') {
            queue.push(row.dataset.id);
        }
    });

    function updateStats() {
        if (total === 0) return;
        let percent = Math.round(((sentCount + failedCount) / total) * 100);
        if (isNaN(percent)) percent = 0;

        document.getElementById('progress-bar').style.width = percent + '%';
        document.getElementById('progress-text').innerText = percent + '%';
        document.getElementById('count-sent').innerText = sentCount;
        document.getElementById('count-failed').innerText = failedCount;
        document.getElementById('count-pending').innerText = pendingCount;

        // ETA Calculation
        let wait = parseInt(document.getElementById('interval').value) || 30;
        let remaining = pendingCount * wait;
        if (remaining > 0) {
            if (remaining < 60) {
                document.getElementById('eta-text').innerText = remaining + 's';
            } else {
                let m = Math.floor(remaining / 60);
                let s = remaining % 60;
                document.getElementById('eta-text').innerText = `${m}m ${s}s`;
            }
        } else {
            document.getElementById('eta-text').innerText = '<?php echo __('finished_status'); ?>';
        }
    }

    async function startCampaign() {
        if (isRunning || isWaiting) return;

        // --- SAVE SETTINGS START ---
        const inputInterval = document.getElementById('interval');
        const inputRetryCount = document.getElementById('retry_count');
        const inputRetryDelay = document.getElementById('retry_delay');
        const btnStart = document.getElementById('btn-start');
        const originalBtnText = btnStart.innerText;

        btnStart.innerText = '<?php echo __('saving_status'); ?>...';

        try {
            const settingsData = new FormData();
            settingsData.append('campaign_id', <?php echo $campaign_id; ?>);
            settingsData.append('interval', inputInterval.value);
            settingsData.append('retry_count', inputRetryCount.value);
            settingsData.append('retry_delay', inputRetryDelay.value);

            await fetch('api_save_settings.php', { method: 'POST', body: settingsData });
        } catch (e) {
            console.error('Save Settings Error:', e);
        }
        // --- SAVE SETTINGS END ---

        if (queue.length === 0) {
            alert('<?php echo __('empty_queue_title'); ?>');
            btnStart.innerText = originalBtnText;
            return;
        }

        // --- TOKEN CHECK ---
        document.getElementById('status-msg').innerText = '<?php echo __('checking_token'); ?>';
        try {
            const formData = new FormData();
            formData.append('action', 'check_token');
            formData.append('campaign_id', <?php echo $campaign_id; ?>);
            const checkRes = await fetch('campaign_token_handler.php', { method: 'POST', body: formData });
            const checkData = await checkRes.json();

            if (checkData.status !== 'valid') {
                document.getElementById('status-msg').innerText = '<?php echo __('token_invalid'); ?>';
                showTokenModal();
                btnStart.innerText = originalBtnText;
                return;
            }
        } catch (e) {
            btnStart.innerText = originalBtnText;
            return;
        }

        // Restore Button Text
        btnStart.innerText = originalBtnText;

        // Check for Schedule Delay
        if (secondsToWait > 0) {
            // Update Status to 'scheduled' to ARM the system (Persistence)
            try {
                await fetch('api_update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'campaign_id=<?php echo $campaign_id; ?>&status=scheduled'
                });
            } catch (e) { console.error("Error arming schedule", e); }

            startCountdown();
            return;
        }

        executeCampaign(); // Immediate Start (Running)
    }

    function startCountdown() {
        isWaiting = true;
        document.getElementById('btn-start').classList.add('hidden');
        document.getElementById('btn-pause').classList.remove('hidden');

        const timer = setInterval(() => {
            if (!isWaiting) { // Clicked Pause
                clearInterval(timer);
                document.getElementById('status-msg').innerText = '<?php echo __('paused'); ?>';
                return;
            }

            secondsToWait--;

            let h = Math.floor(secondsToWait / 3600);
            let m = Math.floor((secondsToWait % 3600) / 60);
            let s = secondsToWait % 60;
            let timeStr = `${h}h ${m}m ${s}s`;

            document.getElementById('status-msg').innerHTML = `<span class="text-indigo-400 font-bold animate-pulse">⏳ <?php echo __('starts_in'); ?> ${timeStr}</span>`;

            if (secondsToWait <= 0) {
                clearInterval(timer);
                isWaiting = false;
                executeCampaign();
            }
        }, 1000);
    }

    function executeCampaign() {
        // API: Update status to running
        fetch('api_update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'campaign_id=<?php echo $campaign_id; ?>&status=running'
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    isRunning = true;
                    document.getElementById('btn-start').classList.add('hidden');
                    document.getElementById('btn-pause').classList.remove('hidden');
                    document.getElementById('status-msg').innerHTML = '<span class="animate-pulse text-green-400 font-bold uppercase tracking-wider"><?php echo __('sending_batch'); ?> (Backend)</span>';

                    // Start Monitoring Loop
                    monitorLoop();
                    processQueue();
                } else {
                    alert('Error starting campaign: ' + data.message);
                }
            });
    }

    function pauseCampaign() {
        // Optimistic UI Update
        isRunning = false;
        isWaiting = false;
        document.getElementById('btn-start').classList.remove('hidden');
        document.getElementById('btn-pause').classList.add('hidden');
        document.getElementById('status-msg').innerHTML = '<span class="text-yellow-400 font-bold uppercase tracking-wider"><?php echo __('paused'); ?>...</span>';

        fetch('api_update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'campaign_id=<?php echo $campaign_id; ?>&status=paused'
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('status-msg').innerHTML = '<span class="text-yellow-400 font-bold uppercase tracking-wider"><?php echo __('paused'); ?></span>';
                } else {
                    console.error("Pause failed", data);
                    // Revert? Or just show error
                }
            })
            .catch(err => console.error("Pause Network Error", err));
    }

    // Monitoring Loop
    function monitorLoop() {
        if (!isRunning) return;

        fetch('api_get_stats.php?id=<?php echo $campaign_id; ?>')
            .then(res => res.json())
            .then(data => {
                // Sync status from Server
                if (data.status === 'paused' || data.status === 'stopped') {
                    isRunning = false;
                    document.getElementById('btn-start').classList.remove('hidden');
                    document.getElementById('btn-pause').classList.add('hidden');
                    document.getElementById('status-msg').innerHTML = '<span class="text-yellow-400 font-bold uppercase tracking-wider"><?php echo __('paused'); ?></span>';
                    return;
                }

                if (data.status === 'completed') {
                    isRunning = false;
                    document.getElementById('status-msg').innerHTML = '<span class="text-indigo-400 font-bold tracking-widest uppercase"><?php echo __('campaign_finished_title'); ?></span>';
                    document.getElementById('btn-start').classList.remove('hidden');
                    document.getElementById('btn-start').innerText = '<?php echo __('rerun_campaign'); ?>';
                    document.getElementById('btn-pause').classList.add('hidden');

                    // Show Report Modal
                    document.getElementById('report-modal').classList.remove('hidden');
                    document.getElementById('report-sent').innerText = data.sent;
                    document.getElementById('report-failed').innerText = data.failed;
                    document.getElementById('report-total').innerText = <?php echo $total; ?>;

                    return;
                }

                // Update Counts
                document.getElementById('count-sent').innerText = data.sent;
                document.getElementById('count-failed').innerText = data.failed;
                document.getElementById('count-pending').innerText = data.pending;

                // Update Progress
                let total = <?php echo $total; ?>;
                let percent = Math.round(((parseInt(data.sent) + parseInt(data.failed)) / total) * 100);
                document.getElementById('progress-bar').style.width = percent + '%';
                document.getElementById('progress-text').innerText = percent + '%';
            })
            .catch(err => console.error("Monitor Error", err));

        setTimeout(monitorLoop, 500);
    }

    async function processQueue() {
        if (!isRunning) return;

        // Visual Feedback
        const timerDiv = document.getElementById('status-msg');
        const currentBatchSize = document.getElementById('batch_size') ? document.getElementById('batch_size').value : 1;

        if (timerDiv.innerText.indexOf('Starts in') === -1 && timerDiv.innerText.indexOf('Saving') === -1) {
            timerDiv.innerHTML = '<span class="animate-pulse text-indigo-400 font-bold uppercase tracking-wider"><?php echo __('sending_batch'); ?> (' + currentBatchSize + ')</span>';
        }

        const startTime = Date.now(); // Start timer

        try {
            const formData = new FormData();
            formData.append('campaign_id', <?php echo $campaign_id; ?>);

            const res = await fetch('campaign_batch_handler.php', {
                method: 'POST',
                body: formData
            });

            const rawText = await res.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                console.error("CRITICAL: Server returned invalid JSON. Raw Output:", rawText);
                // Force a 'fatal' status to stop the loop clearly
                data = { status: 'stopped', error: 'Server Error: check console' };
                // Update UI to warn user
                timerDiv.innerHTML = '<span class="text-red-500 font-bold uppercase tracking-wider">SERVER ERROR (CHECK CONSOLE)</span>';
            }

            if (data.status === 'batch_processed' || data.status === 'waiting_retry') {
                // Update UI for processed items
                if (data.results && Array.isArray(data.results)) {
                    data.results.forEach(item => {
                        const row = document.getElementById('row-' + item.id);
                        if (row) {
                            if (item.status === 'sent') {
                                row.classList.remove('row-processing');
                                row.classList.add('row-success');
                                row.querySelector('.status-cell').innerHTML = `
                                    <div class="flex items-center gap-2 text-green-500 font-bold text-xs">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        <?php echo __('status_sent_small'); ?>
                                    </div>`;
                                const now = new Date();
                                const timeString = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' }) + ' ' + (now.getDate() + '/' + (now.getMonth() + 1));
                                row.querySelector('.msg-cell').innerText = timeString;

                                sentCount++;
                                pendingCount--;
                                const idx = queue.indexOf(item.id);
                                if (idx > -1) queue.splice(idx, 1);
                            } else if (item.status === 'retrying') {
                                row.classList.remove('row-processing');
                                row.querySelector('.status-cell').innerHTML = `
                                    <div class="flex items-center gap-2 text-yellow-500 font-bold text-xs">
                                        <div class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></div>
                                        <?php echo ($lang == 'ar' ? 'إعادة مطابقة' : 'Retrying'); ?>
                                    </div>`;
                                row.querySelector('.msg-cell').innerHTML = `<div class="text-yellow-400 text-[9px]">${item.error}</div>`;
                                // We DON'T remove from queue or pendingCount because it's still coming back
                            } else {
                                row.classList.remove('row-processing');
                                row.classList.add('row-error');
                                row.querySelector('.status-cell').innerHTML = `
                                    <div class="flex items-center gap-2 text-red-500 font-bold text-xs">
                                        <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                                        <?php echo __('status_failed_small'); ?>
                                    </div>`;
                                row.querySelector('.msg-cell').innerHTML = `<div class="text-red-400 max-w-[200px] ml-auto overflow-hidden text-ellipsis">${item.error || 'Error'}</div>`;
                                failedCount++;
                                pendingCount--;
                                const idx = queue.indexOf(item.id);
                                if (idx > -1) queue.splice(idx, 1);
                            }
                        }
                    });
                }

                updateStats();

                if (data.status === 'waiting_retry') {
                    const waitSec = data.next_retry_in || 30;
                    timerDiv.innerHTML = `<span class="text-yellow-400 font-bold uppercase tracking-wider">⏳ <?php echo ($lang == 'ar' ? 'انتظار إعادة المحاولة' : 'Waiting for Retry'); ?> (${waitSec}s)</span>`;
                    setTimeout(processQueue, 5000); // Check again in 5s
                    return;
                }

                // GAP Logic: Wait full interval AFTER processing
                const intervalInput = document.getElementById('interval');
                const waitSeconds = parseInt(intervalInput ? intervalInput.value : <?php echo $campaign['waiting_interval'] ?? 30; ?>) || 30;

                timerDiv.innerHTML = `<span class="text-blue-400 font-bold uppercase tracking-wider">⏳ <?php echo __('waiting'); ?> ${waitSeconds}<?php echo __('unit_s'); ?>...</span>`;

                setTimeout(processQueue, waitSeconds * 1000); // Simple Delay

            } else if (data.status === 'completed') {
                isRunning = false;
                timerDiv.innerHTML = '<span class="text-green-500 font-bold uppercase tracking-wider"><?php echo __('finished_status'); ?></span>';
                document.getElementById('btn-start').classList.remove('hidden');
                document.getElementById('btn-pause').classList.add('hidden');
                updateStats();

                // Show Report Modal
                document.getElementById('report-modal').classList.remove('hidden');
                // Populate Report Data
                document.getElementById('report-sent').innerText = sentCount;
                document.getElementById('report-failed').innerText = failedCount;
                document.getElementById('report-total').innerText = total;

            } else if (data.status === 'stopped') {
                isRunning = false;
                timerDiv.innerHTML = '<span class="text-yellow-500 font-bold uppercase tracking-wider"><?php echo __('paused'); ?></span>';
            } else {
                console.log("Batch response info:", data);
                setTimeout(processQueue, 3000);
            }
        } catch (e) {
            console.error("Batch Error", e);
            setTimeout(processQueue, 5000);
        }
    }

    // Auto-Start Logic Update
    if (shouldAutoStart || (secondsToWait > 0 && secondsToWait < 100000)) {
        // If explicitly autostart OR waiting time is reasonable, we arm the system.
        // Wait, user complained schedule didn't work. The logic ABOVE in window.onload handles the countdown.
        // We just need to ensure startCampaign() is called when timer ends.
        // The check below is for IMMEDIATE start if no wait time.
        if (secondsToWait <= 0 && shouldAutoStart) {
            setTimeout(startCampaign, 500);
        }
    }
    // REMOVED OLD LOGIC
    // Old logic removed





    function stopCampaign() {
        if (!confirm('<?php echo __('confirm_stop_campaign'); ?>')) return;
        window.location.href = 'create_campaign.php?id=<?php echo $campaign_id; ?>';
    }

    function showTokenModal() {
        document.getElementById('token-modal').classList.remove('hidden');
    }

    async function updateToken() {
        const newToken = document.getElementById('new-token-input').value.trim();
        if (!newToken) {
            alert('<?php echo __('enter_valid_token'); ?>');
            return;
        }

        const btn = document.getElementById('btn-update-token');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <?php echo __('updating_btn'); ?>';

        try {
            const formData = new FormData();
            formData.append('action', 'update_token');
            formData.append('campaign_id', <?php echo $campaign_id; ?>);
            formData.append('new_token', newToken);

            const response = await fetch('campaign_token_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                document.getElementById('token-modal').classList.add('hidden');
                alert('<?php echo __('token_updated_success'); ?>');
                // Clean reload to Convert POST to GET and refresh state
                window.location.href = window.location.href;
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error(e);
            alert('<?php echo __('update_token_error'); ?>');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<?php echo __('update_token_btn'); ?>';
        }
    }

    // Old processNext removed.


    async function downloadReportImage() {
        const btn = document.getElementById('btn-download-report');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-black" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <?php echo __('generating_image'); ?>';

        const card = document.getElementById('report-card');

        try {
            const canvas = await html2canvas(card, {
                backgroundColor: '#0f172a',
                scale: 2, // High Quality
                logging: false,
                useCORS: true
            });

            const link = document.createElement('a');
            link.download = 'Marketation_Report_<?php echo $campaign_id; ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        } catch (e) {
            console.error(e);
            alert('<?php echo __('export_error'); ?>');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function saveSettings() {
        const inputInterval = document.getElementById('interval');
        const inputRetryCount = document.getElementById('retry_count');
        const inputRetryDelay = document.getElementById('retry_delay');
        const inputBatchSize = document.getElementById('batch_size'); // NEW
        const btnSave = document.getElementById('btn-save-settings');
        const statusDiv = document.getElementById('status-msg');

        const formData = new FormData();
        formData.append('campaign_id', <?php echo $campaign_id; ?>);
        formData.append('interval', inputInterval.value);
        formData.append('retry_count', inputRetryCount.value);
        formData.append('retry_delay', inputRetryDelay.value);
        if (inputBatchSize) formData.append('batch_size', inputBatchSize.value); // NEW

        // Visual Feedback
        if (btnSave) btnSave.innerText = '<?php echo __('saving_settings'); ?>';
        if (btnSave) btnSave.disabled = true;
        if (statusDiv) {
            statusDiv.innerText = '<?php echo __('saving_settings'); ?>';
            statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-indigo-500 truncate italic animate-pulse';
        }

        fetch('campaign_settings_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (btnSave) {
                    btnSave.innerText = '<?php echo __('save_settings'); ?>';
                    btnSave.disabled = false;
                }

                if (data.status === 'success') {
                    if (statusDiv) {
                        statusDiv.innerText = '<?php echo __('saved_success'); ?>';
                        statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-green-500 truncate italic';
                        setTimeout(() => {
                            if (!isRunning && !isWaiting) {
                                statusDiv.innerText = '<?php echo __('ready_msg'); ?>';
                                statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 truncate italic';
                            }
                        }, 2000);
                    }
                } else {
                    if (statusDiv) {
                        statusDiv.innerText = '<?php echo __('save_failed'); ?>';
                        statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-red-500 truncate italic';
                        setTimeout(() => {
                            if (!isRunning && !isWaiting) {
                                statusDiv.innerText = '<?php echo __('ready_msg'); ?>';
                                statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 truncate italic';
                            }
                        }, 3000);
                    }
                }
            })
            .catch(err => {
                console.error('Error saving settings', err);
                if (btnSave) {
                    btnSave.innerText = '<?php echo __('save_settings'); ?>';
                    btnSave.disabled = false;
                }
                if (statusDiv) {
                    statusDiv.innerText = '<?php echo __('save_failed_network'); ?>';
                    statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-red-500 truncate italic';
                    setTimeout(() => {
                        if (!isRunning && !isWaiting) {
                            statusDiv.innerText = '<?php echo __('ready_msg'); ?>';
                            statusDiv.className = 'flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 truncate italic';
                        }
                    }, 3000);
                }
            });
    }

    window.onload = function () {
        updateStats(); // Initial UI update

        // Clean URL
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Logic for State Restoration
        // 1. If Campaign is ALREADY RUNNING in DB
        if (<?php echo $is_running ? 'true' : 'false'; ?>) {
            console.log("Restoring Running State...");
            isRunning = true;
            document.getElementById('btn-start').classList.add('hidden');
            document.getElementById('btn-pause').classList.remove('hidden');
            document.getElementById('status-msg').innerHTML = '<span class="animate-pulse text-green-400 font-bold uppercase tracking-wider"><?php echo __('sending_batch'); ?> (Resumed)</span>';

            // Kickstart the loops again
            monitorLoop();
            processQueue();
        }
        // 2. If Campaign is SCHEDULED (ARMED)
        else if (<?php echo ($campaign['status'] === 'scheduled') ? 'true' : 'false'; ?>) {
            console.log("Restoring Armed Schedule...");
            if (secondsToWait > 0) {
                startCountdown();
            } else {
                // Time passed while we were away? Auto-start logic handled by Cron, but UI should reflect that.
                executeCampaign();
            }
        }

        // 3. If Paused, do nothing (Classic Manual Mode)

        const inputInterval = document.getElementById('interval');
        const inputRetryCount = document.getElementById('retry_count');
        const inputRetryDelay = document.getElementById('retry_delay');
        const inputBatchSize = document.getElementById('batch_size');

        ['change', 'blur'].forEach(evt => {
            if (inputInterval) inputInterval.addEventListener(evt, saveSettings);
            if (inputRetryCount) inputRetryCount.addEventListener(evt, saveSettings);
            if (inputRetryDelay) inputRetryDelay.addEventListener(evt, saveSettings);
            if (inputBatchSize) inputBatchSize.addEventListener(evt, saveSettings);
        });
    };

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>