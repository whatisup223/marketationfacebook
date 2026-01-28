<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Fetch New Stats for SaaS System
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'active_subs' => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn(),
    'fb_accounts' => $pdo->query("SELECT COUNT(*) FROM fb_accounts WHERE is_active=1")->fetchColumn(),
    'campaigns' => $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn(),
    'support_open' => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn(),
    'total_leads' => $pdo->query("SELECT COUNT(*) FROM fb_leads")->fetchColumn(), // Count all extracted leads
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 min-w-0 p-4 md:p-8 relative">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 left-0 w-72 h-72 bg-indigo-500/10 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute top-0 right-0 w-72 h-72 bg-purple-500/10 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000 pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 left-1/2 w-72 h-72 bg-pink-500/10 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000 pointer-events-none">
        </div>

        <!-- Header Section -->
        <div class="relative z-10 mb-10 animate-fade-in">
            <div class="flex items-center gap-4 mb-2">
                <div
                    class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    <span
                        class="text-indigo-300 text-xs font-bold uppercase tracking-widest"><?php echo __('admin_panel'); ?></span>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('overview'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('system_statistics_overview'); ?></p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10 relative z-10">
            <!-- Active Subscriptions -->
            <div class="group relative animate-fade-in" style="animation-delay: 100ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-indigo-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-indigo-500/30 transition-all duration-500 overflow-hidden">
                    <!-- Icon -->
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-indigo-600/5 border border-white/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?php echo __('active'); ?>
                        </span>
                    </div>
                    <!-- Stats -->
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('active_subscriptions'); ?>
                    </div>
                    <div
                        class="text-4xl font-black text-white mb-2 bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-purple-500">
                        <?php echo $stats['active_subs']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        <?php echo __('paid_trial_users'); ?>
                    </div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="group relative animate-fade-in" style="animation-delay: 200ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-blue-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-blue-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500/20 to-blue-600/5 border border-white/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(59,130,246,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            <?php echo __('total'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('users'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['users']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        <?php echo __('registered_members'); ?>
                    </div>
                </div>
            </div>

            <!-- FB Accounts -->
            <div class="group relative animate-fade-in" style="animation-delay: 300ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-cyan-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-cyan-500/30 transition-all duration-500 overflow-hidden">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-cyan-600/5 border border-white/10 flex items-center justify-center text-cyan-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(6,182,212,0.5)]" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                            <?php echo __('connected'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('connected_fb_accounts'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['fb_accounts']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                            </path>
                        </svg>
                        <?php echo __('total_linked_profiles'); ?>
                    </div>
                </div>
            </div>

            <!-- Campaigns Run -->
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
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-500/10 text-purple-400 border border-purple-500/20">
                            <?php echo __('campaigns'); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs font-bold uppercase mb-2 tracking-wider">
                        <?php echo __('campaigns_launched'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['campaigns']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                        <?php echo __('active_automation'); ?>
                    </div>
                </div>
            </div>

            <!-- Open Tickets -->
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
                        <?php echo __('open_tickets'); ?>
                    </div>
                    <div class="text-4xl font-black text-white mb-2">
                        <?php echo $stats['support_open']; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                        <?php echo __('needs_attention'); ?>
                    </div>
                </div>
            </div>

            <!-- Total Leads Extracted -->
            <div class="group relative animate-fade-in" style="animation-delay: 600ms;">
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
                        <?php echo __('total_leads_extracted'); ?>
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
        </div>

        <!-- Recent Campaigns -->
        <div class="glass-card rounded-[2.5rem] p-8 overflow-hidden mb-8 relative z-10 border border-white/10 animate-fade-in"
            style="animation-delay: 600ms;">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-black text-white mb-1 tracking-tight"><?php echo __('recent_campaigns'); ?>
                    </h2>
                    <p class="text-sm text-gray-500"><?php echo __('latest_campaign_activity'); ?></p>
                </div>
                <div
                    class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-500/20 to-purple-600/5 border border-white/10 flex items-center justify-center text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-4 pl-2 text-xs font-bold uppercase tracking-wider"><?php echo __('id'); ?>
                            </th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('user'); ?></th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider">
                                <?php echo __('campaign_name'); ?>
                            </th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('leads'); ?></th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('status'); ?></th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('date'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <?php
                        $stmt = $pdo->query("SELECT c.*, u.name as user_name 
                                            FROM campaigns c 
                                            LEFT JOIN users u ON c.user_id = u.id 
                                            ORDER BY c.created_at DESC LIMIT 5");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-white/5 transition-all duration-300 group">
                                <td class="py-4 pl-2 font-mono text-sm text-gray-500 group-hover:text-gray-400">
                                    #<?php echo $row['id']; ?></td>
                                <td class="py-4 font-semibold text-gray-300 group-hover:text-white transition-colors">
                                    <?php echo htmlspecialchars($row['user_name'] ?? __('unknown')); ?>
                                </td>
                                <td class="py-4 text-white font-medium">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>
                                <td class="py-4 text-gray-300 font-bold">
                                    <?php echo number_format($row['total_leads']); ?>
                                </td>
                                <td class="py-4">
                                    <span class="px-3 py-1.5 rounded-xl text-xs font-bold inline-flex items-center gap-2
                                    <?php
                                    if ($row['status'] == 'completed')
                                        echo 'bg-green-500/20 text-green-400 border border-green-500/30';
                                    elseif ($row['status'] == 'running')
                                        echo 'bg-blue-500/20 text-blue-400 border border-blue-500/30 animate-pulse';
                                    elseif ($row['status'] == 'scheduled')
                                        echo 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
                                    else
                                        echo 'bg-gray-500/20 text-gray-400 border border-gray-500/30';
                                    ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php
                                        if ($row['status'] == 'completed')
                                            echo 'bg-green-400';
                                        elseif ($row['status'] == 'running')
                                            echo 'bg-blue-400';
                                        elseif ($row['status'] == 'scheduled')
                                            echo 'bg-yellow-400';
                                        else
                                            echo 'bg-gray-400';
                                        ?>"></span>
                                        <?php echo __('status_' . $row['status']); ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-500 text-sm font-medium">
                                    <?php echo date('M d, H:i', strtotime($row['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php if ($stmt->rowCount() == 0): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-800/50 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                </path>
                            </svg>
                        </div>
                        <p class="text-gray-500 font-medium"><?php echo __('no_campaigns_found'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="glass-card rounded-[2.5rem] p-8 overflow-hidden relative z-10 border border-white/10 animate-fade-in"
            style="animation-delay: 700ms;">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-black text-white mb-1 tracking-tight"><?php echo __('newest_users'); ?>
                    </h2>
                    <p class="text-sm text-gray-500"><?php echo __('recently_registered_members'); ?></p>
                </div>
                <div
                    class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500/20 to-blue-600/5 border border-white/10 flex items-center justify-center text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-4 pl-2 text-xs font-bold uppercase tracking-wider"><?php echo __('user'); ?>
                            </th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('email'); ?></th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('joined'); ?></th>
                            <th class="pb-4 text-xs font-bold uppercase tracking-wider"><?php echo __('actions'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <?php
                        $stmtUsers = $pdo->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5");
                        while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-white/5 transition-all duration-300 group">
                                <td class="py-4 pl-2 font-semibold text-gray-300 group-hover:text-white transition-colors">
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </td>
                                <td class="py-4 text-gray-400 text-sm font-medium">
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </td>
                                <td class="py-4 text-gray-500 text-sm font-medium">
                                    <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="py-4">
                                    <a href="users.php?edit=<?php echo $u['id']; ?>"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 hover:text-indigo-300 text-sm font-bold border border-indigo-500/20 hover:border-indigo-500/30 transition-all duration-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                            </path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <?php echo __('manage'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>