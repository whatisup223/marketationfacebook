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
            <?php echo __('user_panel'); ?>
        </h3>
    </div>
    <nav class="space-y-2">
        <a href="dashboard.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('overview'); ?>
        </a>

        <!-- Page Management -->
        <a href="fb_accounts.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('fb_accounts'); ?>
        </a>

        <!-- Message Management -->
        <a href="page_inbox.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'page_inbox.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('manage_messages'); ?>
        </a>

        <!-- Create Campaign -->
        <a href="create_campaign.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'create_campaign.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('setup_campaign'); ?>
        </a>

        <!-- Campaign Reports -->
        <a href="campaign_reports.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'campaign_reports.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('campaign_reports'); ?>
        </a>




        <a href="profile.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('profile'); ?>
        </a>
        <a href="notifications.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center">
            <span><?php echo __('notifications'); ?></span>
            <?php
            $user_unread_count = 0;
            if (isset($_SESSION['user_id'])) {
                $user_unread_count = getUnreadCount($_SESSION['user_id']);
            }
            if ($user_unread_count > 0):
                ?>
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $user_unread_count > 9 ? '9+' : $user_unread_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="support.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'create_ticket.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center">
            <span><?php echo __('support_tickets'); ?></span>
            <?php
            $user_ticket_unread = 0;
            if (isset($_SESSION['user_id'])) {
                $user_ticket_unread = getUserTicketUnreadCount($_SESSION['user_id']);
            }
            if ($user_ticket_unread > 0):
                ?>
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $user_ticket_unread > 9 ? '9+' : $user_ticket_unread; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="../logout.php"
            class="block px-4 py-3 text-red-400 hover:bg-red-500/10 hover:text-red-300 rounded-xl transition-colors">
            <?php echo __('logout'); ?>
        </a>
    </nav>
</aside>