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
    <nav class="space-y-2" x-data="{ 
        fbOpen: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['fb_accounts.php', 'page_inbox.php', 'create_campaign.php', 'campaign_reports.php']) ? 'true' : 'false'; ?>,
        waOpen: false 
    }">
        <a href="dashboard.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors">
            <?php echo __('overview'); ?>
        </a>

        <!-- Facebook Dropdown -->
        <div class="space-y-1">
            <button @click="fbOpen = !fbOpen"
                class="w-full flex items-center justify-between px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium border border-transparent transition-colors group">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-400 transition-colors" fill="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                    <span><?php echo __('facebook'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-200" :class="fbOpen ? 'rotate-180' : ''" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="fbOpen" x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                class="pl-4 rtl:pl-0 rtl:pr-4 space-y-1">
                <a href="fb_accounts.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'fb_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('fb_accounts'); ?>
                </a>
                <a href="page_inbox.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'page_inbox.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('manage_messages'); ?>
                </a>
                <a href="create_campaign.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'create_campaign.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('setup_campaign'); ?>
                </a>
            </div>
        </div>

        <!-- WhatsApp Dropdown -->
        <div class="space-y-1">
            <button @click="waOpen = !waOpen"
                class="w-full flex items-center justify-between px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium border border-transparent transition-colors group">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-500 group-hover:text-green-400 transition-colors" fill="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                    </svg>
                    <span><?php echo __('whatsapp'); ?></span>
                </div>
                <svg class="w-4 h-4 transition-transform duration-200" :class="waOpen ? 'rotate-180' : ''" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="waOpen" x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                class="pl-4 rtl:pl-0 rtl:pr-4 space-y-1">
                <a href="wa_accounts.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'wa_accounts.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('wa_accounts'); ?>
                </a>
                <a href="wa_bulk_send.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'wa_bulk_send.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('wa_bulk_send'); ?>
                </a>
                <a href="wa_settings.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'wa_settings.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('wa_settings'); ?>
                </a>
            </div>
        </div>

        <!-- Campaign Reports moved outside -->
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