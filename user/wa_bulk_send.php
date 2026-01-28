<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Fetch WhatsApp accounts for selection
// For now, using a placeholder until DB table is created
$wa_accounts = [
    ['id' => 1, 'phone' => '201012345678', 'name' => 'Support Line 1', 'status' => 'connected'],
    ['id' => 2, 'phone' => '201598765432', 'name' => 'Marketing Line 2', 'status' => 'connected'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4" x-data="{ 
    message: '', 
    numbers: '', 
    selectedAccounts: [],
    importMode: 'paste'
}">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-96 h-96 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 -right-4 w-96 h-96 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000 pointer-events-none">
        </div>

        <!-- Header -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('wa_bulk_send'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('wa_bulk_send_desc'); ?></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 relative z-10">
            <!-- Left Side: Config (Col 7) -->
            <div class="lg:col-span-12 xl:col-span-8 space-y-8 animate-fade-in" style="animation-delay: 100ms;">

                <!-- Account Selection Card -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8 relative overflow-hidden">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </span>
                        <?php echo __('wa_select_accounts'); ?>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($wa_accounts as $acc): ?>
                            <label class="relative group cursor-pointer">
                                <input type="checkbox" value="<?php echo $acc['id']; ?>" x-model="selectedAccounts"
                                    class="peer hidden">
                                <div
                                    class="flex items-center gap-4 p-4 rounded-2xl bg-white/5 border border-white/10 peer-checked:border-green-500/50 peer-checked:bg-green-500/5 transition-all duration-300">
                                    <div
                                        class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white font-bold truncate"><?php echo $acc['name']; ?></p>
                                        <p class="text-gray-500 text-xs truncate">+<?php echo $acc['phone']; ?></p>
                                    </div>
                                    <div
                                        class="w-5 h-5 rounded-full border-2 border-white/20 peer-checked:border-green-500 peer-checked:bg-green-500 flex items-center justify-center transition-all">
                                        <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Numbers List Card -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white flex items-center gap-3">
                            <span
                                class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            <?php echo __('wa_numbers_list'); ?>
                        </h3>
                        <div class="flex rounded-xl bg-white/5 p-1 border border-white/10">
                            <button @click="importMode = 'paste'"
                                :class="importMode === 'paste' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all">
                                <?php echo __('wa_paste_numbers'); ?>
                            </button>
                            <button @click="importMode = 'csv'"
                                :class="importMode === 'csv' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-xs font-bold transition-all">
                                <?php echo __('wa_import_csv'); ?>
                            </button>
                        </div>
                    </div>

                    <div x-show="importMode === 'paste'" class="animate-fade-in">
                        <textarea x-model="numbers" rows="6" placeholder="<?php echo __('wa_numbers_placeholder'); ?>"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-indigo-500/50 transition-all font-mono text-sm"></textarea>
                        <p class="mt-2 text-xs text-gray-500"><?php echo __('wa_numbers_hint'); ?></p>
                    </div>

                    <div x-show="importMode === 'csv'" class="animate-fade-in">
                        <div
                            class="border-2 border-dashed border-white/10 rounded-2xl p-8 text-center hover:border-indigo-500/30 transition-colors cursor-pointer relative group">
                            <input type="file" class="absolute inset-0 opacity-0 cursor-pointer">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4 group-hover:scale-110 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-white font-bold"><?php echo __('wa_csv_drop'); ?></p>
                            <p class="text-gray-500 text-xs mt-1"><?php echo __('wa_csv_hint'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Message Content & Settings -->
                <div class="glass-card rounded-[2.5rem] border border-white/5 p-8">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </span>
                        <?php echo __('wa_message_content'); ?>
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-bold text-gray-400"><?php echo __('message_text'); ?></label>
                                <div class="flex gap-2">
                                    <button
                                        class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] text-gray-400 hover:text-white"
                                        title="<?php echo __('wa_insert_var'); ?>">{{name}}</button>
                                    <button
                                        class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] text-gray-400 hover:text-white"
                                        title="<?php echo __('wa_spin_syntax'); ?>">{hi|hello}</button>
                                </div>
                            </div>
                            <textarea x-model="message" rows="5"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white focus:outline-none focus:border-purple-500/50 transition-all"
                                placeholder="<?php echo __('wa_msg_placeholder'); ?>"></textarea>
                        </div>

                        <!-- Settings Dividers -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-white/5">
                            <div>
                                <label
                                    class="text-sm font-bold text-gray-400 block mb-3"><?php echo __('wa_delay_random'); ?></label>
                                <div class="flex items-center gap-3">
                                    <input type="number" value="10"
                                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white">
                                    <span class="text-gray-500">-</span>
                                    <input type="number" value="25"
                                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white">
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-sm font-bold text-gray-400 block mb-3"><?php echo __('wa_switch_every'); ?></label>
                                <input type="number" value="5"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="flex justify-end pt-4 mb-20">
                    <button
                        class="group relative px-10 py-5 bg-gradient-to-r from-green-600 to-emerald-600 rounded-[2rem] overflow-hidden transition-all duration-300 hover:shadow-[0_20px_40px_-15px_rgba(22,163,74,0.4)] hover:scale-[1.02] active:scale-95">
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>
                        <div class="relative flex items-center gap-3 text-white">
                            <span
                                class="text-lg font-black uppercase tracking-widest"><?php echo __('wa_start_campaign'); ?></span>
                            <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Right Side: Live Preview (Col 4) -->
            <div class="hidden xl:block xl:col-span-4 sticky top-8 h-fit animate-fade-in"
                style="animation-delay: 200ms;">
                <div
                    class="relative w-full max-w-[340px] mx-auto aspect-[9/18.5] bg-black rounded-[3rem] border-[8px] border-zinc-800 shadow-2xl overflow-hidden">
                    <!-- WhatsApp Header Mockup -->
                    <div class="bg-[#075E54] p-4 pt-8 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-zinc-200 overflow-hidden">
                            <img src="https://ui-avatars.com/api/?name=User&background=random"
                                class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <p class="text-white text-xs font-bold leading-tight">Customer</p>
                            <p class="text-white/70 text-[10px]">online</p>
                        </div>
                        <div class="flex gap-2">
                            <div class="w-1 h-1 rounded-full bg-white/80"></div>
                            <div class="w-1 h-1 rounded-full bg-white/80"></div>
                            <div class="w-1 h-1 rounded-full bg-white/80"></div>
                        </div>
                    </div>

                    <!-- Chat Background -->
                    <div class="absolute inset-0 top-20 bg-[#e5ddd5] opacity-100 -z-10"
                        style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: cover; background-repeat: no-repeat;">
                    </div>

                    <!-- Preview Bubbles -->
                    <div class="p-4 pt-10 space-y-4 h-full overflow-y-auto">
                        <div class="flex justify-end animate-fade-in" x-show="message.length > 0">
                            <div class="bg-[#dcf8c6] p-3 rounded-lg rounded-tr-none shadow-sm max-w-[85%] relative">
                                <p class="text-zinc-800 text-xs whitespace-pre-wrap" x-text="message"></p>
                                <div class="flex justify-end items-center gap-1 mt-1">
                                    <span class="text-[9px] text-zinc-500">10:45 AM</span>
                                    <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-4.24l-1.41-1.41L9 13.17 5.17 9.34 3.76 10.75 9 16l13.24-13.24zM1 10.5L2.5 9l5.5 5.5L6.5 16 1 10.5z" />
                                    </svg>
                                </div>
                                <!-- Tail -->
                                <div class="absolute -right-2 top-0 w-3 h-3 bg-[#dcf8c6]"
                                    style="clip-path: polygon(0 0, 0% 100%, 100% 0);"></div>
                            </div>
                        </div>
                        <div x-show="message.length === 0" class="text-center mt-20 italic text-zinc-400 text-xs">
                            <?php echo __('wa_msg_placeholder'); ?>
                        </div>
                    </div>

                    <!-- Bottom Input Mockup -->
                    <div class="absolute bottom-0 inset-x-0 p-3 bg-zinc-100 flex items-center gap-2">
                        <div
                            class="flex-1 bg-white rounded-full px-4 py-2 flex items-center gap-2 shadow-sm border border-zinc-200">
                            <div class="text-zinc-300"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" />
                                    <path d="M8 11h3v3h2v-3h3v-2h-3V6h-2v3H8z" />
                                </svg></div>
                            <div class="flex-1 text-[10px] text-zinc-400">Type a message</div>
                            <div class="text-zinc-300"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" />
                                    <path d="M12 17.5l-2.5-2.5h2V10h1v5h2zM15 8H9v1h6V8z" />
                                </svg></div>
                        </div>
                        <div
                            class="w-10 h-10 rounded-full bg-[#075E54] flex items-center justify-center text-white shadow-md">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
                                <path
                                    d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mt-6 glass-card p-6 rounded-3xl border border-white/5 bg-white/5">
                    <h5 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <?php echo __('wa_preview'); ?>
                    </h5>
                    <p class="text-gray-400 text-xs"><?php echo __('wa_preview_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>