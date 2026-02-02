<?php
// Ensure this file is included within a page that has started a session and has translations
?>
<aside
    class="w-64 hidden lg:block bg-gray-900/50 backdrop-blur-xl border-r border-gray-800 ml-4 rounded-3xl mb-4 p-6 self-start sticky top-24 max-h-[calc(100vh-8rem)] overflow-y-auto custom-scrollbar">
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
                    <?php
                    $user_avatar = $current_user['avatar'];
                    $avatar_path = !empty($user_avatar) ? $prefix . $user_avatar : null;
                    ?>
                    <div class="relative w-10 h-10 group-hover:scale-105 transition-transform">
                        <?php if ($avatar_path): ?>
                            <img src="<?php echo $avatar_path; ?>"
                                onerror="this.onerror=null; this.src='data:image/svg+xml;base64,<?php echo base64_encode('<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'); ?>'; this.className='w-full h-full rounded-xl bg-indigo-500/20 p-2 text-indigo-400 border border-indigo-500/30'"
                                class="w-full h-full rounded-xl object-cover border border-indigo-500/30">
                        <?php else: ?>
                            <div
                                class="w-full h-full rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                                <span
                                    class="text-indigo-400 font-bold"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
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
            <?php echo __('user_panel'); ?>
        </h3>
    </div>
    <?php
    $fb_pages = ['fb_accounts.php', 'page_inbox.php', 'create_campaign.php', 'campaign_reports.php', 'page_auto_reply.php', 'page_moderator.php', 'fb_scheduler.php', 'page_messenger_bot.php'];
    $wa_pages = ['wa_accounts.php', 'wa_bulk_send.php', 'wa_settings.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    $is_fb_open = in_array($current_page, $fb_pages) ? 'true' : 'false';
    $is_wa_open = in_array($current_page, $wa_pages) ? 'true' : 'false';
    ?>
    <nav class="space-y-2" x-data="{ fbOpen: <?php echo $is_fb_open; ?>, waOpen: <?php echo $is_wa_open; ?> }">
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
                <a href="page_auto_reply.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'page_auto_reply.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('auto_reply'); ?>
                </a>
                <a href="page_messenger_bot.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'page_messenger_bot.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('auto_reply_messages'); ?>
                </a>
                <a href="fb_scheduler.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'fb_scheduler.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('post_scheduler'); ?>
                </a>
                <a href="page_moderator.php"
                    class="block px-4 py-2.5 <?php echo basename($_SERVER['PHP_SELF']) == 'page_moderator.php' ? 'text-indigo-400 bg-indigo-500/5' : 'text-gray-500 hover:text-gray-300'; ?> rounded-lg text-sm font-medium transition-colors mb-1">
                    <?php echo __('auto_moderator'); ?>
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

        <!-- Campaign Reports -->
        <a href="campaign_reports.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'campaign_reports.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex items-center gap-3">
            <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span><?php echo __('campaign_reports'); ?></span>
        </a>

        <!-- Notifications -->
        <a href="notifications.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center group">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span><?php echo __('notifications'); ?></span>
            </div>
            <?php
            $user_unread_count = 0;
            if (isset($_SESSION['user_id'])) {
                $user_unread_count = getUnreadCount($_SESSION['user_id']);
            }
            if ($user_unread_count > 0):
                ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $user_unread_count > 9 ? '9+' : $user_unread_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Support Tickets -->
        <a href="support.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' || basename($_SERVER['PHP_SELF']) == 'create_ticket.php' || basename($_SERVER['PHP_SELF']) == 'view_ticket.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex justify-between items-center group">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span><?php echo __('support_tickets'); ?></span>
            </div>
            <?php
            $user_ticket_unread = 0;
            if (isset($_SESSION['user_id'])) {
                $user_ticket_unread = getUserTicketUnreadCount($_SESSION['user_id']);
            }
            if ($user_ticket_unread > 0):
                ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $user_ticket_unread > 9 ? '9+' : $user_ticket_unread; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Profile -->
        <a href="profile.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex items-center gap-3 group">
            <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span><?php echo __('profile'); ?></span>
        </a>

        <!-- Settings -->
        <a href="settings.php"
            class="block px-4 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-indigo-600/20 text-indigo-300 border-indigo-500/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white'; ?> rounded-xl font-medium border border-transparent transition-colors flex items-center gap-3 group">
            <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span><?php echo __('settings'); ?></span>
        </a>

        <div class="pt-4 mt-4 border-t border-gray-800">
            <a href="../logout.php"
                class="block px-4 py-3 text-red-400 hover:bg-red-500/10 hover:text-red-300 rounded-xl transition-colors flex items-center gap-3 group font-bold">
                <svg class="w-5 h-5 opacity-70 group-hover:text-red-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span><?php echo __('logout'); ?></span>
            </a>
        </div>
    </nav>
</aside>