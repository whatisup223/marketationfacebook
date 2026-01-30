<?php
// user/fb_scheduler.php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$current_user = $_SESSION['user_id'];
$pdo = getDB();

// Fetch Pages
$stmt = $pdo->prepare("SELECT p.* FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE a.user_id = ? GROUP BY p.page_id");
$stmt->execute([$current_user]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing scheduled posts
$stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE user_id = ? ORDER BY scheduled_at DESC");
$stmt->execute([$current_user]);
$scheduled_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
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

    [x-cloak] {
        display: none !important;
    }

    @media (max-width: 768px) {
        .modal-fullscreen {
            height: 100dvh !important;
            max-height: 100dvh !important;
            border-radius: 0 !important;
        }
    }
</style>

<div class="flex min-h-screen pt-4" x-data="postScheduler()" x-init="$watch('showModal', value => {
    if (value) document.body.classList.add('overflow-hidden');
    else document.body.classList.remove('overflow-hidden');
})">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <main class="flex-1 p-4 md:p-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header & Control Bar -->
            <div class="mb-8 p-6 glass-panel rounded-[2.5rem] border border-white/5 bg-gray-900/40">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-white mb-2"><?php echo __('post_scheduler'); ?></h1>
                        <p class="text-gray-400 text-sm"><?php echo __('schedule_guideline'); ?></p>
                    </div>

                    <!-- Page Selector & Status -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                        <div class="relative min-w-[240px]">
                            <select x-model="formData.page_id" @change="fetchTokenDebug();"
                                class="w-full bg-black/40 border border-white/10 rounded-2xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 outline-none appearance-none transition-all pr-10">
                                <option value=""><?php echo __('select_page'); ?>...</option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo $page['page_id']; ?>">
                                        <?php echo htmlspecialchars($page['page_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Token Badge -->
                        <div x-show="formData.page_id" x-cloak
                            class="flex items-center gap-2 px-4 py-3 bg-white/5 rounded-2xl border border-white/10">
                            <div class="w-2 h-2 rounded-full animate-pulse"
                                :class="debugInfo ? 'bg-green-500' : 'bg-red-500'"></div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                x-text="debugInfo ? '<?php echo __('valid_token'); ?>' : '<?php echo __('invalid_token'); ?>'"></span>
                        </div>

                        <button @click="openCreateModal()"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2 group">
                            <svg class="w-5 h-5 group-hover:rotate-90 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            <?php echo __('create_post'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- List of Scheduled Posts -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Empty State: No page selected -->
                <div x-show="formData.page_id === ''" x-cloak
                    class="col-span-full glass-panel p-12 text-center border border-white/5 bg-gray-900/40">
                    <div
                        class="w-16 h-16 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <p class="text-gray-400"><?php echo __('no_scheduled_posts'); ?></p>
                </div>
                <?php if (empty($scheduled_posts)): ?>
                    <div x-show="formData.page_id !== ''" x-cloak
                        class="col-span-full glass-panel p-12 text-center border border-white/5 bg-gray-900/40">
                        <div
                            class="w-16 h-16 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                            </svg>
                        </div>
                        <p class="text-gray-400"><?php echo __('no_scheduled_posts'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($scheduled_posts as $post): ?>
                        <div x-show="formData.page_id !== '' && formData.page_id === '<?php echo $post['page_id']; ?>'"
                            class="glass-card p-5 rounded-3xl border border-white/5 bg-white/5 hover:bg-white/10 transition-all group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white truncate max-w-[120px]">
                                            <?php
                                            $p_name = "Page";
                                            foreach ($pages as $p)
                                                if ($p['page_id'] == $post['page_id'])
                                                    $p_name = $p['page_name'];
                                            echo htmlspecialchars($p_name);
                                            ?>
                                        </h4>
                                        <p class="text-[10px] text-gray-500">
                                            <?php echo date('M d, Y H:i', strtotime($post['scheduled_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider 
                                    <?php echo $post['status'] === 'success' ? 'bg-green-500/20 text-green-400' :
                                        ($post['status'] === 'pending' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-red-500/20 text-red-400'); ?>">
                                    <?php echo $post['status'] === 'pending' ? 'Scheduled' : __($post['status']); ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-300 line-clamp-3 mb-4 h-15">
                                <?php echo htmlspecialchars($post['content']); ?>
                            </p>
                            <?php if ($post['media_url']): ?>
                                <div
                                    class="relative aspect-video rounded-xl overflow-hidden bg-black/40 mb-4 border border-white/5">
                                    <?php
                                    $is_video = false;
                                    $ext = strtolower(pathinfo($post['media_url'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['mp4', 'mov', 'avi']))
                                        $is_video = true;

                                    if ($is_video): ?>
                                        <video src="<?php echo htmlspecialchars($post['media_url']); ?>"
                                            class="w-full h-full object-cover" muted></video>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <svg class="w-10 h-10 text-white opacity-70" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z">
                                                </path>
                                            </svg>
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($post['media_url']); ?>"
                                            class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex gap-2">
                                <button @click="deletePost(<?php echo $post['id']; ?>)"
                                    class="flex-1 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl text-xs font-bold transition-all border border-red-500/20">
                                    <?php echo __('delete'); ?>
                                </button>
                                <?php if ($post['status'] === 'pending'): ?>
                                    <button @click="editPost(<?php echo htmlspecialchars(json_encode($post)); ?>)"
                                        class="px-3 py-2 bg-white/5 hover:bg-white/10 text-gray-400 rounded-xl transition-all border border-white/5"
                                        title="<?php echo __('edit'); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create Post Modal -->
        <div x-show="showModal" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-0 md:p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="showModal = false"></div>

            <div class="glass-panel w-full max-w-6xl bg-gray-900 border border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden relative z-10 flex flex-col h-full md:h-[90vh] modal-fullscreen"
                @keydown.escape.window="showModal = false">

                <!-- Modal Header -->
                <div class="p-6 md:p-8 flex justify-between items-center border-b border-white/5 flex-shrink-0">
                    <h3 class="text-2xl font-bold text-white"><?php echo __('create_post'); ?></h3>
                    <button @click="showModal = false"
                        class="text-gray-500 hover:text-white transition-colors p-2 bg-white/5 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Main Scrollable Area -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-8">
                    <form @submit.prevent="submitPost" class="max-w-5xl mx-auto">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12">

                            <!-- Left Side: Form Controls -->
                            <div class="col-span-1 md:col-span-7 space-y-8">
                                <!-- Selected Page Info -->
                                <div
                                    class="p-5 bg-indigo-500/10 rounded-[2rem] border border-indigo-500/20 flex items-center justify-between shadow-lg">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-indigo-600/30">
                                            <span x-text="getPageName() ? getPageName().charAt(0) : '?'"></span>
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] uppercase font-black text-indigo-400 tracking-widest mb-1">
                                                <?php echo __('facebook_page'); ?>
                                            </p>
                                            <h4 class="text-white font-bold text-lg"
                                                x-text="getPageName() || '<?php echo __('select_page_first'); ?>'"></h4>
                                        </div>
                                    </div>
                                    <div class="px-4 py-1.5 bg-green-500/20 rounded-full text-[10px] font-black text-green-400 uppercase tracking-widest"
                                        x-show="debugInfo">Connected</div>
                                </div>

                                <!-- Post Type Selection -->
                                <div class="space-y-4">
                                    <label
                                        class="block text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('post_type'); ?></label>
                                    <div
                                        class="flex flex-wrap gap-2 p-1.5 bg-black/30 rounded-2xl border border-white/5 max-w-fit">
                                        <button type="button" @click="formData.post_type = 'feed'"
                                            :class="formData.post_type === 'feed' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400'"
                                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all"><?php echo __('feed_post'); ?></button>
                                        <button type="button" @click="formData.post_type = 'story'"
                                            :class="formData.post_type === 'story' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400'"
                                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all"><?php echo __('story'); ?></button>
                                        <button type="button" @click="formData.post_type = 'reel'"
                                            :class="formData.post_type === 'reel' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400'"
                                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all"><?php echo __('reel'); ?></button>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="space-y-4"
                                    x-show="formData.post_type === 'feed'|| formData.post_type === 'reel'">
                                    <label
                                        class="block text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('post_content'); ?>
                                        <span
                                            x-show="formData.post_type !== 'reel'">(<?php echo __('optional'); ?>)</span>
                                        <span x-show="formData.post_type === 'reel'"
                                            class="text-red-400">(<?php echo __('required'); ?>)</span>
                                    </label>
                                    <textarea x-model="formData.content" rows="6"
                                        class="w-full bg-black/40 border border-white/10 rounded-[1.5rem] px-6 py-5 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all resize-none text-base leading-relaxed"
                                        placeholder="<?php echo __('post_content_placeholder'); ?>"></textarea>
                                </div>

                                <!-- Media -->
                                <div class="space-y-4">
                                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">
                                        <?php echo __('upload_image'); ?>
                                        <span
                                            x-show="formData.post_type === 'feed'">(<?php echo __('optional'); ?>)</span>
                                        <span x-show="formData.post_type === 'story' || formData.post_type === 'reel'"
                                            class="text-red-400">(<?php echo __('required'); ?>)</span>
                                    </label>

                                    <div
                                        class="flex gap-2 p-1.5 bg-black/30 rounded-2xl border border-white/5 mb-4 max-w-fit">
                                        <button type="button" @click="mediaMode = 'url'"
                                            :class="mediaMode === 'url' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400'"
                                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all"><?php echo __('use_url'); ?></button>
                                        <button type="button" @click="mediaMode = 'file'"
                                            :class="mediaMode === 'file' ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-400'"
                                            class="px-5 py-2.5 rounded-xl text-xs font-black transition-all"><?php echo __('local_file'); ?></button>
                                    </div>

                                    <div x-show="mediaMode === 'url'"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-2">
                                        <input type="url" x-model="formData.media_url"
                                            class="w-full bg-black/40 border border-white/10 rounded-[1.5rem] px-6 py-5 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm"
                                            placeholder="<?php echo __('image_url_placeholder'); ?>">
                                    </div>

                                    <div x-show="mediaMode === 'file'"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-2">
                                        <label
                                            class="flex flex-col items-center justify-center w-full h-40 border-2 border-white/10 border-dashed rounded-[1.5rem] cursor-pointer bg-black/10 hover:bg-black/20 transition-all hover:border-indigo-500/50 group">

                                            <!-- Drag Drop UI -->
                                            <div
                                                class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                                <svg class="w-12 h-12 mb-3 text-gray-500 group-hover:text-indigo-400 transition-colors"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                                        stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                </svg>
                                                <p class="text-sm text-gray-400 font-bold mb-1">
                                                    <?php echo __('upload_image'); ?>
                                                </p>
                                                <p class="text-[10px] text-gray-500 uppercase tracking-widest">
                                                    Supports Multiple Files
                                                </p>
                                            </div>
                                            <input type="file" @change="handleFileUpload" class="hidden"
                                                accept="image/*,video/*" multiple />
                                        </label>

                                        <!-- File List -->
                                        <div x-show="filesSelected.length > 0" class="mt-4 space-y-2">
                                            <template x-for="(file, index) in filesSelected" :key="index">
                                                <div
                                                    class="flex items-center justify-between p-3 bg-black/30 rounded-xl border border-white/5 group hover:border-indigo-500/30 transition-all">
                                                    <div class="flex items-center gap-3 overflow-hidden">
                                                        <div
                                                            class="w-10 h-10 rounded-lg bg-gray-800 flex-shrink-0 flex items-center justify-center overflow-hidden">
                                                            <template x-if="file.type.startsWith('image/')">
                                                                <img :src="file.preview"
                                                                    class="w-full h-full object-cover">
                                                            </template>
                                                            <template x-if="!file.type.startsWith('image/')">
                                                                <svg class="w-5 h-5 text-gray-500" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </template>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="text-xs text-gray-300 font-bold truncate"
                                                                x-text="file.name"></p>
                                                            <p class="text-[10px] text-gray-500"
                                                                x-text="(file.size/1024/1024).toFixed(2) + ' MB'"></p>
                                                        </div>
                                                    </div>
                                                    <button type="button" @click="removeFile(index)"
                                                        class="p-2 hover:bg-red-500/10 text-gray-500 hover:text-red-400 rounded-lg transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Scheduled Time -->
                                <div class="space-y-4">
                                    <label
                                        class="block text-xs font-black text-gray-500 uppercase tracking-widest"><?php echo __('scheduled_time'); ?></label>
                                    <input type="datetime-local" x-model="formData.scheduled_at"
                                        class="w-full bg-black/40 border border-white/10 rounded-[1.5rem] px-6 py-5 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <div class="flex items-center gap-2 px-2">
                                        <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                stroke-width="2" />
                                        </svg>
                                        <p class="text-[10px] text-gray-500 italic">
                                            <?php echo __('schedule_guideline'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side: Preview (Below on mobile) -->
                            <div class="col-span-1 md:col-span-5 flex flex-col items-center pt-4">
                                <h4
                                    class="text-xs font-black text-gray-500 uppercase tracking-widest mb-8 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <?php echo __('mobile_preview'); ?>
                                </h4>

                                <!-- Phone UI Mockup -->
                                <div class="w-full max-w-[300px] bg-black rounded-[3.5rem] border-[12px] border-gray-800 shadow-3xl relative overflow-hidden flex flex-col scale-100 mb-8 transition-all duration-500"
                                    :class="(formData.post_type === 'story' || formData.post_type === 'reel') ? 'aspect-[9/16] h-[550px]' : 'h-[600px]'">
                                    <!-- Top Notch -->
                                    <div class="h-8 bg-black w-full flex justify-center items-end pb-1.5 pt-4">
                                        <div class="w-24 h-5 bg-gray-900 rounded-full border border-white/5"></div>
                                    </div>

                                    <!-- Content Area -->
                                    <div class="flex-1 bg-[#18191a] overflow-hidden flex flex-col">
                                        <!-- Post Header -->
                                        <div class="p-4 flex items-center gap-3 border-b border-white/5">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-black border border-white/10 shadow-lg">
                                                <span x-text="getPageName() ? getPageName().charAt(0) : 'F'"></span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[12px] font-black text-white truncate"
                                                    x-text="getPageName() || 'Facebook Page'"></div>
                                                <div class="flex items-center gap-1.5">
                                                    <div class="text-[10px] text-gray-500">Just now</div>
                                                    <div class="w-1 h-1 rounded-full bg-gray-600"></div>
                                                    <svg class="w-2.5 h-2.5 text-gray-600" fill="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path
                                                            d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Post Content Wrapper -->
                                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                                            <div class="p-4 text-xs text-white whitespace-pre-line leading-relaxed break-words"
                                                x-text="formData.content || '<?php echo __('post_content_placeholder'); ?>'">
                                            </div>

                                            <div class="relative bg-gray-900 overflow-hidden border-y border-white/5"
                                                :class="(formData.post_type === 'story' || formData.post_type === 'reel') ? 'aspect-[9/16]' : 'aspect-video'"
                                                x-show="formData.media_url">
                                                <template x-if="isVideoUrl(formData.media_url)">
                                                    <div class="relative w-full h-full">
                                                        <video :src="formData.media_url"
                                                            class="absolute inset-0 w-full h-full object-cover"
                                                            muted></video>
                                                        <div
                                                            class="absolute inset-0 flex items-center justify-center bg-black/20">
                                                            <svg class="w-12 h-12 text-white opacity-70"
                                                                fill="currentColor" viewBox="0 0 20 20">
                                                                <path
                                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z">
                                                                </path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!isVideoUrl(formData.media_url)">
                                                    <img :src="formData.media_url"
                                                        class="absolute inset-0 w-full h-full object-cover">
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Post Reactions -->
                                        <div
                                            class="p-4 flex items-center justify-between border-t border-white/5 mt-auto bg-black/20">
                                            <div class="flex items-center gap-2 text-[10px] text-gray-400 font-black">
                                                Like</div>
                                            <div class="text-[10px] text-gray-400 font-black">Comment</div>
                                            <div class="text-[10px] text-gray-400 font-black">Share</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Final Action Button & Progress -->
                        <div class="mt-12 sticky bottom-0 bg-gray-900/80 backdrop-blur-md p-4 md:p-0 z-20">
                            <!-- Progress Bar -->
                            <div x-show="uploading || processing" x-transition.opacity class="mb-4">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs font-bold text-indigo-400" x-text="progressText()"></span>
                                    <span class="text-xs font-bold text-white" x-text="uploadPercent + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-2.5 overflow-hidden">
                                    <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300 relative overflow-hidden"
                                        :style="'width: ' + uploadPercent + '%'">
                                        <div class="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite]"></div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" :disabled="loading"
                                class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed text-white py-5 rounded-[1.5rem] font-black text-lg shadow-2xl shadow-indigo-600/40 transition-all flex items-center justify-center gap-3">
                                <template x-if="loading">
                                    <svg class="animate-spin h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </template>
                                <span
                                    x-text="loading ? '<?php echo __('processing_activity'); ?>...' : (editId ? '<?php echo __('update'); ?>' : '<?php echo __('schedule_post_btn'); ?>')"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    function postScheduler() {
        return {
            showModal: false,
            loading: false,
            uploading: false,
            processing: false,
            uploadPercent: 0,
            mediaMode: 'url',
            filesSelected: [], // Array of file objects with previews
            debugInfo: null,
            editId: null,
            formData: {
                page_id: '',
                post_type: 'feed',
                content: '',
                scheduled_at: '',
                media_url: ''
            },
            pages: <?php echo json_encode($pages); ?>,

            getPageName() {
                const page = this.pages.find(p => p.page_id == this.formData.page_id);
                return page ? page.page_name : '';
            },

            isVideoUrl(url) {
                if (!url) return false;
                const videoExtensions = ['.mp4', '.mov', '.avi', '.webm', '.mkv'];
                return videoExtensions.some(ext => url.toLowerCase().includes(ext));
            },

            progressText() {
                if (this.uploading && this.uploadPercent < 100) return 'Uploading Media...';
                if (this.uploadPercent >= 100 || this.processing) return 'Processing with Facebook...';
                return '';
            },

            async fetchTokenDebug() {
                if (!this.formData.page_id) {
                    this.debugInfo = null;
                    return;
                }
                try {
                    const res = await fetch('ajax_auto_reply.php?action=debug_token_info&page_id=' + this.formData.page_id);
                    const data = await res.json();
                    if (data.success) {
                        this.debugInfo = data;
                    } else {
                        this.debugInfo = null;
                    }
                } catch (e) {
                    this.debugInfo = null;
                }
            },

            openCreateModal() {
                if (!this.formData.page_id) {
                    alert('<?php echo __('select_page_first'); ?>');
                    return;
                }
                this.editId = null;
                this.formData.post_type = 'feed';
                this.formData.content = '';
                this.formData.scheduled_at = '';
                this.formData.media_url = '';
                this.filesSelected = [];
                this.mediaMode = 'url';
                this.uploadPercent = 0;
                this.showModal = true;
            },

            editPost(post) {
                this.editId = post.id;
                this.formData.page_id = post.page_id;
                this.formData.post_type = post.post_type || 'feed';
                this.formData.content = post.content;
                // Format date for datetime-local (YYYY-MM-DDThh:mm)
                const d = new Date(post.scheduled_at);
                const offset = d.getTimezoneOffset() * 60000;
                const localISODate = (new Date(d.getTime() - offset)).toISOString().slice(0, 16);
                this.formData.scheduled_at = localISODate;

                // Handle media
                this.filesSelected = [];
                if (post.media_url) {
                    this.formData.media_url = post.media_url;
                    this.mediaMode = 'url';
                } else {
                    this.formData.media_url = '';
                }

                this.showModal = true;
                this.fetchTokenDebug();
            },

            handleFileUpload(e) {
                const files = Array.from(e.target.files);
                if (files.length === 0) return;

                files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        file.preview = ev.target.result;
                        this.filesSelected.push(file);

                        // Set first image as main preview if empty
                        if (!this.formData.media_url) {
                            this.formData.media_url = file.preview;
                        }
                    };
                    reader.readAsDataURL(file);
                });

                e.target.value = '';
            },

            removeFile(index) {
                this.filesSelected.splice(index, 1);
                // Update main preview if needed
                if (this.filesSelected.length > 0) {
                    this.formData.media_url = this.filesSelected[0].preview;
                } else {
                    this.formData.media_url = '';
                }
            },

            async submitPost() {
                // Validation logic based on post type
                const isFeed = this.formData.post_type === 'feed';
                const isStory = this.formData.post_type === 'story';
                const isReel = this.formData.post_type === 'reel';

                const hasPage = !!this.formData.page_id;
                const hasContent = !!this.formData.content;
                const hasTime = !!this.formData.scheduled_at;
                const hasMedia = !!this.formData.media_url || this.filesSelected.length > 0;

                let isValid = false;
                if (isFeed) {
                    isValid = hasPage && hasContent && hasTime;
                } else if (isStory) {
                    isValid = hasPage && hasTime && hasMedia;
                } else if (isReel) {
                    isValid = hasPage && hasTime && hasMedia && hasContent;
                }

                if (!isValid) {
                    alert('<?php echo __('fill_all_fields'); ?>');
                    return;
                }

                // Extra check for Reel/Story - Only single file allowed currently
                if ((isStory || isReel) && this.filesSelected.length > 1) {
                    alert('Stories and Reels currently support only 1 file.');
                    return;
                }

                const scheduledDate = new Date(this.formData.scheduled_at);
                const now = new Date();
                const minDate = new Date(now.getTime() + 10 * 60 * 1000);
                const maxDate = new Date(now.getTime() + 75 * 24 * 60 * 60 * 1000);

                if (scheduledDate < minDate || scheduledDate > maxDate) {
                    alert('<?php echo __('schedule_guideline'); ?>');
                    return;
                }

                this.loading = true;
                this.uploading = true;
                this.processing = false;
                this.uploadPercent = 0;

                const sendData = new FormData();
                sendData.append('page_id', this.formData.page_id);
                sendData.append('post_type', this.formData.post_type);
                sendData.append('content', this.formData.content);
                sendData.append('scheduled_at', this.formData.scheduled_at);

                if (this.editId) {
                    sendData.append('id', this.editId);
                    sendData.append('action', 'edit');
                }

                if (this.mediaMode === 'url') {
                    sendData.append('media_url', this.formData.media_url);
                } else if (this.filesSelected.length > 0) {
                    this.filesSelected.forEach(f => {
                        sendData.append('media_file[]', f);
                    });
                }

                // Use XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'ajax_scheduler.php', true);

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        this.uploadPercent = percentComplete;
                        if (percentComplete >= 100) {
                            this.processing = true;
                            this.uploading = false;
                        }
                    }
                });

                xhr.onload = () => {
                    this.loading = false;
                    this.uploading = false;
                    this.processing = false;

                    if (xhr.status === 200) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.status === 'success') {
                                alert('<?php echo __('schedule_success'); ?>');
                                window.location.reload();
                            } else {
                                alert(result.message || 'Error occurred');
                            }
                        } catch (e) {
                            console.error('JSON Error:', xhr.responseText);
                            alert('Server Error: Invalid Response');
                        }
                    } else {
                        alert('HTTP Error: ' + xhr.status);
                    }
                };

                xhr.onerror = () => {
                    this.loading = false;
                    this.uploading = false;
                    this.processing = false;
                    alert('Network Connection Error');
                };

                xhr.send(sendData);
            },

            async deletePost(id) {
                if (!confirm('<?php echo __('confirm_delete'); ?>')) return;
                try {
                    const response = await fetch('ajax_scheduler.php?action=delete&id=' + id);
                    const result = await response.json();
                    if (result.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    alert('Delete failed');
                }
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>