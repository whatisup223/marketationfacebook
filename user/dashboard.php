<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch User Stats
$stats = [
    'connected_accounts' => $pdo->query("SELECT COUNT(*) FROM fb_accounts WHERE user_id = $user_id AND is_active=1")->fetchColumn(),
    'campaigns' => $pdo->query("SELECT COUNT(*) FROM campaigns WHERE user_id = $user_id")->fetchColumn(),
    'total_leads' => $pdo->query("SELECT COALESCE(SUM(total_leads), 0) FROM campaigns WHERE user_id = $user_id")->fetchColumn(),
    'active_campaigns' => $pdo->query("SELECT COUNT(*) FROM campaigns WHERE user_id = $user_id AND status='running'")->fetchColumn(),
    'support_tickets' => getUserTicketUnreadCount($_SESSION['user_id']),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute top-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000 pointer-events-none">
        </div>
        <div
            class="absolute -bottom-8 left-20 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000 pointer-events-none">
        </div>

        <!-- Header Section -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <div class="flex items-center justify-between mb-4">
                <div
                    class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                    <span
                        class="text-blue-300 text-xs font-bold uppercase tracking-widest"><?php echo __('user_panel'); ?></span>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('overview'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('user_dashboard_desc'); ?></p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10 relative z-10">
            <!-- Connected FB Accounts -->
            <div class="group relative animate-fade-in" style="animation-delay: 100ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-blue-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-blue-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500/20 to-blue-600/5 border border-white/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(59,130,246,0.5)]" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            <?php echo __('connected'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('fb_accounts'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['connected_accounts']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                            </path>
                        </svg>
                        <?php echo __('active_linked_accounts'); ?>
                    </div>
                </div>
            </div>

            <!-- Total Campaigns -->
            <div class="group relative animate-fade-in" style="animation-delay: 200ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-indigo-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-indigo-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-indigo-600/5 border border-white/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?php echo __('campaigns'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('total_campaigns'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['campaigns']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <?php echo __('all_time_campaigns'); ?>
                    </div>
                </div>
            </div>

            <!-- Total Leads Extracted -->
            <div class="group relative animate-fade-in" style="animation-delay: 300ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-green-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-green-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-green-500/20 to-green-600/5 border border-white/10 flex items-center justify-center text-green-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(34,197,94,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-green-500/10 text-green-400 border border-green-500/20">
                            <?php echo __('extracted'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('total_leads'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo number_format($stats['total_leads']); ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo __('from_all_campaigns'); ?>
                    </div>
                </div>
            </div>

            <!-- Active Campaigns -->
            <div class="group relative animate-fade-in" style="animation-delay: 400ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-purple-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-purple-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500/20 to-purple-600/5 border border-white/10 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(168,85,247,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-500/10 text-purple-400 border border-purple-500/20 animate-pulse">
                            <?php echo __('running'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('active_campaigns'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['active_campaigns']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo __('currently_running'); ?>
                    </div>
                </div>
            </div>

            <!-- Support Tickets -->
            <div class="group relative animate-fade-in" style="animation-delay: 500ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-yellow-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-yellow-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-yellow-500/20 to-yellow-600/5 border border-white/10 flex items-center justify-center text-yellow-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(234,179,8,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">
                            <?php echo __('support'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('support_tickets'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['support_tickets']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                        <?php echo __('unread_responses'); ?>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="group relative animate-fade-in" style="animation-delay: 600ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-cyan-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-cyan-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-cyan-600/5 border border-white/10 flex items-center justify-center text-cyan-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(6,182,212,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                            <?php echo __('verified'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('account_status'); ?>
                    </div>
                    <div class="text-2xl font-black text-white mb-2">
                        <?php echo __('active'); ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                        <?php echo __('active_membership'); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>