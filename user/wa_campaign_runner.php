<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wa_sender_engine.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Get campaign ID
$campaign_id = $_GET['id'] ?? null;
if (!$campaign_id) {
    header("Location: wa_bulk_send.php");
    exit;
}

// Fetch campaign
$stmt = $pdo->prepare("SELECT * FROM wa_campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header("Location: wa_bulk_send.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sender = new WAUniversalSender($pdo, $campaign_id);

    switch ($action) {
        case 'start':
            $sender->start();
            break;
        case 'pause':
            $sender->pause();
            break;
        case 'resume':
            $sender->resume();
            break;
        case 'cancel':
            $sender->cancel();
            break;
    }

    // Refresh campaign data
    $stmt->execute([$campaign_id, $user_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{
    status: '<?php echo $campaign['status']; ?>',
    sentCount: <?php echo $campaign['sent_count']; ?>,
    failedCount: <?php echo $campaign['failed_count']; ?>,
    totalCount: <?php echo $campaign['total_count']; ?>,
    
    get progress() {
        return this.totalCount > 0 ? Math.round((this.sentCount / this.totalCount) * 100) : 0;
    },
    
    get statusColor() {
        switch(this.status) {
            case 'running': return 'text-green-400';
            case 'paused': return 'text-yellow-400';
            case 'completed': return 'text-blue-400';
            case 'failed': return 'text-red-400';
            case 'cancelled': return 'text-gray-400';
            default: return 'text-gray-400';
        }
    },
    
    refreshData() {
        fetch('ajax_campaign_status.php?id=<?php echo $campaign_id; ?>')
            .then(r => r.json())
            .then(data => {
                this.status = data.status;
                this.sentCount = data.sent_count;
                this.failedCount = data.failed_count;
                this.totalCount = data.total_count;
            });
    }
}" x-init="setInterval(() => { if(status === 'running') refreshData() }, 2000)">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-96 h-96 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 -right-4 w-96 h-96 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000 pointer-events-none">
        </div>

        <!-- Header -->
        <div class="mb-10 relative z-10">
            <div class="flex items-center gap-4 mb-4">
                <a href="wa_bulk_send.php"
                    class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight">
                        <?php echo htmlspecialchars($campaign['campaign_name']); ?>
                    </h1>
                    <p class="text-gray-400 text-lg mt-1">
                        <?php echo __('wa_campaign_runner'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Status Card -->
            <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-white">
                        <?php echo __('campaign_status'); ?>
                    </h2>
                    <span
                        class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-sm font-bold uppercase tracking-wider"
                        :class="statusColor" x-text="status"></span>
                </div>

                <!-- Progress Bar -->
                <div class="mb-8">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-400">
                            <?php echo __('progress'); ?>
                        </span>
                        <span class="text-white font-bold" x-text="progress + '%'"></span>
                    </div>
                    <div class="h-4 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-green-500 to-emerald-500 transition-all duration-500"
                            :style="'width: ' + progress + '%'"></div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10">
                        <div class="text-gray-400 text-sm mb-2">
                            <?php echo __('total_messages'); ?>
                        </div>
                        <div class="text-3xl font-black text-white" x-text="totalCount"></div>
                    </div>
                    <div class="bg-green-500/10 rounded-2xl p-6 border border-green-500/20">
                        <div class="text-green-400 text-sm mb-2">
                            <?php echo __('sent_successfully'); ?>
                        </div>
                        <div class="text-3xl font-black text-green-400" x-text="sentCount"></div>
                    </div>
                    <div class="bg-red-500/10 rounded-2xl p-6 border border-red-500/20">
                        <div class="text-red-400 text-sm mb-2">
                            <?php echo __('failed'); ?>
                        </div>
                        <div class="text-3xl font-black text-red-400" x-text="failedCount"></div>
                    </div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                <h3 class="text-xl font-bold text-white mb-6">
                    <?php echo __('campaign_controls'); ?>
                </h3>
                <form method="POST" class="flex flex-wrap gap-4">
                    <?php if ($campaign['status'] === 'pending' || $campaign['status'] === 'paused'): ?>
                        <button type="submit" name="action"
                            value="<?php echo $campaign['status'] === 'pending' ? 'start' : 'resume'; ?>"
                            class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-4 px-8 rounded-2xl shadow-xl transition-all transform active:scale-95 flex items-center justify-center gap-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>
                                <?php echo $campaign['status'] === 'pending' ? __('start_campaign') : __('resume_campaign'); ?>
                            </span>
                        </button>
                    <?php endif; ?>

                    <?php if ($campaign['status'] === 'running'): ?>
                        <button type="submit" name="action" value="pause"
                            class="flex-1 bg-gradient-to-r from-yellow-600 to-orange-600 hover:from-yellow-500 hover:to-orange-500 text-white font-bold py-4 px-8 rounded-2xl shadow-xl transition-all transform active:scale-95 flex items-center justify-center gap-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>
                                <?php echo __('pause_campaign'); ?>
                            </span>
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($campaign['status'], ['pending', 'running', 'paused'])): ?>
                        <button type="submit" name="action" value="cancel"
                            class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-500 hover:to-pink-500 text-white font-bold py-4 px-8 rounded-2xl shadow-xl transition-all transform active:scale-95 flex items-center justify-center gap-3"
                            onclick="return confirm('<?php echo __('confirm_cancel_campaign'); ?>')">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span>
                                <?php echo __('cancel_campaign'); ?>
                            </span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Campaign Details -->
            <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                <h3 class="text-xl font-bold text-white mb-6">
                    <?php echo __('campaign_details'); ?>
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between py-3 border-b border-white/5">
                        <span class="text-gray-400">
                            <?php echo __('gateway_mode'); ?>
                        </span>
                        <span class="text-white font-bold uppercase">
                            <?php echo $campaign['gateway_mode']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-white/5">
                        <span class="text-gray-400">
                            <?php echo __('media_type'); ?>
                        </span>
                        <span class="text-white font-bold">
                            <?php echo $campaign['media_type']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-white/5">
                        <span class="text-gray-400">
                            <?php echo __('delay_range'); ?>
                        </span>
                        <span class="text-white font-bold">
                            <?php echo $campaign['delay_min']; ?>s -
                            <?php echo $campaign['delay_max']; ?>s
                        </span>
                    </div>
                    <div class="flex justify-between py-3">
                        <span class="text-gray-400">
                            <?php echo __('created_at'); ?>
                        </span>
                        <span class="text-white font-bold">
                            <?php echo date('Y-m-d H:i', strtotime($campaign['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>