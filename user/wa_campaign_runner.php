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

// Release session lock to prevent blocking parallel requests
session_write_close();

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$campaign_id = $_GET['id'] ?? 0;

// Fetch Campaign Info
$stmt = $pdo->prepare("SELECT * FROM wa_campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    die(__('campaign_not_found'));
}

// Get stats
$total = (int) ($campaign['total_count'] ?? 0);
$sent = (int) ($campaign['sent_count'] ?? 0);
$failed = (int) ($campaign['failed_count'] ?? 0);
$pending = max(0, $total - ($sent + $failed));

// Check if running
$is_running = $campaign['status'] === 'running';

// --- Prepare Queue Items (Pagination) ---
$numbers = json_decode($campaign['numbers'], true) ?: [];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$total_items = count($numbers);
$total_pages = ceil($total_items / $per_page);
$offset = ($page - 1) * $per_page;
$current_slice = array_slice($numbers, $offset, $per_page);

$queue_items = [];
$current_index = $campaign['current_number_index'];

foreach ($current_slice as $idx => $phone) {
    // Calculate global index
    $global_index = $offset + $idx;

    // Determine status
    $status = 'pending';
    if ($global_index < $current_index) {
        $status = 'sent';
        // Ideally checking specific failed log would be better, but index-based is good approximation for now
    }

    // Check error log for failed numbers if available (Optional enhancement)
    // $campaign['error_log'] is JSON array of errors.

    $queue_items[] = [
        'id' => $global_index,
        'phone' => $phone,
        'status' => $status,
        'sent_at' => ($status == 'sent') ? 'Done' : null
    ];
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
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .status-transition {
        transition: all 0.3s ease;
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
            <a href="wa_bulk_send.php"
                class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors group">
                <div
                    class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center border border-white/5 group-hover:border-green-500/30 group-hover:bg-green-500/10 transition-all">
                    <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                        </path>
                    </svg>
                </div>
                <span class="text-sm font-bold"><?php echo __('back_to_campaigns'); ?></span>
            </a>
        </div>

        <!-- Dashboard Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                    <span class="p-2 bg-green-500 rounded-lg shadow-lg shadow-green-500/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                        </svg>
                    </span>
                    <?php echo __('wa_runner_title'); ?>
                </h1>
                <div class="flex items-center gap-2 text-gray-500 text-sm">
                    <span class="w-2 h-2 rounded-full bg-gray-600"></span>
                    <?php echo __('campaign_label'); ?> <span
                        class="font-bold text-gray-300"><?php echo htmlspecialchars($campaign['campaign_name']); ?></span>
                </div>
            </div>

            <!-- Controls -->
            <div class="w-full lg:w-auto flex flex-wrap items-center gap-4">
                <!-- Status Display -->
                <div
                    class="flex-1 min-w-[280px] h-14 bg-gray-800/40 backdrop-blur-md rounded-2xl border border-white/5 flex items-center p-1.5 shadow-inner">
                    <div id="status-msg"
                        class="flex-1 px-4 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 truncate italic">
                        <?php echo __('ready_msg'); ?>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex items-center gap-2 h-14">
                    <button id="btn-start" onclick="startCampaign()"
                        class="h-full px-8 bg-green-600 hover:bg-green-700 text-white font-black text-[11px] uppercase tracking-widest rounded-2xl shadow-lg shadow-green-600/20 transition-all flex items-center justify-center gap-3 whitespace-nowrap active:scale-95">
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
                        <span id="eta-text" class="text-sm font-bold text-green-400">---</span>
                    </div>
                </div>
                <!-- Progress Bar -->
                <div class="w-full bg-gray-800 rounded-2xl h-8 p-1 relative mb-6">
                    <div id="progress-bar"
                        class="h-full bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl transition-all duration-700 ease-out flex items-center justify-end pr-2 overflow-hidden"
                        style="width: 0%">
                        <div class="w-full h-full opacity-10 animate-pulse bg-white/20"></div>
                    </div>
                </div>

                <!-- Delay Controls Inside Card -->
                <div class="flex items-center gap-6 pt-4 border-t border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                                <?php echo __('delay_min'); ?>
                            </p>
                            <div class="flex items-center gap-1">
                                <input type="number" id="delay_min"
                                    value="<?php echo intval($campaign['delay_min'] ?? 10); ?>" min="1"
                                    class="w-10 bg-transparent text-white font-black focus:outline-none text-sm">
                                <span
                                    class="text-[10px] text-gray-600 font-bold uppercase"><?php echo __('unit_s'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                                <?php echo __('delay_max'); ?>
                            </p>
                            <div class="flex items-center gap-1">
                                <input type="number" id="delay_max"
                                    value="<?php echo intval($campaign['delay_max'] ?? 25); ?>" min="1"
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

        <!-- Queue Table -->
        <div class="bg-gray-900/40 rounded-3xl border border-white/5 overflow-hidden shadow-2xl mb-10">
            <div class="px-8 py-5 border-b border-white/5 bg-white/5 flex justify-between items-center flex-wrap gap-4">
                <h2 class="text-lg font-bold text-white"><?php echo __('queue_list'); ?></h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-mono">Page: #<?php echo $page; ?></span>
                    <div class="w-px h-4 bg-gray-700"></div>
                    <span class="text-xs text-gray-400"><?php echo __('total_messages'); ?>:
                        <b><?php echo $total_items; ?></b></span>
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
                        <?php if (empty($queue_items)): ?>
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
                                    <div class="text-sm font-bold text-white font-mono">
                                        <?php echo htmlspecialchars($item['phone']); ?>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-right msg-cell font-mono text-[10px] text-gray-500">
                                    <?php echo $item['sent_at'] ? $item['sent_at'] : '--:--'; ?>
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
                            <?php echo __('page'); ?>     <?php echo $page; ?> / <?php echo $total_pages; ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?id=<?php echo $campaign_id; ?>&page=<?php echo $page - 1; ?>"
                                    class="px-3 py-1 bg-white/10 rounded hover:bg-white/20 text-xs text-white transition-colors border border-white/5"><?php echo __('prev'); ?></a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?id=<?php echo $campaign_id; ?>&page=<?php echo $page + 1; ?>"
                                    class="px-3 py-1 bg-white/10 rounded hover:bg-white/20 text-xs text-white transition-colors border border-white/5"><?php echo __('next'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Campaign Details -->
        <div class="bg-gray-900/40 rounded-3xl border border-white/5 overflow-hidden shadow-2xl">
            <div class="px-8 py-5 border-b border-white/5 bg-white/5 flex justify-between items-center flex-wrap gap-4">
                <h2 class="text-lg font-bold text-white"><?php echo __('campaign_details'); ?></h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-mono">ID: #<?php echo $campaign_id; ?></span>
                    <div class="w-px h-4 bg-gray-700"></div>
                    <span class="text-xs text-gray-400"><?php echo __('total_messages'); ?>:
                        <b><?php echo $total; ?></b></span>
                </div>
            </div>

            <div class="p-8 space-y-4">
                <div class="flex justify-between py-3 border-b border-white/5">
                    <span class="text-gray-400"><?php echo __('gateway_mode'); ?></span>
                    <span class="text-white font-bold uppercase"><?php echo $campaign['gateway_mode']; ?></span>
                </div>
                <div class="flex justify-between py-3 border-b border-white/5">
                    <span class="text-gray-400"><?php echo __('media_type'); ?></span>
                    <span class="text-white font-bold"><?php echo $campaign['media_type']; ?></span>
                </div>
                <div class="flex justify-between py-3 border-b border-white/5">
                    <span class="text-gray-400"><?php echo __('message_preview'); ?></span>
                    <span
                        class="text-white font-bold text-sm max-w-md truncate"><?php echo htmlspecialchars(substr($campaign['message'], 0, 50)) . '...'; ?></span>
                </div>
                <div class="flex justify-between py-3">
                    <span class="text-gray-400"><?php echo __('created_at'); ?></span>
                    <span
                        class="text-white font-bold"><?php echo date('Y-m-d H:i', strtotime($campaign['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let isRunning = false;
    let sentCount = <?php echo $sent; ?>;
    let failedCount = <?php echo $failed; ?>;
    let total = <?php echo $total; ?>;
    let pendingCount = <?php echo $pending; ?>;

    let campaignId = <?php echo $campaign_id; ?>;
    let sendInterval = null;
    let statusInterval = null;

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
        let delayMin = parseInt(document.getElementById('delay_min').value) || 10;
        let delayMax = parseInt(document.getElementById('delay_max').value) || 25;
        let avgDelay = (delayMin + delayMax) / 2;
        let remaining = pendingCount * avgDelay;
        if (remaining > 0) {
            if (remaining < 60) {
                document.getElementById('eta-text').innerText = remaining.toFixed(0) + 's';
            } else {
                let m = Math.floor(remaining / 60);
                let s = Math.floor(remaining % 60);
                document.getElementById('eta-text').innerText = `${m}m ${s}s`;
            }
        } else {
            document.getElementById('eta-text').innerText = '<?php echo __('finished_status'); ?>';
        }
    }

    async function startCampaign() {
        if (isRunning) return;

        const btnStart = document.getElementById('btn-start');
        const btnPause = document.getElementById('btn-pause');
        const statusMsg = document.getElementById('status-msg');

        try {
            // Start campaign
            const formData = new FormData();
            formData.append('action', 'start');
            formData.append('campaign_id', campaignId);

            const response = await fetch('api_wa_campaign.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                btnStart.classList.add('hidden');
                btnPause.classList.remove('hidden');
                isRunning = true;

                statusMsg.innerHTML = '<span class="text-green-400 font-bold animate-pulse">🚀 <?php echo __('campaign_running'); ?></span>';

                // Start sending messages
                startSending();

                // Start status polling
                startStatusPolling();
            } else {
                alert(data.message || 'Failed to start campaign');
            }
        } catch (e) {
            console.error('Start error:', e);
            alert('Failed to start campaign');
        }
    }

    async function pauseCampaign() {
        if (!isRunning) return;

        try {
            const formData = new FormData();
            formData.append('action', 'pause');
            formData.append('campaign_id', campaignId);

            const response = await fetch('api_wa_campaign.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                isRunning = false;
                clearInterval(sendInterval);
                clearInterval(statusInterval);

                document.getElementById('btn-start').classList.remove('hidden');
                document.getElementById('btn-pause').classList.add('hidden');
                document.getElementById('status-msg').innerHTML = '<span class="text-yellow-400">⏸️ <?php echo __('paused'); ?></span>';
            }
        } catch (e) {
            console.error('Pause error:', e);
        }
    }

    async function stopCampaign() {
        if (!confirm('<?php echo __('confirm_stop_campaign'); ?>')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('campaign_id', campaignId);

            await fetch('api_wa_campaign.php', {
                method: 'POST',
                body: formData
            });

            window.location.href = 'wa_bulk_send.php';
        } catch (e) {
            console.error('Stop error:', e);
        }
    }

    function startSending() {
        // Send messages with delay
        sendInterval = setInterval(async () => {
            if (!isRunning) {
                clearInterval(sendInterval);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'send_next_batch');
                formData.append('campaign_id', campaignId);

                const response = await fetch('api_wa_campaign.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Check for forced wait (Batch Pause)
                if (data.status === 'success' && data.force_wait > 0) {
                    clearInterval(sendInterval);
                    const waitSeconds = parseInt(data.force_wait);

                    // Update status message
                    const statusMsg = document.getElementById('status-msg');
                    let remaining = waitSeconds;

                    statusMsg.innerHTML = `<span class="text-orange-400">⏳ Paused for Batch Delay: ${remaining}s</span>`;

                    const countdown = setInterval(() => {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(countdown);
                            statusMsg.innerHTML = '<span class="text-green-400 font-bold animate-pulse">🚀 Resuming...</span>';
                            startSending(); // Resume sending
                        } else {
                            statusMsg.innerHTML = `<span class="text-orange-400">⏳ Paused for Batch Delay: ${remaining}s</span>`;
                        }
                    }, 1000);

                    // Update stats before pausing
                    sentCount = data.sent_count;
                    failedCount = data.failed_count;
                    pendingCount = data.total_count - (sentCount + failedCount);
                    updateStats();

                    return; // Exit current loop and wait for resume
                }

                if (data.status === 'completed') {
                    // Campaign finished
                    isRunning = false;
                    clearInterval(sendInterval);
                    clearInterval(statusInterval);

                    document.getElementById('btn-start').classList.add('hidden');
                    document.getElementById('btn-pause').classList.add('hidden');
                    document.getElementById('status-msg').innerHTML = '<span class="text-blue-400">✅ <?php echo __('campaign_completed'); ?></span>';

                    // Update final stats
                    sentCount = data.sent_count;
                    failedCount = data.failed_count;
                    pendingCount = 0;
                    updateStats();

                    alert('<?php echo __('campaign_completed'); ?>');
                } else if (data.status === 'success') {
                    // Message sent, update stats
                    sentCount = data.sent_count;
                    failedCount = data.failed_count;
                    pendingCount = data.total_count - (sentCount + failedCount);
                    updateStats();

                    if (data.result.success) {
                        console.log(`✅ Sent to ${data.number}: Success`);
                    } else {
                        console.error(`❌ Failed to send to ${data.number}:`, data.result.error);
                    }
                } else if (data.status === 'error') {
                    if (data.message === 'Campaign not running' || data.message === 'Campaign completed') {
                        console.warn('Campaign stopped or completed server-side.');
                        isRunning = false;
                        clearInterval(sendInterval);
                        sendInterval = null;
                        updateStats(); // Update final status
                        return;
                    }
                    console.error('Send error:', data.message);
                }
            } catch (e) {
                console.error('Send error:', e);
            }

            // Random delay between messages
            let delayMin = parseInt(document.getElementById('delay_min').value) || 10;
            let delayMax = parseInt(document.getElementById('delay_max').value) || 25;
            let delay = Math.floor(Math.random() * (delayMax - delayMin + 1)) + delayMin;

            clearInterval(sendInterval);
            sendInterval = setTimeout(() => startSending(), delay * 1000);
        }, 100); // Initial trigger
    }

    function startStatusPolling() {
        // Poll status every 5 seconds
        statusInterval = setInterval(async () => {
            if (!isRunning) {
                clearInterval(statusInterval);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'get_status');
                formData.append('campaign_id', campaignId);

                const response = await fetch('api_wa_campaign.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    sentCount = data.sent_count;
                    failedCount = data.failed_count;
                    pendingCount = data.total_count - (sentCount + failedCount);
                    updateStats();

                    // Check if campaign stopped externally
                    if (data.campaign_status !== 'running') {
                        isRunning = false;
                        clearInterval(sendInterval);
                        clearInterval(statusInterval);
                        document.getElementById('btn-start').classList.remove('hidden');
                        document.getElementById('btn-pause').classList.add('hidden');
                    }
                }
            } catch (e) {
                console.error('Status poll error:', e);
            }
        }, 5000);
    }

    // Initialize
    updateStats();

    // Auto-resume if campaign is already running
    <?php if ($campaign['status'] === 'running'): ?>
        isRunning = true;
        document.getElementById('btn-start').classList.add('hidden');
        document.getElementById('btn-pause').classList.remove('hidden');
        document.getElementById('status-msg').innerHTML = '<span class="text-green-400 font-bold animate-pulse">🚀 <?php echo __('campaign_running'); ?></span>';
        startSending();
        startStatusPolling();
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>