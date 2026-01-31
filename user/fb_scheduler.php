<?php
// user/fb_scheduler.php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$current_user = $_SESSION['user_id'];
$pdo = getDB();

// Fetch Pages - Robust query to prevent duplicates while ensuring all user pages are included
$stmt = $pdo->prepare("SELECT * FROM fb_pages WHERE id IN (
    SELECT MIN(p.id) 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? 
    GROUP BY p.page_id
) ORDER BY page_name ASC");
$stmt->execute([$current_user]);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing scheduled posts
$stmt = $pdo->prepare("SELECT s.*, p.page_name 
FROM fb_scheduled_posts s 
LEFT JOIN (SELECT page_id, page_name FROM fb_pages GROUP BY page_id) p ON s.page_id = p.page_id 
WHERE s.user_id = ? 
ORDER BY s.scheduled_at DESC");
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
    // Scroll lock removed to allow scrolling to the inline editor
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
                            <select x-model="formData.page_id"
                                @change="localStorage.setItem('scheduler_last_page', formData.page_id); fetchTokenDebug();"
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

                        <!-- Top Actions -->
                        <div class="flex items-center gap-3">
                            <button @click="openCreateModal()"
                                class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-2xl font-bold border border-indigo-500/20 shadow-lg shadow-indigo-500/20 transition-all transform hover:scale-105 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                <span><?php echo __('new_post_btn'); ?></span>
                            </button>
                        </div>



                    </div>
                </div>
            </div>



            <!-- Create Post Modal -->
            <!-- Create Post Form (Inline) -->
            <div
                class="glass-panel w-full bg-gray-900 border border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden relative z-10 flex flex-col mb-12">

                <!-- Header -->
                <div class="p-6 md:p-8 flex justify-between items-center border-b border-white/5">
                    <h3 class="text-2xl font-bold text-white"><?php echo __('create_post'); ?></h3>
                </div>

                <!-- Main Area -->
                <div class="flex-1">
                    <form id="scheduler-form" @submit.prevent="submitPost" class="flex flex-col min-h-full">
                        <div class="flex-1 p-6 md:p-8 w-full max-w-5xl mx-auto">
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
                                                    x-text="getPageName() || '<?php echo __('select_page_first'); ?>'">
                                                </h4>
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
                                            <span
                                                x-show="formData.post_type === 'story' || formData.post_type === 'reel'"
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
                                            <input type="text" x-model="formData.media_url"
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
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                </template>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="text-xs text-gray-300 font-bold truncate"
                                                                    x-text="file.name"></p>
                                                                <p class="text-[10px] text-gray-500"
                                                                    x-text="(file.size/1024/1024).toFixed(2) + ' MB'">
                                                                </p>
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
                                            <div class="w-24 h-5 bg-gray-900 rounded-full border border-white/5">
                                            </div>
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
                                                        x-text="getPageName() || '<?php echo __('site_name'); ?>'">
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="text-[10px] text-gray-500">
                                                            <?php echo __('just_now'); ?>
                                                        </div>
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

                                                <div class="relative bg-black w-full overflow-hidden flex items-center justify-center border-y border-white/5"
                                                    :class="(formData.post_type === 'story' || formData.post_type === 'reel') ? 'aspect-[9/16]' : 'aspect-square'"
                                                    x-show="formData.media_url || filesSelected.length > 0">

                                                    <!-- Single Media (Legacy or Single Select) -->
                                                    <template
                                                        x-if="(mediaMode === 'url' && formData.media_url && !formData.media_url.startsWith('[')) || (mediaMode === 'file' && filesSelected.length === 1)">
                                                        <div class="w-full h-full">
                                                            <template
                                                                x-if="isVideoUrl(formData.media_url || (filesSelected[0] ? filesSelected[0].preview : ''))">
                                                                <video
                                                                    :src="(formData.media_url || (filesSelected[0] ? filesSelected[0].preview : '')) + '#t=0.1'"
                                                                    class="w-full h-full object-cover" muted></video>
                                                            </template>
                                                            <template
                                                                x-if="!isVideoUrl(formData.media_url || (filesSelected[0] ? filesSelected[0].preview : ''))">
                                                                <img :src="formData.media_url || (filesSelected[0] ? filesSelected[0].preview : '')"
                                                                    class="w-full h-full object-cover">
                                                            </template>
                                                        </div>
                                                    </template>

                                                    <!-- Multi Media Grid Preview -->
                                                    <template
                                                        x-if="(mediaMode === 'url' && formData.media_url && formData.media_url.startsWith('[')) || (mediaMode === 'file' && filesSelected.length > 1)">
                                                        <div class="grid w-full h-full gap-0.5"
                                                            :class=" (mediaMode === 'file' ? filesSelected.length : getMediaList(formData.media_url).length) > 2 ? 'grid-cols-2' : 'grid-cols-1'">
                                                            <template
                                                                x-for="(m, i) in (mediaMode === 'file' ? filesSelected : getMediaList(formData.media_url).map(u => ({preview: u})))"
                                                                :key="i">
                                                                <div
                                                                    class="relative h-full w-full bg-gray-900 border border-white/5 overflow-hidden">
                                                                    <template x-if="isVideoUrl(m.preview)">
                                                                        <video :src="m.preview"
                                                                            class="w-full h-full object-cover"></video>
                                                                    </template>
                                                                    <template x-if="!isVideoUrl(m.preview)">
                                                                        <img :src="m.preview"
                                                                            class="w-full h-full object-cover">
                                                                    </template>
                                                                    <!-- Overlap count for many images -->
                                                                    <template
                                                                        x-if="i === 3 && (mediaMode === 'file' ? filesSelected.length : getMediaList(formData.media_url).length) > 4">
                                                                        <div
                                                                            class="absolute inset-0 bg-black/60 flex items-center justify-center text-white font-bold text-xl">
                                                                            +<span
                                                                                x-text="(mediaMode === 'file' ? filesSelected.length : getMediaList(formData.media_url).length) - 4"></span>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Post Reactions -->
                                            <div
                                                class="p-4 flex items-center justify-between border-t border-white/5 mt-auto bg-black/20">
                                                <div
                                                    class="flex items-center gap-2 text-[10px] text-gray-400 font-black">
                                                    <?php echo __('fb_like'); ?>
                                                </div>
                                                <div class="text-[10px] text-gray-400 font-black">
                                                    <?php echo __('fb_comment'); ?>
                                                </div>
                                                <div class="text-[10px] text-gray-400 font-black">
                                                    <?php echo __('fb_share'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
                </form>
            </div>

            <!-- Final Action Button & Progress (Fixed Footer) -->
            <div class="flex-shrink-0 bg-gray-900/95 backdrop-blur-xl border-t border-white/5 z-20">
                <div class="w-full max-w-5xl mx-auto p-4 md:px-8">


                    <div class="flex gap-3">
                        <button type="button" x-show="uploading" @click="cancelUpload()"
                            class="w-1/3 bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 py-5 rounded-[1.5rem] font-bold text-sm transition-all">
                            <?php echo __('cancel'); ?>
                        </button>

                        <button type="submit" form="scheduler-form" :disabled="loading"
                            class="flex-1 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed text-white py-5 rounded-[1.5rem] font-black text-lg shadow-2xl shadow-indigo-600/40 transition-all flex items-center justify-center gap-3">
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
                </div>
            </div>
        </div>


        <!-- List of Scheduled Posts -->
        <div id="scheduled-posts-list"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-12 pt-12 border-t border-white/5">
            <div class="col-span-full mb-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-2xl font-bold text-white"><?php echo __('scheduled_posts'); ?></h3>
                    <p class="text-gray-400 text-sm"><?php echo __('scheduled_posts_desc'); ?></p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Selection Actions -->
                    <template x-if="selectionMode">
                        <div
                            class="flex items-center gap-2 bg-indigo-500/10 p-1 rounded-2xl border border-indigo-500/20">
                            <button @click="confirmBulkDelete()" :disabled="selectedPosts.length === 0"
                                class="px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl font-bold transition-all text-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span
                                    x-text="'<?php echo __('delete_selected'); ?> (' + selectedPosts.length + ')'"></span>
                            </button>
                            <button @click="exitSelectionMode()"
                                class="px-3 py-2 text-gray-400 hover:text-white transition-colors text-sm font-bold">
                                <?php echo __('exit_selection'); ?>
                            </button>
                        </div>
                    </template>

                    <!-- Main Actions -->
                    <template x-if="!selectionMode">
                        <div class="flex items-center gap-2">
                            <button @click="syncAll()" x-show="formData.page_id" x-cloak
                                class="px-5 py-3 bg-white/5 hover:bg-white/10 text-white rounded-2xl font-bold transition-all border border-white/10 flex items-center gap-2 group"
                                :disabled="syncingAll" :class="{'opacity-50 cursor-not-allowed': syncingAll}">
                                <svg class="w-4 h-4 transition-transform"
                                    :class="syncingAll ? 'animate-spin' : 'group-hover:rotate-180'" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span
                                    x-text="syncingAll ? '<?php echo __('syncing'); ?>...' : '<?php echo __('sync_all'); ?>'"></span>
                            </button>

                            <button @click="confirmDeleteAll()"
                                x-show="formData.page_id && scheduledPosts.filter(p => p.page_id == formData.page_id).length > 0"
                                x-cloak
                                class="px-5 py-3 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-2xl font-bold transition-all border border-red-500/20 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span><?php echo __('delete_all'); ?></span>
                            </button>

                            <button @click="enterSelectionMode()"
                                x-show="formData.page_id && scheduledPosts.filter(p => p.page_id == formData.page_id).length > 0"
                                x-cloak
                                class="p-3 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-2xl transition-all border border-white/10"
                                title="<?php echo __('selection_mode'); ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

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
            <!-- Uploading/Temporary Post Cards - Always Visible if Active -->
            <template x-for="(upload, index) in activeUploads" :key="upload.id">
                <div x-show="true"
                    class="glass-card p-5 rounded-3xl border border-indigo-500/30 bg-indigo-500/5 group relative mb-6">

                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 animate-pulse">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-bold text-white truncate max-w-[100px]"
                                        x-text="upload.pageName"></h4>
                                </div>
                                <p class="text-[10px] text-indigo-400"
                                    :class="{'animate-pulse': upload.status === 'uploading'}">
                                    <span
                                        x-text="upload.status === 'success' ? '<?php echo __('completed_status'); ?>' : (upload.status === 'error' ? '<?php echo __('failed_status'); ?>' : '<?php echo __('uploading_status'); ?>')"></span>
                                    <span x-show="upload.status === 'uploading'" x-text="upload.progress + '%'"></span>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                x-text="upload.status === 'success' ? '<?php echo __('success_status'); ?>' : (upload.status === 'error' ? '<?php echo __('error_status'); ?>' : '<?php echo __('pending_status'); ?>')"></span>
                            </span>
                            <!-- Abort Button -->
                            <button x-show="upload.status === 'uploading'" @click="abortUpload(upload.id)"
                                class="p-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition-all border border-red-500/20 group/btn"
                                title="إيقاف الرفع">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <p class="text-sm text-gray-300 line-clamp-3 mb-4 h-15" x-text="upload.content || '...'">
                    </p>

                    <template x-if="upload.previews && upload.previews.length > 0">
                        <div
                            class="relative aspect-video rounded-xl overflow-hidden bg-black/40 mb-4 border border-white/5 opacity-50">
                            <!-- Multi Media Grid for Upload Task -->
                            <div class="grid w-full h-full gap-0.5"
                                :class="upload.previews.length > 2 ? 'grid-cols-2' : 'grid-cols-1'">
                                <template x-for="(m, i) in upload.previews.slice(0, 4)" :key="i">
                                    <div class="relative h-full w-full bg-gray-900 overflow-hidden">
                                        <template x-if="isVideoUrl(m)">
                                            <video :src="m" class="w-full h-full object-cover" preload="metadata"
                                                playsinline muted onloadedmetadata="this.currentTime=0.1"></video>
                                        </template>
                                        <template x-if="!isVideoUrl(m)">
                                            <img :src="m" class="w-full h-full object-cover">
                                        </template>

                                        <!-- Overlay for more images -->
                                        <template x-if="i === 3 && upload.previews.length > 4">
                                            <div
                                                class="absolute inset-0 bg-black/60 flex items-center justify-center text-white font-bold text-lg">
                                                +<span x-text="upload.previews.length - 4"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                x-show="upload.status === 'uploading'">
                                <svg class="animate-spin w-8 h-8 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                    </template>

                    <template x-if="!upload.preview">
                        <div
                            class="aspect-video rounded-xl mb-4 border border-white/5 bg-[#18191a] flex flex-col overflow-hidden relative shadow-inner opacity-50">
                            <!-- Mini Header -->
                            <div class="p-3 flex items-center gap-2 border-b border-white/5 bg-black/20 text-left">
                                <div
                                    class="w-6 h-6 rounded-full bg-indigo-500/20 flex items-center justify-center text-[8px] text-white font-black border border-white/10">
                                    <span x-text="upload.pageName ? upload.pageName.charAt(0) : 'P'"></span>
                                </div>
                                <div class="flex-1 min-w-0 text-left">
                                    <div class="text-[8px] font-black text-white truncate" x-text="upload.pageName">
                                    </div>
                                    <div class="text-[6px] text-gray-500"><?php echo __('just_now'); ?></div>
                                </div>
                            </div>
                            <!-- Body -->
                            <div class="flex-1 p-3 flex flex-col justify-center text-center">
                                <p class="text-[9px] text-white/80 line-clamp-3 leading-relaxed italic"
                                    x-text="'“ ' + upload.content + ' ”'"></p>
                            </div>
                            <!-- Loader Overlay -->
                            <div class="absolute inset-0 flex items-center justify-center bg-black/40"
                                x-show="upload.status === 'uploading'">
                                <svg class="animate-spin w-6 h-6 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                    </template>

                    <!-- Progress Bar Overlay -->
                    <div class="w-full bg-gray-800 rounded-full h-1.5 overflow-hidden mt-2"
                        x-show="upload.status === 'uploading'">
                        <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-300"
                            :style="'width: ' + upload.progress + '%'"></div>
                    </div>

                    <!-- Background Info Notice -->
                    <div x-show="upload.progress >= 100 && upload.status === 'uploading'"
                        class="mt-2 p-2 bg-blue-500/10 border border-blue-500/20 rounded-xl text-[10px] text-blue-400 flex items-center gap-2">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"
                                stroke-linecap="round" />
                        </svg>
                        <span>تم الرفع للخادم. يمكنك مغادرة الصفحة الآن، العملية ستستمر في الخلفية.</span>
                    </div>

                    <div x-show="upload.status === 'success'" class="mt-2 text-center text-xs text-green-400">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <?php echo __('success_status'); ?>
                        </span>
                    </div>
                    <div x-show="upload.status === 'error'" class="mt-2 text-center text-xs text-red-400"
                        x-text="upload.error"></div>
                </div>
            </template>

            <template x-if="scheduledPosts.length === 0">
                <div x-show="formData.page_id !== '' && activeUploads.length === 0" x-cloak
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
            </template>

            <template x-for="post in scheduledPosts" :key="post.id">
                <div x-show="formData.page_id !== '' && formData.page_id === post.page_id" x-cloak
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100" :id="'post-card-' + post.id"
                    @click="selectionMode ? toggleSelect(post.id) : null"
                    :class="selectionMode && selectedPosts.includes(post.id) ? 'bg-indigo-500/10 border-indigo-500/40 ring-2 ring-indigo-500/20' : ''"
                    class="glass-card p-5 rounded-3xl border border-white/5 bg-white/5 hover:bg-white/10 transition-all group relative cursor-pointer">

                    <!-- Selection Overlay Checkbox -->
                    <template x-if="selectionMode">
                        <div class="absolute top-4 right-4 z-20">
                            <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all"
                                :class="selectedPosts.includes(post.id) ? 'bg-indigo-500 border-indigo-500' : 'bg-white/5 border-white/10'">
                                <svg x-show="selectedPosts.includes(post.id)" class="w-4 h-4 text-white" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-col sm:flex-row justify-between items-start mb-4 gap-4">
                        <div class="flex items-start gap-4 w-full sm:w-auto overflow-hidden">
                            <div
                                class="w-12 h-12 shrink-0 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 border border-indigo-500/20">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center flex-wrap gap-2 mb-1">
                                    <h4 class="text-base font-bold text-white truncate max-w-[150px] sm:max-w-[200px]"
                                        x-text="post.page_name || 'Page'"></h4>
                                    <template x-if="post.fb_post_id">
                                        <a :href="'https://facebook.com/' + post.fb_post_id" target="_blank"
                                            class="text-indigo-400 hover:text-indigo-300 transition-colors p-1"
                                            title="View on Facebook">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path
                                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </a>
                                    </template>
                                </div>
                                <p class="text-xs text-gray-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span x-text="formatDate(post.scheduled_at)"></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full sm:w-auto gap-3 sm:flex-col sm:items-end">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border"
                                :class="post.status === 'success' ? 'bg-green-500/10 text-green-400 border-green-500/20' :
                                        (post.status === 'pending' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20')"
                                x-text="getStatusLabel(post)"></span>

                            <div class="flex items-center gap-1 bg-white/5 rounded-lg p-1 border border-white/5">
                                <button @click="syncPost(post.id)"
                                    class="p-1.5 rounded-md text-gray-400 hover:text-indigo-400 hover:bg-white/5 transition-all"
                                    :class="syncingId == post.id ? 'animate-spin text-indigo-400' : ''"
                                    title="<?php echo __('refresh_status'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-300 line-clamp-3 mb-4 h-15" x-text="post.content"></p>

                    <template x-if="post.media_url">
                        <div
                            class="relative aspect-video rounded-xl overflow-hidden bg-black/40 mb-4 border border-white/5 group-hover:border-indigo-500/30 transition-all">
                            <!-- Multi Media Grid for Cards -->
                            <div class="grid w-full h-full gap-0.5"
                                :class="getMediaList(post.media_url).length > 2 ? 'grid-cols-2' : 'grid-cols-1'">
                                <template x-for="(m, i) in getMediaList(post.media_url).slice(0, 4)" :key="i">
                                    <div class="relative h-full w-full bg-gray-900 overflow-hidden">
                                        <template x-if="isVideoUrl(m)">
                                            <video :src="m" class="w-full h-full object-cover" preload="metadata"
                                                playsinline muted onloadedmetadata="this.currentTime=0.1"></video>
                                        </template>
                                        <template x-if="!isVideoUrl(m)">
                                            <img :src="m" class="w-full h-full object-cover">
                                        </template>

                                        <!-- Overlay for more images -->
                                        <template x-if="i === 3 && getMediaList(post.media_url).length > 4">
                                            <div
                                                class="absolute inset-0 bg-black/60 flex items-center justify-center text-white font-bold text-lg">
                                                +<span x-text="getMediaList(post.media_url).length - 4"></span>
                                            </div>
                                        </template>

                                        <!-- Video Icon Overlay -->
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20"
                                            x-show="isVideoUrl(m)">
                                            <svg class="w-8 h-8 text-white opacity-70" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" />
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="!post.media_url">
                        <div
                            class="aspect-video rounded-xl mb-4 border border-white/5 bg-[#18191a] flex flex-col overflow-hidden relative shadow-inner">
                            <!-- Mini FB Header -->
                            <div class="p-3 flex items-center gap-2 border-b border-white/5 bg-black/20 text-left">
                                <div
                                    class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-[10px] text-white font-black border border-white/10 shadow-lg">
                                    <span x-text="post.page_name ? post.page_name.charAt(0) : 'P'"></span>
                                </div>
                                <div class="flex-1 min-w-0 text-left">
                                    <div class="text-[9px] font-black text-white truncate"
                                        x-text="post.page_name || 'Facebook'"></div>
                                    <div class="flex items-center gap-1">
                                        <div class="text-[7px] text-gray-500"><?php echo __('just_now'); ?></div>
                                        <div class="w-0.5 h-0.5 rounded-full bg-gray-600"></div>
                                        <svg class="w-1.5 h-1.5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <!-- FB Post Body for Text -->
                            <div
                                class="flex-1 p-4 flex flex-col justify-center bg-gradient-to-b from-transparent to-black/5">
                                <p class="text-[11px] text-white/90 line-clamp-4 leading-relaxed text-center font-medium italic"
                                    x-text="'&quot;' + post.content + '&quot;'"></p>
                            </div>
                            <!-- Fake Reactions Line -->
                            <div
                                class="px-3 py-1.5 flex items-center justify-between border-t border-white/5 bg-black/10">
                                <div class="flex gap-2 text-[6px] text-gray-600 uppercase font-black tracking-tighter">
                                    <span>Like</span>
                                    <span>Comment</span>
                                </div>
                                <div class="text-[6px] text-gray-600 uppercase font-black">Share</div>
                            </div>
                        </div>
                    </template>

                    <div class="flex gap-2">
                        <button @click="confirmSmartDelete(post)"
                            class="flex-1 py-3 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-2xl text-xs font-bold transition-all border border-red-500/20 flex items-center justify-center gap-2 group">
                            <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <?php echo __('delete'); ?>
                        </button>
                        <template
                            x-if="post.status === 'pending' || post.status === 'failed' || post.status === 'success'">
                            <button @click="editPost(post)"
                                class="px-4 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl transition-all border border-white/5 group"
                                title="<?php echo __('edit'); ?>">
                                <svg class="w-4 h-4 group-hover:rotate-12 transition-transform" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>



        <!-- Smart Delete Modal -->
        <div x-show="showDeleteModal" x-cloak x-transition.opacity
            class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div
                class="glass-panel w-full max-w-md bg-gray-900 border border-white/10 rounded-[2.5rem] p-8 relative z-10 text-center">
                <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2"
                    x-text="isBulkDelete ? '<?php echo __('bulk_delete_title'); ?>' : (postToDelete?.status === 'success' ? 'هل تريد حذف المنشور من فيسبوك أيضاً؟' : 'تأكيد الحذف')">
                </h3>
                <p class="text-gray-400 text-sm mb-8 leading-relaxed"
                    x-text="isBulkDelete ? '<?php echo __('bulk_delete_desc'); ?>'.replace('%d', postsToBulkDelete.length) : 'هذا الإجراء سيقوم بإزالة المنشور من لوحة التحكم الخاصة بك.'">
                </p>

                <div class="space-y-3">
                    <!-- Confirm Bulk/All Delete -->
                    <template x-if="isBulkDelete">
                        <div class="space-y-3">
                            <button @click="executeBulkDelete(true)"
                                class="w-full py-4 bg-red-600 hover:bg-red-700 text-white rounded-2xl font-bold transition-all flex items-center justify-center gap-2">
                                <span>حذف من هنا ومن فيسبوك</span>
                            </button>
                            <button @click="executeBulkDelete(false)"
                                class="w-full py-4 bg-white/5 hover:bg-white/10 text-white rounded-2xl font-bold transition-all">
                                حذف من هنا فقط
                            </button>
                        </div>
                    </template>

                    <!-- Confirm Single Delete -->
                    <template x-if="!isBulkDelete">
                        <div class="space-y-3">
                            <template x-if="postToDelete?.fb_post_id">
                                <button @click="executeDelete(true)"
                                    class="w-full py-4 bg-red-600 hover:bg-red-700 text-white rounded-2xl font-bold transition-all flex items-center justify-center gap-2">
                                    <span>حذف من هنا ومن فيسبوك</span>
                                </button>
                            </template>
                            <button @click="executeDelete(false)"
                                class="w-full py-4 bg-white/5 hover:bg-white/10 text-white rounded-2xl font-bold transition-all">
                                حذف من هنا فقط
                            </button>
                        </div>
                    </template>

                    <button @click="showDeleteModal = false; isBulkDelete = false;"
                        class="w-full py-4 text-gray-500 hover:text-white transition-colors">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
</div>
</main>
</div>

<script>
    function postScheduler() {
        return {
            showModal: false,
            showDeleteModal: false,
            activeUploads: [],
            temporaryPost: null, // Legacy check
            syncingId: null,
            postToDelete: null,
            loading: false,
            uploading: false,
            processing: false, // Legacy
            uploadPercent: 0, // Legacy
            mediaMode: 'url',
            filesSelected: [], // Array of file objects with previews
            activeXhr: null,
            uploadStats: null,
            scheduledPosts: <?php echo json_encode($scheduled_posts); ?>,
            i18n: {
                scheduled: '<?php echo __('scheduled_status'); ?>',
                published: '<?php echo __('published_status'); ?>',
                pending: '<?php echo __('pending_status'); ?>',
                failed: '<?php echo __('failed_status'); ?>'
            },
            syncingAll: false,
            selectionMode: false,
            selectedPosts: [], // Array of IDs
            isBulkDelete: false,
            postsToBulkDelete: [],

            init() {
                if (this.formData.page_id) {
                    this.fetchTokenDebug();
                }
            },

            cancelUpload() {
                if (this.activeXhr) {
                    this.activeXhr.abort();
                    this.activeXhr = null;
                }
                this.loading = false;
                this.uploading = false;
                this.processing = false;
                this.uploadPercent = 0;
                this.uploadStats = null;
            },

            async refreshPostList() {
                try {
                    const res = await fetch('ajax_scheduler.php?action=list');
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.scheduledPosts = data.posts;
                    }
                } catch (e) {
                    console.error('Failed to refresh posts', e);
                }
            },

            async syncPost(id) {
                this.syncingId = id;
                try {
                    const response = await fetch('ajax_scheduler.php?action=sync&id=' + id);
                    const result = await response.json();
                    if (result.status === 'success') {
                        // Update state without reload
                        await this.refreshPostList();
                        return true;
                    } else {
                        console.error(result.message);
                        return false;
                    }
                } catch (e) {
                    console.error('Sync failed', e);
                    return false;
                } finally {
                    this.syncingId = null;
                }
            },

            async syncAll() {
                if (!this.formData.page_id || this.syncingAll) return;

                const postsToSync = this.scheduledPosts.filter(p => p.page_id == this.formData.page_id && p.fb_post_id);
                if (postsToSync.length === 0) return;

                this.syncingAll = true;
                try {
                    for (const post of postsToSync) {
                        await this.syncPost(post.id);
                        // Small delay to prevent rate limit
                        await new Promise(r => setTimeout(r, 300));
                    }
                } finally {
                    this.syncingAll = false;
                    await this.refreshPostList();
                }
            },

            getStatusLabel(post) {
                const isFuture = new Date(post.scheduled_at) > new Date();
                if (post.status === 'success' && isFuture) return this.i18n.scheduled;
                if (post.status === 'success') return this.i18n.published;
                if (post.status === 'pending') return this.i18n.pending;
                return this.i18n.failed;
            },

            formatDate(str) {
                if (!str) return '';
                const d = new Date(str);
                return d.toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            },

            getFirstMedia(url) {
                if (!url) return '';
                const list = this.getMediaList(url);
                return list.length > 0 ? list[0] : '';
            },

            getMediaList(url) {
                if (!url) return [];
                if (typeof url !== 'string') return [url];
                if (url.startsWith('[')) {
                    try {
                        const parsed = JSON.parse(url);
                        return Array.isArray(parsed) ? parsed : [url];
                    } catch (e) { return [url]; }
                }
                return [url];
            },



            debugInfo: null,
            editId: null,
            formData: {
                page_id: localStorage.getItem('scheduler_last_page') || '',
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
                if (typeof url !== 'string') return false;

                // Check if it's a blob url from local selection
                if (url.startsWith('blob:')) {
                    const file = this.filesSelected.find(f => f.preview === url);
                    if (file) {
                        return file.type.startsWith('video/');
                    }
                }

                // If it's a Facebook CDN URL, it's very likely a thumbnail (image) unless it explicitly has video traits
                if (url.includes('fbcdn.net')) {
                    // FB thumbnails often have /v/t... but they are images. 
                    // Direct video files usually have different signatures or .mp4
                    if (url.toLowerCase().includes('.mp4')) return true;
                    // If it's a FB URL and doesn't explicitly end with common video formats, assume it's an image/thumbnail
                    return false;
                }

                const videoExtensions = ['.mp4', '.mov', '.avi', '.webm', '.mkv', '.m4v'];
                return videoExtensions.some(ext => url.toLowerCase().includes(ext));
            },

            progressText() {
                if (this.uploading && this.uploadPercent < 100) return '<?php echo __('uploading_media'); ?> ' + this.uploadPercent + '%';
                if (this.uploadPercent >= 100 || this.processing) return '<?php echo __('processing_facebook'); ?>';
                return '';
            },

            formatBytes(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            },

            formatTime(seconds) {
                if (!isFinite(seconds) || seconds < 0) return '...';
                if (seconds < 60) return Math.round(seconds) + 's';
                const m = Math.floor(seconds / 60);
                const s = Math.round(seconds % 60);
                return `${m}m ${s}s`;
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
                if (this.uploading) {
                    this.showModal = true;
                    return;
                }
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
                window.scrollTo({ top: 0, behavior: 'smooth' });
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
                    // Check if media_url is a JSON string (for multiple images)
                    try {
                        const decodedMedia = JSON.parse(post.media_url);
                        if (Array.isArray(decodedMedia) && decodedMedia.length > 0) {
                            // If it's an array, use the first item for preview and set mediaMode to 'url'
                            this.formData.media_url = decodedMedia[0];
                            this.mediaMode = 'url';
                            // For editing, we don't load all previous files into filesSelected for 'file' mode
                            // as they are already uploaded. We just display the first one as a preview.
                        } else {
                            // Not an array, treat as single URL
                            this.formData.media_url = post.media_url;
                            this.mediaMode = 'url';
                        }
                    } catch (e) {
                        // Not a JSON string, treat as single URL
                        this.formData.media_url = post.media_url;
                        this.mediaMode = 'url';
                    }
                } else {
                    this.formData.media_url = '';
                }

                this.showModal = true;
                this.fetchTokenDebug();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            handleFileUpload(e) {
                const files = Array.from(e.target.files);
                if (files.length === 0) return;

                files.forEach(file => {
                    // Use createObjectURL instead of FileReader for performance with large files
                    file.preview = URL.createObjectURL(file);
                    this.filesSelected.push(file);

                    // Set first image/video as main preview if empty
                    if (!this.formData.media_url) {
                        this.formData.media_url = file.preview;
                    }
                });

                e.target.value = '';
            },

            removeFile(index) {
                // Free memory
                if (this.filesSelected[index].preview && this.filesSelected[index].preview.startsWith('blob:')) {
                    URL.revokeObjectURL(this.filesSelected[index].preview);
                }

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
                    alert('<?php echo __('stories_reels_single_file'); ?>');
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

                // Create Upload Task
                const uploadId = Date.now();
                const uploadTask = {
                    id: uploadId,
                    pageName: this.getPageName(),
                    content: this.formData.content,
                    preview: null,
                    progress: 0,
                    status: 'uploading',
                    error: null,
                    xhr: null
                };

                // Store files locally before resetting logic
                let filesToUpload = [];
                if (this.mediaMode === 'file' && this.filesSelected.length > 0) {
                    filesToUpload = [...this.filesSelected];
                    // Capture all previews for the task
                    uploadTask.previews = filesToUpload.map(f => f.preview);
                    uploadTask.preview = uploadTask.previews[0]; // Legacy fallback
                } else if (this.mediaMode === 'url' && this.formData.media_url) {
                    uploadTask.previews = [this.formData.media_url];
                    uploadTask.preview = this.formData.media_url;
                }

                // Add to active uploads
                this.activeUploads.unshift(uploadTask);

                // Start Scroll
                setTimeout(() => {
                    const list = document.getElementById('scheduled-posts-list');
                    if (list) list.scrollIntoView({ behavior: 'smooth' });
                }, 100);

                // Construct FormData
                const sendData = new FormData();
                sendData.append('page_id', this.formData.page_id);
                sendData.append('post_type', this.formData.post_type);
                sendData.append('content', this.formData.content);
                sendData.append('scheduled_at', this.formData.scheduled_at);

                if (this.editId) {
                    sendData.append('id', this.editId);
                    sendData.append('action', 'edit');
                }

                // Flag to tell backend we expect media
                const isMediaExpected = (this.mediaMode === 'url' && this.formData.media_url) || (this.mediaMode === 'file' && filesToUpload.length > 0);
                sendData.append('media_expected', isMediaExpected ? 'true' : 'false');

                if (this.mediaMode === 'url') {
                    sendData.append('media_url', this.formData.media_url);
                } else if (filesToUpload.length > 0) {
                    filesToUpload.forEach(f => {
                        // f is the File object (which has extra props attached, but FormData handles File objects correctly)
                        sendData.append('media_files[]', f);
                    });
                }

                this.showModal = false; // Close modal immediately
                // RESET FORM IMMEDIATELY
                this.formData.content = '';
                this.formData.media_url = '';
                this.filesSelected = [];
                this.editId = null; // IMPORTANT: Reset edit mode so next upload is new
                // Keep page_id and post_type for convenience
                // Do NOT reset loading/uploading global flags as we are now async multi-upload

                // Use XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                // We don't track activeXhr globally anymore to allow valid parallel uploads

                xhr.open('POST', 'ajax_scheduler.php', true);

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        // Update specific task
                        const task = this.activeUploads.find(u => u.id === uploadId);
                        if (task) task.progress = percentComplete;
                    }
                });

                xhr.onload = () => {
                    const task = this.activeUploads.find(u => u.id === uploadId);
                    if (!task) return;

                    if (xhr.status === 200) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.status === 'success') {
                                task.status = 'success';
                                task.progress = 100;
                                // Refresh list immediately
                                this.refreshPostList();
                                // Remove task from active uploads after animation
                                setTimeout(() => {
                                    this.activeUploads = this.activeUploads.filter(u => u.id !== uploadId);
                                }, 3000);
                            } else {
                                task.status = 'error';
                                task.error = result.message || '<?php echo __('error_status'); ?>';
                            }
                        } catch (e) {
                            task.status = 'error';
                            task.error = '<?php echo __('json_error'); ?>';
                        }
                    } else {
                        task.status = 'error';
                        task.error = '<?php echo __('http_error'); ?>' + xhr.status;
                    }
                };

                xhr.onerror = () => {
                    const task = this.activeUploads.find(u => u.id === uploadId);
                    if (task) {
                        task.status = 'error';
                        task.error = '<?php echo __('network_error'); ?>';
                    }
                };


                xhr.send(sendData);
                uploadTask.xhr = xhr;
            },

            abortUpload(uploadId) {
                const task = this.activeUploads.find(u => u.id === uploadId);
                if (task) {
                    if (task.xhr) {
                        task.xhr.abort();
                    }
                    this.activeUploads = this.activeUploads.filter(u => u.id !== uploadId);
                }
            },

            async syncPost(id) {
                this.syncingId = id;
                try {
                    const response = await fetch('ajax_scheduler.php?action=sync&id=' + id);
                    const result = await response.json();
                    if (result.status === 'success') {
                        // Update state without reload
                        await this.refreshPostList();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    alert('Sync failed');
                } finally {
                    this.syncingId = null;
                }
            },

            confirmSmartDelete(post) {
                this.postToDelete = post;
                this.showDeleteModal = true;
            },

            async executeDelete(fromFb) {
                if (!this.postToDelete) return;

                this.showDeleteModal = false;
                try {
                    const response = await fetch(`ajax_scheduler.php?action=delete&id=${this.postToDelete.id}&from_fb=${fromFb ? 1 : 0}`);
                    const result = await response.json();
                    if (result.status === 'success') {
                        // Refresh list without reload
                        await this.refreshPostList();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    alert('Delete failed');
                } finally {
                    this.postToDelete = null;
                }
            },

            async deletePost(id) {
                // Keep for legacy if needed, but we use confirmSmartDelete now
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
            },

            // --- Bulk Actions ---
            enterSelectionMode() {
                this.selectionMode = true;
                this.selectedPosts = [];
            },
            exitSelectionMode() {
                this.selectionMode = false;
                this.selectedPosts = [];
            },
            toggleSelect(id) {
                if (this.selectedPosts.includes(id)) {
                    this.selectedPosts = this.selectedPosts.filter(i => i !== id);
                } else {
                    this.selectedPosts.push(id);
                }
            },
            confirmBulkDelete() {
                if (this.selectedPosts.length === 0) return;
                this.postsToBulkDelete = this.selectedPosts;
                this.isBulkDelete = true;
                this.showDeleteModal = true;
            },
            confirmDeleteAll() {
                const posts = this.scheduledPosts.filter(p => p.page_id == this.formData.page_id);
                if (posts.length === 0) return;
                this.postsToBulkDelete = posts.map(p => p.id);
                this.isBulkDelete = true;
                this.showDeleteModal = true;
            },
            async executeBulkDelete(fromFb) {
                if (this.postsToBulkDelete.length === 0) return;
                const count = this.postsToBulkDelete.length;
                this.showDeleteModal = false;
                const ids = this.postsToBulkDelete.join(',');

                // Use background mode for more than 3 posts to avoid browser hanging
                const isBg = count > 3;
                const url = `ajax_scheduler.php?action=delete_bulk&ids=${ids}&from_fb=${fromFb ? 1 : 0}${isBg ? '&background=1' : ''}`;

                try {
                    const response = await fetch(url);
                    const result = await response.json();

                    if (result.status === 'success' || result.status === 'background') {
                        if (result.status === 'background') {
                            alert('تم تشغيل الحذف الجماعي في الخلفية بنجاح. يمكنك التنقل في الموقع بحرية، وقد تستغرق العملية بضع دقائق لتكتمل بالكامل على فيسبوك.');
                        }
                        await this.refreshPostList();
                        this.exitSelectionMode();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    console.error('Bulk delete failed', e);
                    alert('Bulk delete request failed');
                } finally {
                    this.isBulkDelete = false;
                    this.postsToBulkDelete = [];
                }
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>