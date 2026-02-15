<?php
include '../includes/header.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
?>

<div class="flex h-screen overflow-hidden bg-gray-900 font-sans" x-data="smartInbox()">
    <!-- Sidebar -->
    <?php include '../includes/user_sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/80 z-20 md:hidden"></div>

    <!-- Left Sidebar (Conversations) -->
    <div x-show="sidebarOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-30 w-72 md:w-80 lg:static flex-shrink-0 border-r border-white/5 bg-gray-900/95 backdrop-blur-xl flex flex-col h-full shadow-2xl lg:shadow-none">
        <!-- Header -->
        <div class="p-4 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white tracking-wide"><?php echo __('smart_inbox'); ?></h2>
            <div class="flex gap-2">
                <!-- Sync Button -->
                <!-- Sync Button -->
                <button @click="openSyncModal()"
                    class="p-2 text-gray-400 hover:text-indigo-400 transition-colors relative"
                    :title="'<?php echo __('sync_conversations'); ?>'">
                    <span x-show="syncing" class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-5 h-5 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </span>
                    <span x-show="!syncing">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </span>
                </button>

                <a href="ai_advisor_settings.php" class="p-2 text-gray-400 hover:text-indigo-400 transition-colors"
                    title="<?php echo __('settings'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Tabs & Search -->
        <div class="p-4 space-y-3">
            <!-- Platform Tabs -->
            <div class="flex bg-gray-800 p-1 rounded-lg">
                <button @click="activeTab = 'all'"
                    :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-1 text-xs font-bold rounded-md transition-all">
                    <?php echo __('all'); ?>
                </button>
                <button @click="activeTab = 'facebook'"
                    :class="activeTab === 'facebook' ? 'bg-blue-600 text-white shadow' : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-1 text-xs font-bold rounded-md transition-all">
                    FB
                </button>
                <button @click="activeTab = 'instagram'"
                    :class="activeTab === 'instagram' ? 'bg-pink-600 text-white shadow' : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-1 text-xs font-bold rounded-md transition-all">
                    IG
                </button>
            </div>

            <input type="text" x-model="searchQuery" placeholder="<?php echo __('search_conversations'); ?>"
                class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 transition-colors">
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1">
            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="selectConversation(conv)"
                    :class="selectedConv && selectedConv.id === conv.id ? 'bg-white/10 border-indigo-500/50' : 'hover:bg-white/5 border-transparent'"
                    class="p-3 rounded-xl cursor-pointer border transition-all group relative">

                    <div class="flex justify-between items-start mb-1">
                        <div class="flex items-center gap-2">
                            <!-- Platform Icon -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0 shadow-lg"
                                :class="conv.platform === 'facebook' ? 'bg-blue-600' : 'bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-500'">
                                <template x-if="conv.platform === 'facebook'">
                                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                        <path
                                            d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036c-2.148 0-2.797 1.603-2.797 4.16v1.972h3.618l-.291 3.667h-3.327v7.98h-5.017z" />
                                    </svg>
                                </template>
                                <template x-if="conv.platform !== 'facebook'">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                    </svg>
                                </template>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-200 leading-tight"
                                    x-text="conv.client_name || 'Unknown User'">
                                </h4>
                                <span class="text-[10px] text-gray-500"
                                    x-text="formatDate(conv.last_message_time)"></span>
                            </div>
                        </div>

                        <!-- Sentiment Dot -->
                        <div class="w-3 h-3 rounded-full shadow-lg border border-black/50" :class="{
                                'bg-green-500': conv.ai_sentiment === 'positive',
                                'bg-gray-500': (conv.ai_sentiment === 'neutral' || !conv.ai_sentiment),
                                'bg-yellow-500': conv.ai_sentiment === 'negative',
                                'bg-red-600 animate-pulse': conv.ai_sentiment === 'angry'
                            }" :title="getSentimentLabel(conv.ai_sentiment)">
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 line-clamp-1 mb-2" x-text="conv.last_message_text"></p>

                    <!-- Intent Badge -->
                    <template x-if="conv.ai_intent && conv.ai_intent !== 'General'">
                        <span
                            class="inline-block px-2 py-0.5 rounded-md bg-indigo-500/10 text-indigo-300 text-[10px] font-medium border border-indigo-500/20 max-w-[150px] truncate"
                            :title="conv.ai_intent" x-text="conv.ai_intent"></span>
                    </template>
                </div>
            </template>

            <div x-show="conversations.length === 0" class="text-center text-gray-500 text-sm mt-10">
                <?php echo __('no_active_conversations'); ?>
                <div class="mt-2 text-xs text-gray-600">
                    <?php echo __('sync_from_linked_fb'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Center Chat Area -->
    <div class="flex-1 flex flex-col bg-gray-900 relative">
        <template x-if="!selectedConv">
            <div class="flex-1 flex flex-col items-center justify-center text-gray-500">
                <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                    </path>
                </svg>
                <p><?php echo __('select_conversation'); ?></p>
            </div>
        </template>

        <template x-if="selectedConv">
            <div class="flex flex-col h-full overflow-hidden">
                <!-- Chat Header -->
                <div class="h-16 shrink-0 border-b border-white/5 bg-gray-900/95 backdrop-blur-xl flex items-center justify-between px-3 md:px-6 z-10 gap-2">
                    <div class="flex items-center gap-2 md:gap-3 min-w-0">
                        <!-- Sidebar Toggle -->
                        <button @click="sidebarOpen = !sidebarOpen"
                            class="p-2 -ml-2 text-gray-400 hover:text-white transition-colors shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>

                        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center text-white font-bold shrink-0 text-xs md:text-base pointer-events-none"
                            :class="selectedConv?.platform === 'facebook' ? 'bg-blue-600' : 'bg-pink-600'">
                            <span x-text="selectedConv?.client_name ? selectedConv.client_name.charAt(0) : '?'"></span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-white text-sm md:text-lg truncate"
                                x-text="selectedConv?.client_name || '<?php echo __('unknown'); ?>'"></h3>
                            <div class="hidden sm:flex items-center gap-2 opacity-60">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                <span class="text-[10px] md:text-xs text-gray-400 capitalize"
                                    x-text="selectedConv?.platform || ''"></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- AI Analysis Button -->
                        <button @click="analyzing = true; analyzeThread()"
                            class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded-lg border border-indigo-500/30 transition-all text-[10px] md:text-sm font-bold shrink-0">
                            <svg class="w-3 h-3 md:w-4 md:h-4" :class="analyzing ? 'animate-spin' : ''" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="hidden xs:inline"
                                x-text="analyzing ? '<?php echo __('thinking'); ?>' : '<?php echo __('analyze_with_ai'); ?>'"></span>
                            <span class="xs:hidden" x-text="analyzing ? '...' : 'AI'"></span>
                        </button>

                        <!-- Advisor Toggle Button (!) -->
                        <button @click="showRightSidebar = !showRightSidebar" class="p-2 rounded-lg transition-all"
                            :class="showRightSidebar ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-gray-800 text-gray-400 hover:text-white'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar" id="messages-container">
                    <template x-for="msg in messages" :key="msg.id">
                        <div class="flex w-full" :class="msg.sender === 'page' ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[70%] rounded-2xl p-4 text-sm relative group"
                                :class="msg.sender === 'page' ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-gray-800 text-gray-200 rounded-tl-none border border-white/5'">
                                <p x-text="msg.message_text" class="whitespace-pre-wrap leading-relaxed"></p>
                                <span class="text-[10px] opacity-50 mt-1 block"
                                    :class="msg.sender === 'page' ? 'text-indigo-200 text-right' : 'text-gray-500'"
                                    x-text="formatTime(msg.created_at)"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Fixed Bottom Section (Suggestions + Input) -->
                <div class="shrink-0 flex flex-col bg-gray-900">
                    <!-- Smart Suggestions Bar -->
                    <div class="px-6 py-2 border-t border-white/5 w-full overflow-hidden"
                        x-show="suggestions.length > 0">
                        <div class="flex items-center gap-2 overflow-x-auto pb-2 custom-scrollbar max-w-full">
                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider shrink-0 mr-2">
                                <?php echo __('ai_suggestions'); ?>
                            </div>
                            <template x-for="reply in suggestions">
                                <button @click="newMessage = reply"
                                    class="shrink-0 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs hover:bg-indigo-500/20 hover:border-indigo-500/40 transition-all cursor-pointer whitespace-nowrap">
                                    <span x-text="reply"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="p-4 border-t border-white/5">
                        <div
                            class="glass-panel rounded-2xl p-2 flex items-end gap-2 bg-gray-800/50 border border-white/10 focus-within:ring-2 ring-indigo-500/50 transition-all">
                            <textarea x-model="newMessage" placeholder="<?php echo __('type_message'); ?>" rows="1"
                                class="w-full bg-transparent border-none text-white focus:ring-0 resize-none max-h-32 py-3 px-3 custom-scrollbar"
                                @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"></textarea>

                            <button @click="sendMessage()"
                                class="p-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 mb-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Right Sidebar (Advisor Panel) -->
    <div x-show="selectedConv && showRightSidebar" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-40 w-80 lg:static border-l border-white/5 bg-gray-900/95 backdrop-blur-xl flex flex-col shadow-2xl lg:shadow-none overflow-hidden h-full">
        
        <!-- Fixed Advisor Header -->
        <div class="h-16 shrink-0 border-b border-white/5 flex items-center px-6 bg-black/20">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                <?php echo __('ai_advisor_insights'); ?>
            </h3>
        </div>

        <!-- Scrollable Advisor Content -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">
            <!-- Analysis Card -->
            <div
                class="glass-panel p-5 rounded-2xl bg-gradient-to-br from-gray-800/50 to-gray-900/50 border border-white/10 group hover:border-indigo-500/30 transition-all">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold text-gray-400"><?php echo __('current_mood'); ?></span>
                    <span class="px-2 py-1 rounded-md text-xs font-bold uppercase border" :class="{
                            'bg-green-500/10 text-green-400 border-green-500/20': analysis.sentiment === 'positive',
                            'bg-gray-500/10 text-gray-400 border-gray-500/20': analysis.sentiment === 'neutral' || !analysis.sentiment,
                            'bg-red-500/10 text-red-400 border-red-500/20': ['negative', 'angry'].includes(analysis.sentiment)
                        }" x-text="getSentimentLabel(analysis.sentiment)"></span>
                </div>

                <h4 class="text-sm font-bold text-white mb-1"><?php echo __('intent'); ?></h4>
                <p class="text-xs text-gray-400 mb-4"
                    x-text="analysis.intent || '<?php echo __('not_analyzed_yet'); ?>'"></p>

                <h4 class="text-sm font-bold text-white mb-1"><?php echo __('summary'); ?></h4>
                <p class="text-xs text-gray-400 leading-relaxed"
                    x-text="analysis.summary || '<?php echo __('analyze_summary_hint'); ?>'"></p>
            </div>

            <!-- Next Best Action -->
            <div
                class="glass-panel p-5 rounded-2xl bg-gradient-to-br from-indigo-900/20 to-purple-900/20 border border-indigo-500/20 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-indigo-500 blur-3xl opacity-20"></div>
                <h3 class="text-sm font-bold text-indigo-300 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <?php echo __('next_best_action'); ?>
                </h3>
                <p class="text-sm text-gray-200 font-medium leading-relaxed break-words whitespace-pre-wrap"
                    x-text="analysis.next_best_action || '<?php echo __('no_recommendation'); ?>'"></p>
            </div>

            <!-- Customer Info (Mockup for now) -->
            <div class="border-t border-white/10 pt-6">
                <h3 class="text-xs font-bold text-gray-500 uppercase mb-4"><?php echo __('customer_details'); ?></h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400"><?php echo __('name'); ?></span>
                        <span class="text-white font-medium"
                            x-text="selectedConv?.client_name || '<?php echo __('unknown'); ?>'"></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400"><?php echo __('platform_id'); ?></span>
                        <span class="text-white font-medium font-mono"
                            x-text="selectedConv?.client_psid ? selectedConv.client_psid.substring(0, 10) + '...' : ''"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Selection Modal -->
    <div x-show="syncModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        x-cloak>
        <div class="bg-gray-900 border border-white/10 rounded-2xl w-full max-w-md p-6 shadow-2xl transform transition-all"
            @click.away="syncModalOpen = false">
            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                <?php echo __('sync_conversations'); ?>
            </h3>

            <p class="text-sm text-gray-400 mb-4"><?php echo __('choose_sync_target'); ?></p>

            <div class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar mb-4">
                <!-- Sync All Option -->
                <button @click="startSync(null)"
                    class="w-full flex items-center justify-between p-3 rounded-xl bg-gray-800 hover:bg-gray-700 transition-colors border border-white/5 hover:border-indigo-500/30 group">
                    <span class="text-sm font-medium text-white"><?php echo __('sync_all_pages'); ?></span>
                    <span
                        class="text-xs text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">Recommended</span>
                </button>

                <template x-for="p in pages" :key="p.page_id">
                    <button @click="startSync(p.page_id)"
                        class="w-full flex items-center justify-between p-3 rounded-xl bg-gray-800/50 hover:bg-gray-700 transition-colors border border-white/5 group">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-blue-600/20 text-blue-400 flex items-center justify-center">
                                <i class="fa-brands fa-facebook-f text-sm"></i>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-medium text-gray-200" x-text="p.page_name"></div>
                                <div class="text-[10px] text-gray-500"
                                    x-text="p.ig_business_id ? 'FB + IG Linked' : 'FB Only'"></div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-600 group-hover:text-white" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </template>

                <div x-show="pages.length === 0" class="text-center py-4 text-gray-500 text-xs">
                    Loading pages...
                </div>
            </div>

            <button @click="syncModalOpen = false" class="w-full py-2 text-sm text-gray-400 hover:text-white">
                <?php echo __('cancel'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    function smartInbox() {
        return {
            conversations: [],
            pages: [],
            syncModalOpen: false,
            // ... rest of data
            messages: [],
            suggestions: [],
            selectedConv: null,
            newMessage: '',
            analyzing: false,
            syncing: false,
            sidebarOpen: false,
            showRightSidebar: true, // Default open on desktop
            activeTab: 'all',
            searchQuery: '',

            analysis: {
                sentiment: null,
                intent: null,
                summary: null,
                next_best_action: null
            },

            get filteredConversations() {
                return this.conversations.filter(c => {
                    // Tab Filter
                    if (this.activeTab !== 'all' && c.platform !== this.activeTab) return false;

                    // Search Filter
                    if (this.searchQuery) {
                        const q = this.searchQuery.toLowerCase();
                        return (c.client_name && c.client_name.toLowerCase().includes(q)) ||
                            (c.last_message_text && c.last_message_text.toLowerCase().includes(q));
                    }
                    return true;
                });
            },

            getSentimentLabel(val) {
                if (!val) return '<?php echo __('neutral'); ?>';
                const map = {
                    'positive': '<?php echo __('positive'); ?>', // إيجابي
                    'neutral': '<?php echo __('neutral'); ?>',   // محايد
                    'negative': '<?php echo __('negative'); ?>', // سلبي
                    'angry': '<?php echo __('angry'); ?>'        // غاضب
                };
                return map[val.toLowerCase()] || val;
            },

            init() {
                this.fetchConversations();

                // Set initial states based on screen size
                const isDesktop = window.innerWidth >= 1280;
                this.sidebarOpen = window.innerWidth >= 1024;
                this.showRightSidebar = isDesktop;

                // Handle Resize auto-close
                window.addEventListener('resize', () => {
                    if (window.innerWidth < 1024) {
                        this.sidebarOpen = false;
                    }
                    if (window.innerWidth < 1280) {
                        this.showRightSidebar = false;
                    } else if (window.innerWidth >= 1280) {
                        this.showRightSidebar = true;
                    }
                });

                // Close sidebar when selecting convo on mobile
                this.$watch('selectedConv', () => {
                    if (window.innerWidth < 1024) this.sidebarOpen = false;
                });

                // Poll for new messages every 10s
                setInterval(() => {
                    if (this.selectedConv) this.fetchMessages(true);
                }, 10000);
            },

            fetchConversations() {
                fetch('smart_inbox_endpoint.php?action=list_conversations')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.conversations = data.conversations;
                        }
                    });
            },

            openSyncModal() {
                this.syncModalOpen = true;
                if (this.pages.length === 0) {
                    fetch('smart_inbox_endpoint.php?action=list_pages')
                        .then(r => r.json())
                        .then(d => { if (d.success) this.pages = d.pages; });
                }
            },

            startSync(pageId) {
                this.syncModalOpen = false;
                this.syncConversations(pageId);
            },

            syncConversations(pageId = null) {
                this.syncing = true;
                const url = pageId ? `smart_inbox_endpoint.php?action=sync_conversations&page_id=${pageId}` : 'smart_inbox_endpoint.php?action=sync_conversations';

                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        this.syncing = false;
                        if (data.success) {
                            if (data.errors && data.errors.length > 0) {
                                alert('Sync Completed with Warnings:\n' + data.errors.join('\n'));
                            } else {
                                // alert('<?php echo __('sync_success'); ?>');
                            }
                            this.fetchConversations();
                        } else {
                            alert('<?php echo __('sync_error'); ?>: ' + (data.errors ? data.errors.join(', ') : 'Unknown'));
                        }
                    })
                    .catch(e => {
                        this.syncing = false;
                        console.error(e);
                        alert('<?php echo __('sync_error'); ?>');
                    });
            },

            selectConversation(conv) {
                this.selectedConv = conv;
                this.analysis = {
                    sentiment: conv.ai_sentiment,
                    intent: conv.ai_intent,
                    summary: conv.ai_summary,
                    next_best_action: conv.ai_next_best_action
                };
                this.suggestions = conv.ai_suggested_replies ? JSON.parse(conv.ai_suggested_replies) : [];
                this.fetchMessages();
            },

            fetchMessages(silent = false) {
                if (!this.selectedConv) return;
                fetch(`smart_inbox_endpoint.php?action=get_messages&conversation_id=${this.selectedConv.id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const isAtBottom = this.isScrolledToBottom();
                            this.messages = data.messages;
                            if (!silent || isAtBottom) {
                                this.$nextTick(() => this.scrollToBottom());
                            }
                        }
                    });
            },

            analyzeThread() {
                if (!this.selectedConv) return;
                this.analyzing = true;

                fetch('smart_inbox_endpoint.php?action=analyze_thread', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: this.selectedConv.id })
                })
                    .then(r => r.json())
                    .then(data => {
                        this.analyzing = false;
                        if (data.success) {
                            const res = data.analysis;
                            this.analysis = {
                                sentiment: res.sentiment,
                                intent: res.intent,
                                summary: res.summary,
                                next_best_action: res.next_best_action
                            };
                            this.suggestions = res.suggested_replies || [];

                            // Update conversation list item locally
                            const idx = this.conversations.findIndex(c => c.id === this.selectedConv.id);
                            if (idx !== -1) {
                                this.conversations[idx].ai_sentiment = res.sentiment;
                                this.conversations[idx].ai_intent = res.intent;
                            }
                        } else {
                            alert('AI Error: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(() => {
                        this.analyzing = false;
                        alert('Connection failed');
                    });
            },

            sendMessage() {
                if (!this.selectedConv || !this.newMessage.trim()) return;
                const text = this.newMessage;
                this.newMessage = ''; // clear immediately

                // Optimistic update
                this.messages.push({
                    id: 'temp-' + Date.now(),
                    sender: 'page',
                    message_text: text,
                    created_at: new Date().toISOString()
                });
                this.$nextTick(() => this.scrollToBottom());

                fetch('smart_inbox_endpoint.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: this.selectedConv.id,
                        message_text: text
                    })
                }).then(r => r.json()).then(data => {
                    if (!data.success) {
                        alert('Failed to send: ' + (data.error || 'Unknown error'));
                        this.messages.pop(); // Remove optimistic msg
                    } else {
                        this.fetchMessages(true); // Sync real ID
                    }
                }).catch(e => {
                    alert('Conn Error');
                    this.messages.pop();
                });
            },

            scrollToBottom() {
                const container = document.getElementById('messages-container');
                if (container) container.scrollTop = container.scrollHeight;
            },

            isScrolledToBottom() {
                const container = document.getElementById('messages-container');
                if (!container) return false;
                return (container.scrollHeight - container.scrollTop - container.clientHeight) < 50;
            },

            formatDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },

            formatTime(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
        }
    }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }
</style>