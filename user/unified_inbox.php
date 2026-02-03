<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .glass-panel {
        background: rgba(17, 24, 39, 0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s ease;
    }

    .glass-card:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .glass-card.active {
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.3);
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .gradient-text {
        background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .chat-bubble-received {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1.2rem 1.2rem 1.2rem 0.2rem;
    }

    .chat-bubble-sent {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.2) 100%);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 1.2rem 1.2rem 0.2rem 1.2rem;
    }

    .ai-insight-bar {
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .suggestion-btn {
        background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
    }

    .suggestion-btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
</style>

<div class="flex h-[calc(100vh-2rem)] overflow-hidden bg-[#0a0f1a] text-white font-sans selection:bg-indigo-500/30"
    x-data="unifiedInbox()" x-init="init()">

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <!-- Main Content Area (Unified Inbox) -->
    <div class="flex-1 flex gap-4 p-4 min-w-0">

        <!-- Column 1: Active Conversations -->
        <div class="w-80 flex flex-col glass-panel rounded-3xl overflow-hidden shrink-0">
            <div class="p-6 border-b border-white/5">
                <h2 class="text-xl font-black mb-4">
                    <?php echo __('active_conversations'); ?>
                </h2>
                <div class="relative">
                    <input type="text" placeholder="<?php echo __('search_placeholder'); ?>"
                        class="w-full bg-white/5 border border-white/10 rounded-xl py-2 px-10 text-sm focus:outline-none focus:border-indigo-500/50 transition-all">
                    <svg class="w-4 h-4 absolute left-4 top-2.5 text-gray-500" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-2">
                <template x-for="chat in conversations" :key="chat.id">
                    <div @click="selectChat(chat)"
                        class="glass-card p-4 rounded-2xl cursor-pointer flex items-center gap-3 relative overflow-hidden group"
                        :class="selectedChatId === chat.id ? 'active' : ''">
                        <div class="relative shrink-0">
                            <img :src="chat.avatar" class="w-12 h-12 rounded-xl object-cover border border-white/10">
                            <div
                                class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center p-1 bg-gray-900 border border-white/10 shadow-lg">
                                <img :src="chat.platformIcon" class="w-full h-full">
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="font-bold text-sm truncate" x-text="chat.name"></h3>
                                <template x-if="chat.ai_intent">
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded-md font-bold uppercase tracking-tighter"
                                        :class="chat.ai_intent_color" x-text="chat.ai_intent_label"></span>
                                </template>
                            </div>
                            <p class="text-xs text-gray-500 truncate" x-text="chat.last_message"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Column 2: Chat Interface -->
        <div class="flex-1 flex flex-col glass-panel rounded-3xl overflow-hidden min-w-0 relative">
            <template x-if="!selectedChatId">
                <div class="flex-1 flex flex-col items-center justify-center text-gray-600 opacity-50">
                    <svg class="w-20 h-20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <p class="text-xl font-medium">
                        <?php echo __('select_chat_hint'); ?>
                    </p>
                </div>
            </template>

            <template x-if="selectedChatId">
                <div class="flex flex-col h-full">
                    <!-- Chat Header -->
                    <div class="p-6 border-b border-white/5 flex items-center justify-between bg-white/[0.02]">
                        <div class="flex items-center gap-4">
                            <img :src="currentChat.avatar"
                                class="w-10 h-10 rounded-xl object-cover border border-white/10">
                            <div>
                                <h3 class="font-bold text-lg" x-text="currentChat.name"></h3>
                                <span class="text-xs text-green-500 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    <?php echo __('online_status'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button class="p-2.5 rounded-xl border border-white/5 hover:bg-white/5 transition-all">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </button>
                            <button class="p-2.5 rounded-xl border border-white/5 hover:bg-white/5 transition-all">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- AI Insight Bar -->
                    <div class="ai-insight-bar px-6 py-2.5 flex items-center gap-4 text-xs font-medium">
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500">Sentiment:</span>
                            <span class="text-green-400" x-text="currentChat.ai_sentiment || 'Neutral'"></span>
                        </div>
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500">Intent:</span>
                            <span class="text-indigo-400" x-text="currentChat.ai_intent_label || 'Scanning...'"></span>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">
                        <template x-for="msg in messages" :key="msg.id">
                            <div class="flex flex-col" :class="msg.is_sent ? 'items-end' : 'items-start'">
                                <div class="max-w-[80%] p-4 text-sm"
                                    :class="msg.is_sent ? 'chat-bubble-sent' : 'chat-bubble-received'">
                                    <p x-text="msg.text"></p>
                                </div>
                                <span class="text-[10px] text-gray-600 mt-2 px-1" x-text="msg.time"></span>
                            </div>
                        </template>
                    </div>

                    <!-- AI Suggestion Area -->
                    <div class="px-6 py-4 bg-white/[0.02] border-t border-white/5">
                        <div
                            class="flex items-center justify-between mb-3 text-[10px] font-black uppercase text-gray-500 tracking-widest">
                            <span>Smart AI Suggested Reply</span>
                            <span class="text-indigo-400 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1a1 1 0 112 0v1a1 1 0 11-2 0zM13.536 14.243a1 1 0 011.414 1.414l-.707.707a1 1 0 00-1.414-1.414l.707-.707zM16.243 14.95a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 001.414-1.414l.707.707z" />
                                </svg>
                                AI Engine V2
                            </span>
                        </div>
                        <button
                            class="w-full p-4 rounded-2xl suggestion-btn text-left text-sm font-medium flex items-center justify-between group">
                            <span x-text="currentChat.ai_suggestion || 'Scanning context for suggestions...'"></span>
                            <svg class="w-5 h-5 opacity-0 group-hover:opacity-100 transition-all translate-x-2 group-hover:translate-x-0"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </div>

                    <!-- Input Area -->
                    <div class="p-6 bg-white/[0.03]">
                        <div class="flex items-center gap-4">
                            <button class="text-gray-500 hover:text-indigo-400 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                            </button>
                            <input type="text" placeholder="Type a message..."
                                class="flex-1 bg-white/5 border border-white/10 rounded-2xl py-3 px-6 text-sm focus:outline-none focus:border-indigo-500/50 transition-all">
                            <button
                                class="bg-indigo-600 p-3 rounded-2xl hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-500/20">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Column 3: Lead Intelligence -->
        <div class="w-80 flex flex-col gap-4 shrink-0">
            <!-- Profile Info -->
            <div class="glass-panel p-6 rounded-3xl shrink-0">
                <div class="flex items-center gap-4 mb-6">
                    <img :src="currentChat.avatar || 'https://via.placeholder.com/100'"
                        class="w-16 h-16 rounded-2xl object-cover border border-white/10">
                    <div>
                        <h3 class="font-black text-lg truncate" x-text="currentChat.name || 'Select Contact'"></h3>
                        <p class="text-xs text-gray-500" x-text="currentChat.platformName || 'N/A'"></p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Total Interactions</span>
                        <span class="font-bold">154</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">First Contact</span>
                        <span class="font-bold">Jan 24, 2024</span>
                    </div>
                </div>
            </div>

            <!-- Lead Score -->
            <div class="glass-panel p-6 rounded-3xl flex-1 flex flex-col shrink-0">
                <h3 class="text-xs font-black uppercase text-gray-500 tracking-widest mb-6">Lead Scoring</h3>
                <div class="flex-1 flex flex-col items-center justify-center relative">
                    <!-- Gauge Mockup -->
                    <div
                        class="w-40 h-40 rounded-full border-[10px] border-white/5 relative flex items-center justify-center">
                        <svg class="w-full h-full -rotate-90 absolute">
                            <circle cx="80" cy="80" r="70" fill="none" stroke="currentColor" stroke-width="10"
                                stroke-dasharray="440" :stroke-dashoffset="440 - (440 * leadScore / 100)"
                                class="text-indigo-500 transition-all duration-1000"></circle>
                        </svg>
                        <div class="text-center">
                            <div class="text-4xl font-black mb-1" x-text="leadScore"></div>
                            <div class="text-[10px] text-gray-500 uppercase font-black tracking-widest leading-none">
                                High Potential</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sentiment Chart -->
            <div class="glass-panel p-6 rounded-3xl shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-black uppercase text-gray-500 tracking-widest">Sentiment Trend</h3>
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                </div>
                <div class="h-20 flex items-end gap-1.5 px-2">
                    <template x-for="val in [40, 60, 45, 90, 85]" :key="Math.random()">
                        <div class="flex-1 bg-indigo-500/20 rounded-t-sm hover:bg-indigo-500/40 transition-all cursor-pointer"
                            :style="`height: ${val}%`" :title="val + '%'"></div>
                    </template>
                </div>
                <div class="flex items-center justify-between mt-4 text-[10px] font-bold text-gray-600 uppercase">
                    <span>Last Week</span>
                    <span>Today</span>
                </div>
            </div>

            <!-- AI Pro Tip -->
            <div class="p-6 rounded-3xl bg-indigo-500/10 border border-indigo-500/20 shrink-0">
                <div class="flex items-center gap-2 mb-3 text-indigo-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1a1 1 0 112 0v1a1 1 0 11-2 0zM13.536 14.243a1 1 0 011.414 1.414l-.707.707a1 1 0 00-1.414-1.414l.707-.707zM16.243 14.95a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 001.414-1.414l.707.707z" />
                    </svg>
                    <span class="text-xs font-black uppercase tracking-widest">AI Pro-tip</span>
                </div>
                <p class="text-xs leading-relaxed text-indigo-200/80" x-text="aiProTip"></p>
            </div>
        </div>
    </div>
</div>

<script>
    function unifiedInbox() {
        return {
            selectedChatId: null,
            leadScore: 0,
            aiProTip: 'Select a conversation to analyze the lead potential and get smart recommendations.',
            currentChat: {},
            conversations: [
                {
                    id: 1,
                    name: 'Sarah Jenkins',
                    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150',
                    last_message: 'How much for the basic plan? and do you have...',
                    platformIcon: 'https://cdn-icons-png.flaticon.com/512/5968/5968841.png',
                    platformName: 'WhatsApp Business',
                    ai_intent: 'sales',
                    ai_intent_label: 'Sales Lead',
                    ai_intent_color: 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
                    ai_sentiment: 'Positive',
                    ai_suggestion: 'Offer the 10% discount now to close the deal. Point out that the early bird offer expires tomorrow.',
                    ai_pro_tip: 'Sarah is showing strong buying signals. She has asked about pricing twice and seems focused on the basic package. Close now for best results.',
                    score: 85
                },
                {
                    id: 2,
                    name: 'David Chen',
                    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=150',
                    last_message: 'Thanks for the info, will discuss with my team.',
                    platformIcon: 'https://cdn-icons-png.flaticon.com/512/5968/5968764.png',
                    platformName: 'FB Messenger',
                    ai_intent: 'inquiry',
                    ai_intent_label: 'Inquiry',
                    ai_intent_color: 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30',
                    ai_sentiment: 'Neutral',
                    ai_suggestion: 'Suggest a brief demo call next week to answer any team concerns.',
                    ai_pro_tip: 'David is a middle-of-funnel lead. He needs more technical validation before committing. Push for a technical demo.',
                    score: 42
                },
                {
                    id: 3,
                    name: 'Maria Barren',
                    avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=150',
                    last_message: 'My order is late for 2 days now, please check!',
                    platformIcon: 'https://cdn-icons-png.flaticon.com/512/5968/5968841.png',
                    platformName: 'WhatsApp Business',
                    ai_intent: 'support',
                    ai_intent_label: 'Support',
                    ai_intent_color: 'bg-red-500/20 text-red-400 border border-red-500/30',
                    ai_sentiment: 'Negative',
                    ai_suggestion: 'Apologize for the delay and provide the latest tracking status immediately.',
                    ai_pro_tip: 'Maria is frustrated. Priority is to resolve the delivery issue. Do not attempt any upselling.',
                    score: 5
                }
            ],
            messages: [
                { id: 1, text: 'Hi, I saw your ad on Facebook about the automation tool.', time: '10:30 AM', is_sent: false },
                { id: 2, text: 'Hello! Yes, our tool helps businesses automate their customer engagement. How can I help you today?', time: '10:31 AM', is_sent: true },
                { id: 3, text: 'I am interested in the basic plan. How much is it annually?', time: '10:32 AM', is_sent: false },
                { id: 4, text: 'The annual plan is $299, saving you 20% compared to monthly payments.', time: '10:35 AM', is_sent: true },
                { id: 5, text: 'Great! Is there a discount for new users?', time: '10:40 AM', is_sent: false }
            ],
            init() {
                console.log('Unified Inbox Initialized');
            },
            selectChat(chat) {
                this.selectedChatId = chat.id;
                this.currentChat = chat;
                // Animate lead score
                this.leadScore = 0;
                setTimeout(() => {
                    this.leadScore = chat.score;
                    this.aiProTip = chat.ai_pro_tip;
                }, 100);
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>