<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// CSV Export Removed per user request

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id'])) {
    $camp_id = $_POST['id'];
    // Confirm ownership implicitly via nested query or simple check
    $check = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND user_id = ?");
    $check->execute([$camp_id, $user_id]);

    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM campaign_queue WHERE campaign_id = ?")->execute([$camp_id]);
        $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$camp_id]);
        header("Location: campaign_reports.php?msg=deleted");
        exit;
    }
}

// Fetch History
$histStmt = $pdo->prepare("
    SELECT c.*, p.page_name, 
    (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = c.id AND status = 'sent') as sent_count,
    (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = c.id AND status = 'failed') as failed_count
    FROM campaigns c 
    LEFT JOIN fb_pages p ON c.page_id = p.id 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC
");
$histStmt->execute([$user_id]);
$history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .offscreen-render {
        position: fixed !important;
        left: -9999px !important;
        top: 0 !important;
        opacity: 1 !important;
        display: flex !important;
        z-index: -100 !important;
    }
</style>
<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <!-- Breadcrumb -->
        <div class="flex items-center text-sm text-gray-400 mb-6">
            <span class="text-white font-bold tracking-wide">
                <?php echo __('campaign_reports'); ?>
            </span>
        </div>

        <div class="flex flex-col gap-8">
            <!-- Campaign History Section -->
            <div class="mt-0">
                <div class="flex items-center gap-4 mb-6">
                    <div
                        class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-500 border border-indigo-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">
                        <?php echo __('campaign_reports'); ?>
                    </h2>
                </div>

                <div class="glass-card rounded-3xl overflow-hidden border border-white/5">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-black/20 text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-6 py-4"><?php echo __('id'); ?></th>
                                    <th class="px-6 py-4">
                                        <?php echo __('campaign_name'); ?>
                                    </th>
                                    <th class="px-6 py-4"><?php echo __('page'); ?></th>
                                    <th class="px-6 py-4">
                                        <?php echo __('status'); ?>
                                    </th>
                                    <th class="px-6 py-4 text-center">
                                        <?php echo __('sent'); ?> /
                                        <?php echo __('total'); ?>
                                    </th>
                                    <th class="px-6 py-4 text-right">
                                        <?php echo __('date_created'); ?>
                                    </th>
                                    <th class="px-6 py-4 text-right">
                                        <?php echo __('actions'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            <?php echo __('no_campaigns_found'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($history as $camp):
                                    $statusClass = 'bg-gray-500/10 text-gray-400';
                                    $statusKey = $camp['status'];

                                    // Smart Status Logic
                                    $is_started = $camp['sent_count'] > 0 || $camp['failed_count'] > 0;
                                    $is_finished = ($camp['sent_count'] + $camp['failed_count']) >= $camp['total_leads'] && $camp['total_leads'] > 0;

                                    if ($camp['status'] == 'completed' || $is_finished) {
                                        $statusClass = 'bg-green-500/10 text-green-500 border border-green-500/20';
                                        $statusKey = 'status_completed';
                                    } elseif ($camp['status'] == 'running' || ($camp['status'] == 'scheduled' && $is_started)) {
                                        $statusClass = 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
                                        $statusKey = 'status_running';
                                    } elseif ($camp['status'] == 'scheduled') {
                                        $statusClass = 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
                                        $statusKey = 'status_scheduled';
                                    } elseif ($camp['status'] == 'failed') {
                                        $statusClass = 'bg-red-500/10 text-red-500 border border-red-500/20';
                                        $statusKey = 'report_failed';
                                    } elseif ($camp['status'] == 'pending') {
                                        $statusClass = 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/20';
                                        $statusKey = 'status_pending_small'; // Use existing key or fallback
                                    }

                                    // Translate safely
                                    $statusLabel = __($statusKey);
                                    if ($statusLabel === $statusKey) {
                                        // Fallback translation
                                        $statusLabel = ucfirst($camp['status']);
                                    }
                                    ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-6 py-4 font-mono text-gray-500">#<?php echo $camp['id']; ?></td>
                                        <td class="px-6 py-4 font-bold text-white">
                                            <?php echo htmlspecialchars($camp['name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-gray-300">
                                            <?php echo htmlspecialchars($camp['page_name'] ?? 'Unknown'); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-3 py-1 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                                                <?php echo $statusLabel; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="text-green-400 font-bold"><?php echo $camp['sent_count']; ?></span>
                                            <span class="text-gray-600 mx-1">/</span>
                                            <span class="text-white font-bold"><?php echo $camp['total_leads']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-gray-400">
                                            <?php echo date('Y-m-d', strtotime($camp['created_at'])); ?>
                                            <br>
                                            <span
                                                class="text-[10px] text-gray-600"><?php echo date('H:i', strtotime($camp['created_at'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Monitor -->
                                                <a href="campaign_runner.php?id=<?php echo $camp['id']; ?>"
                                                    title="<?php echo __('monitor_campaign'); ?>"
                                                    class="px-2 py-2 bg-indigo-500/10 hover:bg-indigo-500 text-indigo-500 hover:text-white rounded-xl transition-all border border-indigo-500/20">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                                        </path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                </a>

                                                <!-- View (Eye) -->
                                                <button onclick='viewReport(<?php echo json_encode([
                                                    "id" => $camp['id'],
                                                    "name" => $camp['name'],
                                                    "sent" => $camp['sent_count'],
                                                    "failed" => $camp['failed_count'],
                                                    "total" => $camp['total_leads'],
                                                    "date" => date('Y-m-d H:i', strtotime($camp['created_at']))
                                                ]); ?>, "view")' title="<?php echo __('view_report'); ?>"
                                                    class="px-2 py-2 bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-white rounded-xl transition-all border border-blue-500/20">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                        </path>
                                                    </svg>
                                                </button>

                                                <!-- Delete -->
                                                <form method="POST"
                                                    onsubmit="return confirm('<?php echo __('confirm_delete'); ?>');"
                                                    class="inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $camp['id']; ?>">
                                                    <button type="submit" title="<?php echo __('delete_campaign'); ?>"
                                                        class="px-2 py-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition-all border border-red-500/20">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Report Modal (Hidden) -->
<div id="report-modal"
    class="fixed inset-0 z-[100] hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
    <div class="w-full max-w-lg animate-in fade-in zoom-in duration-300 m-auto">
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
                    <h3 id="modal-camp-name" class="text-xl font-bold text-white">---</h3>
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
                        <h4 id="modal-sent" class="text-4xl font-black text-white leading-none">0</h4>
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
                        <h4 id="modal-failed" class="text-4xl font-black text-white leading-none">0</h4>
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
                            <p class="text-xs text-gray-400">
                                <?php echo __('report_total_recipients'); ?>
                            </p>
                            <p id="modal-total" class="font-bold text-white font-mono">0
                                <?php echo __('report_people'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">
                            <?php echo __('report_date_logged'); ?>
                        </p>
                        <p id="modal-date" class="font-bold text-gray-300 text-xs">---</p>
                    </div>
                </div>
            </div>

            <!-- Branding Bar -->
            <div class="bg-indigo-600 px-10 py-3 flex justify-between items-center">
                <span class="text-[9px] text-white/60 font-medium tracking-[0.2em]">
                    <?php echo __('report_powered_by'); ?>
                </span>
                <div class="flex gap-1">
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-white/30"></div>
                </div>
            </div>
        </div>

        <!-- Meta Controls -->
        <div class="mt-8 flex flex-col sm:flex-row gap-4">
            <button onclick="downloadCurrentReport()"
                class="flex-1 py-4 bg-white text-black font-black rounded-2xl shadow-xl hover:bg-gray-100 transition-all flex items-center justify-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <?php echo __('report_save_image'); ?>
            </button>
            <button onclick="document.getElementById('report-modal').classList.add('hidden')"
                class="px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl transition-all border border-white/10">
                <?php echo __('report_close'); ?>
            </button>
        </div>
    </div>
</div>

<!-- html2canvas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    async function viewReport(data, mode) {
        // Fill Data
        document.getElementById('modal-camp-name').textContent = data.name;
        document.getElementById('modal-sent').textContent = data.sent;
        document.getElementById('modal-failed').textContent = data.failed;
        document.getElementById('modal-total').textContent = data.total + ' ' + '<?php echo __('report_people'); ?>';
        document.getElementById('modal-date').textContent = data.date;

        const modal = document.getElementById('report-modal');

        // Handle Modes
        if (mode === 'view') {
            modal.classList.remove('offscreen-render');
            modal.classList.remove('hidden');
        }
        else if (mode === 'download') {
            // Render off-screen, capture, then hide
            modal.classList.add('offscreen-render');
            modal.classList.remove('hidden');

            try {
                // Wait a moment for rendering
                await new Promise(resolve => setTimeout(resolve, 100));

                const card = document.getElementById('report-card');
                const canvas = await html2canvas(card, {
                    scale: 2,
                    backgroundColor: null,
                    useCORS: true,
                    logging: false,
                    allowTaint: true
                });

                const link = document.createElement('a');
                link.download = `marketation-report-${data.id}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();

            } catch (err) {
                console.error(err);
                alert('Error generating image');
            } finally {
                modal.classList.add('hidden');
                modal.classList.remove('offscreen-render');
            }
        }
    }

    async function downloadCurrentReport() {
        const card = document.getElementById('report-card');
        try {
            const canvas = await html2canvas(card, {
                scale: 2,
                backgroundColor: null,
                useCORS: true,
                allowTaint: true
            });
            const link = document.createElement('a');
            link.download = `report-${Date.now()}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        } catch (err) {
            console.error(err);
            alert('Error exporting image');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>