<?php
// includes/admin_sidebar.php
?>
<aside x-data="{ 
        sidebarCollapsed: localStorage.getItem('adminSidebarCollapsed') === 'true',
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('adminSidebarCollapsed', this.sidebarCollapsed);
            window.dispatchEvent(new CustomEvent('sidebar-toggled', { detail: this.sidebarCollapsed }));
        }
    }" :class="sidebarCollapsed ? 'w-20' : 'w-64'"
    class="hidden lg:flex flex-col bg-gray-900/50 backdrop-blur-xl border-r border-gray-800 ml-4 rounded-3xl mb-4 self-start sticky top-24 max-h-[calc(100vh-8rem)] transition-all duration-500 ease-in-out group z-40">

    <!-- Header / Toggle Area -->
    <div class="p-4 h-16 flex items-center border-b border-white/5 shrink-0 relative overflow-hidden"
        :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
        <!-- Logo/Name (Visible only when expanded) -->
        <a href="<?php echo $prefix; ?>index.php" class="flex items-center gap-3 transition-all duration-300"
            :class="sidebarCollapsed ? 'invisible opacity-0 w-0' : 'visible opacity-100 w-auto'">
            <?php if (getSetting('site_logo')): ?>
                <img src="<?php echo $prefix; ?>uploads/<?php echo getSetting('site_logo'); ?>"
                    class="h-8 w-auto min-w-[32px]" alt="Logo">
            <?php else: ?>
                <div
                    class="w-8 h-8 rounded-lg bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white font-bold text-xs shadow-lg shrink-0">
                    <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <span class="text-lg font-black tracking-tighter text-white whitespace-nowrap">
                <?php echo __('site_name'); ?>
            </span>
        </a>

        <!-- Toggle Button -->
        <button @click="toggleSidebar()"
            class="p-2 rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-white/10 transition-all z-50 relative shrink-0"
            title="Toggle Sidebar">
            <svg class="w-4 h-4 transition-transform duration-500" :class="sidebarCollapsed ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </div>

    <!-- User Profile Area -->
    <div class="p-3 border-b border-white/5 overflow-hidden">
        <?php
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_user):
            $avatar_path = !empty($current_user['avatar']) ? '../' . $current_user['avatar'] : '';
            ?>
            <a href="profile.php"
                class="flex items-center p-2 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-all group/user overflow-hidden group"
                :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
                <div class="shrink-0">
                    <?php if ($avatar_path && file_exists(__DIR__ . '/../' . $current_user['avatar'])): ?>
                        <img src="<?php echo $avatar_path; ?>"
                            class="w-8 h-8 rounded-xl object-cover border border-indigo-500/30 group-hover/user:scale-105 transition-transform">
                    <?php else: ?>
                        <div
                            class="w-8 h-8 rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30 group-hover/user:scale-105 transition-transform">
                            <span
                                class="text-indigo-400 font-bold text-xs"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'">
                    <p class="text-xs font-bold text-white truncate">
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </p>
                    <p class="text-[10px] text-gray-500 truncate">
                        <?php echo htmlspecialchars($current_user['email']); ?>
                    </p>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Navigation Content -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1.5" x-data="{ 
        accountsOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['fb_accounts.php', 'users.php']) ? 'true' : 'false'; ?>,
        systemOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'backup.php', 'system_update.php']) ? 'true' : 'false'; ?> 
    }">

        <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

        <!-- Overview -->
        <a href="dashboard.php" title="<?php echo __('overview'); ?>"
            class="flex items-center px-3 py-2.5 <?php echo $current_page == 'dashboard.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/20 shadow-lg shadow-indigo-500/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-all group overflow-hidden"
            :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
            <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400 transition-colors shrink-0" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-sm whitespace-nowrap transition-all duration-300"
                :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('overview'); ?></span>
        </a>

        <!-- Accounts Management Dropdown -->
        <div class="relative">
            <button @click="sidebarCollapsed ? toggleSidebar() : accountsOpen = !accountsOpen"
                class="w-full flex items-center px-3 py-2.5 text-gray-400 hover:bg-white/5 hover:text-white rounded-xl font-medium border border-transparent transition-all group"
                :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
                <div class="flex items-center" :class="sidebarCollapsed ? '' : 'gap-3'">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition-colors shrink-0" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="text-sm transition-all duration-300"
                        :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('accounts_management'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-300 shrink-0" x-show="!sidebarCollapsed"
                    :class="accountsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="accountsOpen && !sidebarCollapsed" x-collapse
                class="pl-10 rtl:pl-0 rtl:pr-10 space-y-1 mt-1 transition-all">
                <a href="users.php"
                    class="block py-1.5 text-xs <?php echo $current_page == 'users.php' ? 'text-indigo-400' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('users'); ?></a>
                <a href="fb_accounts.php"
                    class="block py-1.5 text-xs <?php echo $current_page == 'fb_accounts.php' ? 'text-indigo-400' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('fb_accounts'); ?></a>
            </div>
        </div>

        <!-- System Management Dropdown -->
        <div class="relative">
            <button @click="sidebarCollapsed ? toggleSidebar() : systemOpen = !systemOpen"
                class="w-full flex items-center px-3 py-2.5 text-gray-400 hover:bg-white/5 hover:text-white rounded-xl font-medium border border-transparent transition-all group"
                :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
                <div class="flex items-center" :class="sidebarCollapsed ? '' : 'gap-3'">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-emerald-400 transition-colors shrink-0"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-sm transition-all duration-300"
                        :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('system_management'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-300 shrink-0" x-show="!sidebarCollapsed"
                    :class="systemOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="systemOpen && !sidebarCollapsed" x-collapse
                class="pl-10 rtl:pl-0 rtl:pr-10 space-y-1 mt-1 transition-all">
                <a href="settings.php"
                    class="block py-1.5 text-xs <?php echo $current_page == 'settings.php' ? 'text-indigo-400' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('settings'); ?></a>
                <a href="backup.php"
                    class="block py-1.5 text-xs <?php echo $current_page == 'backup.php' ? 'text-indigo-400' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('backup_restore'); ?></a>
                <a href="system_update.php"
                    class="block py-1.5 text-xs <?php echo $current_page == 'system_update.php' ? 'text-indigo-400' : 'text-gray-500 hover:text-gray-300'; ?> flex items-center gap-2">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <?php echo __('system_update'); ?>
                </a>
            </div>
        </div>

        <!-- Notifications -->
        <a href="notifications.php" title="<?php echo __('notifications'); ?>"
            class="flex items-center px-3 py-2.5 <?php echo $current_page == 'notifications.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/20 shadow-lg shadow-indigo-500/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-all group overflow-hidden relative"
            :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
            <div class="flex items-center" :class="sidebarCollapsed ? '' : 'gap-3'">
                <div class="relative shrink-0">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <?php
                    $admin_unread_count = 0;
                    if (isset($_SESSION['user_id'])) {
                        $admin_unread_count = getUnreadCount($_SESSION['user_id']);
                    }
                    if ($admin_unread_count > 0 && $current_page != 'notifications.php'): ?>
                        <div x-show="sidebarCollapsed"
                            class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full border border-gray-900 animate-pulse">
                        </div>
                    <?php endif; ?>
                </div>
                <span class="text-sm whitespace-nowrap transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('notifications'); ?></span>
            </div>
            <?php if ($admin_unread_count > 0): ?>
                <span x-show="!sidebarCollapsed"
                    class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm transition-all duration-300">
                    <?php echo $admin_unread_count > 9 ? '9+' : $admin_unread_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Support Tickets -->
        <a href="support.php" title="<?php echo __('support_tickets'); ?>"
            class="flex items-center px-3 py-2.5 <?php echo $current_page == 'support.php' || $current_page == 'view_ticket.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/20 shadow-lg shadow-indigo-500/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-all group overflow-hidden relative"
            :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
            <div class="flex items-center" :class="sidebarCollapsed ? '' : 'gap-3'">
                <div class="relative shrink-0">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-cyan-400 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <?php
                    $admin_ticket_unread = getAdminTicketUnreadCount();
                    if ($admin_ticket_unread > 0): ?>
                        <div x-show="sidebarCollapsed"
                            class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full border border-gray-900 animate-pulse">
                        </div>
                    <?php endif; ?>
                </div>
                <span class="text-sm whitespace-nowrap transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('support_tickets'); ?></span>
            </div>
            <?php if ($admin_ticket_unread > 0): ?>
                <span x-show="!sidebarCollapsed"
                    class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm transition-all duration-300">
                    <?php echo $admin_ticket_unread > 9 ? '9+' : $admin_ticket_unread; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Profile -->
        <a href="profile.php" title="<?php echo __('profile'); ?>"
            class="flex items-center px-3 py-2.5 <?php echo $current_page == 'profile.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/20 shadow-lg shadow-indigo-500/10' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-all group overflow-hidden"
            :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
            <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400 transition-colors shrink-0" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-sm whitespace-nowrap transition-all duration-300"
                :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('profile'); ?></span>
        </a>

        <!-- Logout Area -->
        <div class="pt-4 mt-4 border-t border-gray-800">
            <a href="<?php echo $prefix; ?>logout.php" title="<?php echo __('logout'); ?>"
                class="flex items-center px-3 py-2.5 text-red-500/70 hover:bg-red-500/10 hover:text-red-400 rounded-xl font-medium transition-all group overflow-hidden"
                :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span class="text-sm whitespace-nowrap transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-0 opacity-0 absolute' : 'w-auto opacity-100'"><?php echo __('logout'); ?></span>
            </a>
        </div>
    </div>
</aside>