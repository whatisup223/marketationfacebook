<?php
// includes/admin_sidebar.php
?>
<aside
    class="w-64 hidden md:block bg-gray-900/50 backdrop-blur-xl border-r border-gray-800 ml-4 rounded-3xl mb-4 p-6 self-start sticky top-24 max-h-[calc(100vh-8rem)] overflow-y-auto custom-scrollbar">
    <div class="mb-8 px-2">
        <a href="../index.php" class="flex items-center space-x-2 rtl:space-x-reverse mb-6">
            <?php if (getSetting('site_logo')): ?>
                <img src="../uploads/<?php echo getSetting('site_logo'); ?>" class="h-8 w-auto" alt="Logo">
            <?php else: ?>
                <div
                    class="w-10 h-10 rounded-lg bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-lg">
                    <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <span class="text-xl font-bold tracking-tight text-white">
                <?php echo __('site_name'); ?>
            </span>
        </a>

        <?php if (isset($current_user)): ?>
            <a href="profile.php"
                class="flex items-center space-x-3 rtl:space-x-reverse mb-8 p-3 bg-white/5 rounded-2xl border border-white/10 hover:bg-white/10 transition-all group">
                <div class="flex-shrink-0">
                    <?php if ($current_user['avatar']): ?>
                        <img src="../<?php echo $current_user['avatar']; ?>"
                            class="w-10 h-10 rounded-xl object-cover border border-indigo-500/30 group-hover:scale-105 transition-transform">
                    <?php else: ?>
                        <div
                            class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30 group-hover:scale-105 transition-transform">
                            <span
                                class="text-indigo-400 font-bold"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-white truncate group-hover:text-indigo-400 transition-colors">
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </p>
                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($current_user['email']); ?></p>
                </div>
            </a>
        <?php endif; ?>

        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-800 pb-2">
            <?php echo __('admin_panel'); ?>
        </h3>
    </div>

    <nav class="space-y-2" x-data="{ 
        accountsOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['fb_accounts.php', 'users.php']) ? 'true' : 'false'; ?>,
        systemOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'backup.php', 'system_update.php']) ? 'true' : 'false'; ?> 
    }">
        <!-- Overview -->
        <a href="dashboard.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <span><?php echo __('overview'); ?></span>
        </a>

        <!-- Accounts Group -->
        <div class="space-y-1">
            <button @click="accountsOpen = !accountsOpen"
                class="w-full flex items-center justify-between px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium border border-transparent transition-colors group">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span><?php echo __('accounts_management'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-200" :class="accountsOpen ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="accountsOpen" x-transition class="pl-4 rtl:pl-0 rtl:pr-4 space-y-1">
                <a href="users.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors">
                    <?php echo __('users'); ?>
                </a>
                <a href="fb_accounts.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors">
                    <?php echo __('fb_accounts'); ?>
                </a>
            </div>
        </div>

        <!-- System Group -->
        <div class="space-y-1">
            <button @click="systemOpen = !systemOpen"
                class="w-full flex items-center justify-between px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium border border-transparent transition-colors group">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span><?php echo __('system_management'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-200" :class="systemOpen ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="systemOpen" x-transition class="pl-4 rtl:pl-0 rtl:pr-4 space-y-1">
                <a href="settings.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors">
                    <?php echo __('settings'); ?>
                </a>
                <a href="backup.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors">
                    <?php echo __('backup_restore'); ?>
                </a>
                <a href="system_update.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'system_update.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <?php echo __('system_update'); ?>
                </a>
            </div>
        </div>

        <!-- Single Links -->
        <a href="notifications.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center group">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span><?php echo __('notifications'); ?></span>
            </div>
            <?php
            $admin_unread_count = 0;
            if (isset($_SESSION['user_id'])) {
                $admin_unread_count = getUnreadCount($_SESSION['user_id']);
            }
            if ($admin_unread_count > 0):
                ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $admin_unread_count > 9 ? '9+' : $admin_unread_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="support.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center group">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span><?php echo __('support_tickets'); ?></span>
            </div>
            <?php
            $admin_ticket_unread = getAdminTicketUnreadCount();
            if ($admin_ticket_unread > 0):
                ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $admin_ticket_unread > 9 ? '9+' : $admin_ticket_unread; ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="profile.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors group">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span><?php echo __('profile'); ?></span>
            </div>
        </a>

        <div class="pt-4 mt-4 border-t border-gray-800">
            <a href="../logout.php"
                class="block px-4 py-3 text-red-400 hover:bg-red-500/10 hover:text-red-300 rounded-xl font-medium transition-colors flex items-center gap-3 group">
                <svg class="w-5 h-5 text-red-500/50 group-hover:text-red-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span><?php echo __('logout'); ?></span>
            </a>
        </div>
    </nav>
</aside>