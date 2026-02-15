<?php
include '../includes/header.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
?>

<div class="flex h-screen overflow-hidden bg-gray-900 font-sans" x-data="smartInbox()">

    <!-- Left Sidebar (Conversations) -->
    <div class="w-80 flex-shrink-0 border-r border-white/5 bg-gray-900/95 backdrop-blur-xl flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white tracking-wide">Smart Inbox</h2>
            <div class="flex gap-2">
                <button @click="fetchConversations()" class="p-2 text-gray-400 hover:text-white transition-colors"
                    title="Refresh">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                </button>
                <a href="ai_advisor_settings.php" class="p-2 text-gray-400 hover:text-indigo-400 transition-colors"
                    title="Settings">
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

        <!-- Search -->
        <div class="p-4">
            <input type="text" placeholder="Search conversations..."
                class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 transition-colors">
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1">
            <template x-for="conv in conversations" :key="conv.id">
                <div @click="selectConversation(conv)"
                    :class="selectedConv && selectedConv.id === conv.id ? 'bg-white/10 border-indigo-500/50' : 'hover:bg-white/5 border-transparent'"
                    class="p-3 rounded-xl cursor-pointer border transition-all group relative">

                    <div class="flex justify-between items-start mb-1">
                        <div class="flex items-center gap-2">
                            <!-- Platform Icon -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs"
                                :class="conv.platform === 'facebook' ? 'bg-blue-600' : 'bg-pink-600'">
                                <span x-text="conv.client_name.charAt(0).toUpperCase()"></span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-200 leading-tight" x-text="conv.client_name">
                                </h4>
                                <span class="text-[10px] text-gray-500"
                                    x-text="formatDate(conv.last_message_time)"></span>
                            </div>
                        </div>

                        <!-- Sentiment Dot -->
                        <div class="w-3 h-3 rounded-full shadow-lg border border-black/50" :class="{
                                'bg-green-500': conv.ai_sentiment === 'positive',
                                'bg-gray-500': conv.ai_sentiment === 'neutral' || !conv.ai_sentiment,
                                'bg-yellow-500': conv.ai_sentiment === 'negative',
                                'bg-red-600 animate-pulse': conv.ai_sentiment === 'angry'
                            }" :title="'Sentiment: ' + (conv.ai_sentiment || 'Unknown')">
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 line-clamp-1 mb-2" x-text="conv.last_message_text"></p>

                    <!-- Intent Badge -->
                    <template x-if="conv.ai_intent">
                        <span
                            class="inline-block px-2 py-0.5 rounded-md bg-indigo-500/10 text-indigo-300 text-[10px] font-medium border border-indigo-500/20"
                            x-text="conv.ai_intent"></span>
                    </template>
                </div>
            </template>

            <div x-show="conversations.length === 0" class="text-center text-gray-500 text-sm mt-10">
                No active conversations found.
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
                <p>Select a conversation to start chatting</p>
            </div>
        </template>

        <template x-if="selectedConv">
            <div class="flex-1 flex flex-col h-full">
                <!-- Chat Header -->
                <div
                    class="h-16 border-b border-white/5 bg-gray-900/95 backdrop-blur-xl flex items-center justify-between px-6 z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
                            :class="selectedConv.platform === 'facebook' ? 'bg-blue-600' : 'bg-pink-600'">
                            <span x-text="selectedConv.client_name.charAt(0)"></span>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg" x-text="selectedConv.client_name"></h3>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                <span class="text-xs text-gray-400 capitalize" x-text="selectedConv.platform"></span>
                            </div>
                        </div>
                    </div>

                    <button @click="analyzing = true; analyzeThread()"
                        class="flex items-center gap-2 px-4 py-2 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded-lg border border-indigo-500/30 transition-all text-sm font-bold">
                        <svg class="w-4 h-4" :class="analyzing ? 'animate-spin' : ''" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span x-text="analyzing ? 'Thinking...' : 'Analyze with AI'"></span>
                    </button>
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

                <!-- Smart Suggestions Bar -->
                <div class="px-6 py-2 bg-gray-900 border-t border-white/5" x-show="suggestions.length > 0">
                    <div class="flex items-center gap-2 overflow-x-auto pb-2 custom-scrollbar">
                        <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider shrink-0 mr-2">
                            AI Suggestions:
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
                <div class="p-4 bg-gray-900 border-t border-white/5">
                    <div
                        class="glass-panel rounded-2xl p-2 flex items-end gap-2 bg-gray-800/50 border border-white/10 focus-within:ring-2 ring-indigo-500/50 transition-all">
                        <textarea x-model="newMessage" placeholder="Type a message..." rows="1"
                            class="w-full bg-transparent border-none text-white focus:ring-0 resize-none max-h-32 py-3 px-3 custom-scrollbar"
                            @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"></textarea>

                        <button @click="sendMessage()"
                            class="p-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-500/20 mb-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Right Sidebar (Advisor Panel) -->
    <div x-show="selectedConv" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        class="w-80 border-l border-white/5 bg-gray-900/95 backdrop-blur-xl flex flex-col overflow-y-auto custom-scrollbar">

        <div class="p-6 space-y-6">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest">AI Advisor Insights</h3>

            <!-- Analysis Card -->
            <div
                class="glass-panel p-5 rounded-2xl bg-gradient-to-br from-gray-800/50 to-gray-900/50 border border-white/10 group hover:border-indigo-500/30 transition-all">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-bold text-gray-400">Current Mood</span>
                    <span class="px-2 py-1 rounded-md text-xs font-bold uppercase border" :class="{
                            'bg-green-500/10 text-green-400 border-green-500/20': analysis.sentiment === 'positive',
                            'bg-gray-500/10 text-gray-400 border-gray-500/20': analysis.sentiment === 'neutral' || !analysis.sentiment,
                            'bg-red-500/10 text-red-400 border-red-500/20': ['negative', 'angry'].includes(analysis.sentiment)
                        }" x-text="analysis.sentiment || 'Waiting...'"></span>
                </div>

                <h4 class="text-sm font-bold text-white mb-1">Intent</h4>
                <p class="text-xs text-gray-400 mb-4" x-text="analysis.intent || 'Not analyzed yet'"></p>

                <h4 class="text-sm font-bold text-white mb-1">Summary</h4>
                <p class="text-xs text-gray-400 leading-relaxed"
                    x-text="analysis.summary || 'Analyze the conversation to generate a summary.'"></p>
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
                    Next Best Action
                </h3>
                <p class="text-sm text-gray-200 font-medium leading-relaxed"
                    x-text="analysis.next_best_action || 'No recommendation available yet.'"></p>
            </div>

            <!-- Customer Info (Mockup for now) -->
            <div class="border-t border-white/10 pt-6">
                <h3 class="text-xs font-bold text-gray-500 uppercase mb-4">Customer Details</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Name</span>
                        <span class="text-white font-medium" x-text="selectedConv.client_name"></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Platform ID</span>
                        <span class="text-white font-medium font-mono"
                            x-text="selectedConv.client_psid.substring(0, 10) + '...'"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    function smartInbox() {
        return {
            conversations: [],
            messages: [],
            suggestions: [],
            selectedConv: null,
            newMessage: '',
            analyzing: false,
            analysis: {
                sentiment: null,
                intent: null,
                summary: null,
                next_best_action: null
            },

            init() {
                this.fetchConversations();
                // Poll for new messages every 10s
                setInterval(() => {
                    if (this.selectedConv) this.fetchMessages(true);
                }, 10000);
            },

            fetchConversations() {
                fetch('../includes/api/smart_inbox_api.php?action=list_conversations')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.conversations = data.conversations;
                        }
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
                fetch(`../includes/api/smart_inbox_api.php?action=get_messages&conversation_id=${this.selectedConv.id}`)
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

                fetch('../includes/api/smart_inbox_api.php?action=analyze_thread', {
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

                fetch('../includes/api/smart_inbox_api.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: this.selectedConv.id,
                        message_text: text
                    })
                }).then(() => {
                    this.fetchMessages(true); // Sync real ID
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