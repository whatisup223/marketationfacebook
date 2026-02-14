<?php
// Ensure this file is included within a page that has started a session and has translations
?>
<?php
// Read cookie for server-side state (defaults to false/open)
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
$initial_width_class = $sidebar_collapsed ? 'w-20' : 'w-64';
?>
<aside id="app-sidebar" x-data="{ 
        sidebarCollapsed: <?php echo $sidebar_collapsed ? 'true' : 'false'; ?>,
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            // Set cookie for server-side persistence (30 days)
            document.cookie = 'sidebar_collapsed=' + this.sidebarCollapsed + '; path=/; max-age=2592000; SameSite=Lax';
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
            
            if(this.sidebarCollapsed) document.documentElement.classList.add('sidebar-closed');
            else document.documentElement.classList.remove('sidebar-closed');
            
            window.dispatchEvent(new CustomEvent('sidebar-toggled', { detail: this.sidebarCollapsed }));
        }
    }" :class="sidebarCollapsed ? 'w-20' : 'w-64'"
    class="<?php echo $initial_width_class; ?> hidden lg:flex flex-col bg-gray-900/50 backdrop-blur-xl border-r border-gray-800 ml-4 rounded-3xl mb-4 self-start sticky top-24 max-h-[calc(100vh-8rem)] transition-all duration-500 ease-in-out group z-40overflow-hidden">

    <!-- Header / Toggle Area -->
    <div class="h-16 flex items-center px-4 border-b border-white/5 shrink-0 relative overflow-hidden">
        <!-- Logo Area (Hidden on Collapse) -->
        <a href="../index.php"
            class="flex items-center gap-3 transition-all duration-300 hide-on-collapse flex-1 min-w-0">
            <?php if (getSetting('site_logo')): ?>
                <img src="../uploads/<?php echo getSetting('site_logo'); ?>" class="h-8 w-auto shrink-0" alt="Logo">
            <?php else: ?>
                <div
                    class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold text-xs shrink-0 shadow-lg">
                    <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <span class="text-lg font-black tracking-tighter text-white whitespace-nowrap">
                <?php echo __('site_name'); ?>
            </span>
        </a>

        <!-- Toggle Button Container -->
        <div class="flex items-center justify-center transition-all duration-300 shrink-0"
            :class="sidebarCollapsed ? 'flex-1' : ''">
            <button @click="toggleSidebar()"
                class="p-2 rounded-xl bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:bg-white/10 transition-all z-50">
                <svg class="w-4 h-4 transition-transform duration-500" :class="sidebarCollapsed ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Navigation Content -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1.5">
        <!-- User Profile -->
        <?php
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_user):
            $avatar_path = !empty($current_user['avatar']) ? '../' . $current_user['avatar'] : '';
            ?>
            <a href="profile.php"
                class="flex items-center p-2 mb-6 rounded-2xl bg-white/5 border border-white/5 hover:bg-white/10 transition-all group/profile overflow-hidden">
                <div class="shrink-0 flex items-center justify-center w-10 h-10 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-10'">
                    <div class="w-10 h-10 relative">
                        <?php if ($avatar_path && file_exists($avatar_path)): ?>
                            <img src="<?php echo $avatar_path; ?>"
                                class="w-full h-full rounded-xl object-cover border border-indigo-500/30">
                        <?php else: ?>
                            <div
                                class="w-full h-full rounded-xl bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                                <span
                                    class="text-indigo-400 font-bold text-xs"><?php echo mb_substr($current_user['name'], 0, 1, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Profile Text (Hidden on Collapse) -->
                <div class="ms-3 flex-1 min-w-0 transition-all duration-300 hide-on-collapse">
                    <p class="text-[13px] font-bold text-white truncate">
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </p>
                    <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($current_user['email']); ?>
                    </p>
                </div>
            </a>
        <?php endif; ?>

        <?php
        $fb_pages = ['fb_accounts.php', 'page_inbox.php', 'create_campaign.php', 'page_auto_reply.php', 'page_moderator.php', 'fb_scheduler.php', 'page_messenger_bot.php'];
        $wa_pages = ['wa_accounts.php', 'wa_bulk_send.php', 'wa_settings.php'];
        $ig_pages = ['ig_accounts.php', 'ig_auto_reply.php', 'ig_messenger_bot.php', 'ig_moderator.php'];
        $current_page = basename($_SERVER['PHP_SELF']);
        $is_fb_open = in_array($current_page, $fb_pages) ? 'true' : 'false';
        $is_wa_open = in_array($current_page, $wa_pages) ? 'true' : 'false';
        $is_ig_open = in_array($current_page, $ig_pages) ? 'true' : 'false';
        ?>

        <nav class="space-y-1"
            x-data="{ fbOpen: <?php echo $is_fb_open; ?>, waOpen: <?php echo $is_wa_open; ?>, igOpen: <?php echo $is_ig_open; ?> }">
            <!-- Dashboard -->
            <a href="dashboard.php"
                class="flex items-center px-3 py-2.5 rounded-xl transition-all <?php echo $current_page == 'dashboard.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span
                    class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('overview'); ?></span>
            </a>

            <!-- Facebook Menu -->
            <div>
                <button @click="sidebarCollapsed ? (toggleSidebar(), fbOpen = true) : fbOpen = !fbOpen"
                    class="w-full flex items-center px-3 py-2.5 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all group">
                    <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                        :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                        <svg class="w-5 h-5 group-hover:text-indigo-400 transition-colors" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </div>
                    <span
                        class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('facebook'); ?></span>
                    <svg class="w-4 h-4 ms-auto transition-transform hide-on-collapse"
                        :class="fbOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div :class="{ 'is-active': fbOpen && !sidebarCollapsed }"
                    class="navigation-submenu pl-12 rtl:pl-0 rtl:pr-12 space-y-1 <?php echo ($is_fb_open == 'true' && !$sidebar_collapsed) ? 'is-active' : ''; ?>">
                    <a href="fb_accounts.php" title="<?php echo __('fb_accounts'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'fb_accounts.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('fb_accounts'); ?></a>
                    <a href="page_inbox.php" title="<?php echo __('manage_messages'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'page_inbox.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('manage_messages'); ?></a>
                    <a href="create_campaign.php" title="<?php echo __('setup_campaign'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'create_campaign.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('setup_campaign'); ?></a>
                    <a href="page_auto_reply.php" title="<?php echo __('auto_reply'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'page_auto_reply.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_reply'); ?></a>
                    <a href="page_messenger_bot.php" title="<?php echo __('auto_reply_messages'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'page_messenger_bot.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_reply_messages'); ?></a>
                    <a href="page_moderator.php" title="<?php echo __('auto_moderator'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'page_moderator.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_moderator'); ?></a>
                </div>
            </div>

            <!-- Instagram Menu -->
            <div>
                <button @click="sidebarCollapsed ? (toggleSidebar(), igOpen = true) : igOpen = !igOpen"
                    class="w-full flex items-center px-3 py-2.5 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all group">
                    <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                        :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                        <svg class="w-5 h-5 group-hover:text-pink-500 transition-colors" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg>
                    </div>
                    <span
                        class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('instagram'); ?></span>
                    <svg class="w-4 h-4 ms-auto transition-transform hide-on-collapse"
                        :class="igOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div :class="{ 'is-active': igOpen && !sidebarCollapsed }"
                    class="navigation-submenu pl-12 rtl:pl-0 rtl:pr-12 space-y-1 <?php echo ($is_ig_open == 'true' && !$sidebar_collapsed) ? 'is-active' : ''; ?>">
                    <a href="ig_accounts.php" title="<?php echo __('ig_accounts'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'ig_accounts.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('ig_accounts'); ?></a>
                    <a href="ig_auto_reply.php" title="<?php echo __('auto_reply'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'ig_auto_reply.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_reply'); ?></a>
                    <a href="ig_messenger_bot.php" title="<?php echo __('auto_reply_messages'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'ig_messenger_bot.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_reply_messages'); ?></a>
                    <a href="ig_moderator.php" title="<?php echo __('auto_moderator'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'ig_moderator.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('auto_moderator'); ?></a>
                </div>

            <!-- WhatsApp Menu -->
            <div>
                <button @click="sidebarCollapsed ? (toggleSidebar(), waOpen = true) : waOpen = !waOpen"
                    class="w-full flex items-center px-3 py-2.5 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all group">
                    <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                        :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                        <svg class="w-5 h-5 group-hover:text-green-400 transition-colors" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                        </svg>
                    </div>
                    <span
                        class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('whatsapp'); ?></span>
                    <svg class="w-4 h-4 ms-auto transition-transform hide-on-collapse"
                        :class="waOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div :class="{ 'is-active': waOpen && !sidebarCollapsed }"
                    class="navigation-submenu pl-12 rtl:pl-0 rtl:pr-12 space-y-1 <?php echo ($is_wa_open == 'true' && !$sidebar_collapsed) ? 'is-active' : ''; ?>">
                    <a href="wa_accounts.php" title="<?php echo __('wa_accounts'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'wa_accounts.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('wa_accounts'); ?></a>
                    <a href="wa_bulk_send.php" title="<?php echo __('wa_bulk_send'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'wa_bulk_send.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('wa_bulk_send'); ?></a>
                    <a href="wa_settings.php" title="<?php echo __('wa_settings'); ?>"
                        class="block py-1.5 text-xs <?php echo $current_page == 'wa_settings.php' ? 'text-indigo-400 font-bold' : 'text-gray-500 hover:text-gray-300'; ?>"><?php echo __('wa_settings'); ?></a>
                </div>
            </div>

            <!-- Reports -->
            <a href="campaign_reports.php"
                class="flex items-center px-3 py-2.5 rounded-xl transition-all <?php echo $current_page == 'campaign_reports.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span
                    class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('campaign_reports'); ?></span>
            </a>

            <!-- Notifications -->
            <a href="notifications.php"
                class="flex items-center px-3 py-2.5 rounded-xl transition-all <?php echo $current_page == 'notifications.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                    <div class="relative">
                        <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if (getUnreadCount($_SESSION['user_id'] ?? 0) > 0): ?>
                            <span
                                class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full border border-gray-900"></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span
                    class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('notifications'); ?></span>
            </a>

            <!-- Support -->
            <a href="support.php"
                class="flex items-center px-3 py-2.5 rounded-xl transition-all <?php echo $current_page == 'support.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <span
                    class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('support_tickets'); ?></span>
            </a>

            <!-- Settings -->
            <a href="settings.php"
                class="flex items-center px-3 py-2.5 rounded-xl transition-all <?php echo $current_page == 'settings.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span
                    class="ms-3 text-sm font-medium hide-on-collapse transition-all duration-300"><?php echo __('settings'); ?></span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Footer -->
    <div class="p-3 border-t border-white/5">
        <a href="../logout.php"
            class="flex items-center px-3 py-2.5 text-red-500/70 hover:bg-red-500/10 hover:text-red-400 rounded-xl transition-all group overflow-hidden">
            <div class="w-6 h-6 flex items-center justify-center shrink-0 transition-all duration-300"
                :class="sidebarCollapsed ? 'w-full' : 'w-6'">
                <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </div>
            <span
                class="ms-3 text-sm font-bold hide-on-collapse transition-all duration-300"><?php echo __('logout'); ?></span>
        </a>
    </div>
</aside>