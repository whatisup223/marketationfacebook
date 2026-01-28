<?php
// Ensure this file is included within a page that has started a session and has translations
?>
<aside
    class="w-64 hidden md:block bg-gray-900/50 backdrop-blur-xl border-r border-gray-800 ml-4 rounded-3xl mb-4 p-6 self-start sticky top-24">
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
            <div
                class="flex items-center space-x-3 rtl:space-x-reverse mb-8 p-3 bg-white/5 rounded-2xl border border-white/10">
                <div class="flex-shrink-0">
                    <?php if ($current_user['avatar']): ?>
                        <img src="../<?php echo $current_user['avatar']; ?>"
                            class="w-10 h-10 rounded-xl object-cover border border-indigo-500/30">
                    <?php else: ?>
                        <div
                            class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                            <span
                                class="text-indigo-400 font-bold"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($current_user['name']); ?>
                    </p>
                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($current_user['email']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-800 pb-2">
            <?php echo __('admin_panel'); ?>
        </h3>
    </div>
    <nav class="space-y-2">
        <a href="dashboard.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <span><?php echo __('overview'); ?></span>
        </a>

        <!-- FB Accounts -->
        <a href="fb_accounts.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('fb_accounts'); ?>
        </a>


        <a href="users.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('users'); ?>
        </a>
        <a href="profile.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('profile'); ?>
        </a>
        <a href="settings.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('settings'); ?>
        </a>
        <a href="notifications.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center">
            <span><?php echo __('notifications'); ?></span>
            <?php
            $admin_unread_count = 0;
            if (isset($_SESSION['user_id'])) {
                $admin_unread_count = getUnreadCount($_SESSION['user_id']);
            }
            if ($admin_unread_count > 0):
                ?>
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $admin_unread_count > 9 ? '9+' : $admin_unread_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="backup.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('backup_restore'); ?>
        </a>
        <a href="system_update.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'system_update.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                </path>
            </svg>
            <?php echo __('system_update'); ?>
        </a>
        <a href="support.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center">
            <span><?php echo __('support_tickets'); ?></span>
            <?php
            $admin_ticket_unread = getAdminTicketUnreadCount();
            if ($admin_ticket_unread > 0):
                ?>
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $admin_ticket_unread > 9 ? '9+' : $admin_ticket_unread; ?>
                </span>
            <?php endif; ?>
        </a>
        </a>

        <div class="pt-4 mt-4 border-t border-gray-800">
            <a href="../logout.php"
                class="block px-4 py-3 text-red-400 hover:bg-gray-800 hover:text-red-300 rounded-xl font-medium border border-transparent transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span><?php echo __('logout'); ?></span>
            </a>
        </div>
    </nav>
</aside>