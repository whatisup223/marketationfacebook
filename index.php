<?php
if (!file_exists(__DIR__ . '/includes/db_config.php')) {
    header("Location: install.php");
    exit;
}
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<div class="relative min-h-screen flex items-center pt-24 pb-16 lg:pt-32 lg:pb-32 overflow-hidden">
    <!-- Animated Background Blobs -->
    <div
        class="absolute top-0 -left-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
    </div>
    <div
        class="absolute top-0 -right-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
    </div>
    <div
        class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            <!-- Text Content Side -->
            <div
                class="<?php echo $lang === 'ar' ? 'lg:order-2 text-right' : 'lg:order-1 text-left'; ?> animate-fade-in">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md mb-8 hover:bg-white/10 transition-all duration-300">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    <span
                        class="text-indigo-300 text-sm font-semibold tracking-wide uppercase"><?php echo __('hero_badge'); ?></span>
                </div>

                <h1 class="text-5xl md:text-7xl font-extrabold mb-4 leading-[1.1] tracking-tight text-white">
                    <?php
                    $title = __('hero_title');
                    // Highlight the last word if it's more than one word
                    $words = explode(' ', $title);
                    if (count($words) > 1) {
                        $last = array_pop($words);
                        echo implode(' ', $words) . ' <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-purple-500">' . $last . '</span>';
                    } else {
                        echo '<span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-purple-500">' . $title . '</span>';
                    }
                    ?>
                </h1>

                <!-- Dynamic Hero Feature Text -->
                <p class="text-indigo-400 font-bold text-lg md:text-xl mb-8 flex items-center gap-2">
                    <span class="w-8 h-px bg-indigo-500/50"></span>
                    <?php echo __('hero_feature'); ?>
                </p>

                <p class="text-xl text-gray-400 mb-10 leading-relaxed max-w-2xl">
                    <?php echo __('hero_subtitle'); ?>
                </p>

                <div class="flex flex-col sm:flex-row items-center gap-5 mb-10">
                    <?php if (!isLoggedIn()): ?>
                        <a href="register.php"
                            class="group w-full sm:w-auto px-10 py-5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-2xl shadow-2xl shadow-indigo-600/30 transition-all duration-300 hover:scale-[1.02] flex items-center justify-center gap-3">
                            <span><?php echo __('start_now'); ?></span>
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </a>
                        <a href="#how-it-works"
                            class="w-full sm:w-auto px-10 py-5 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all duration-300 flex items-center justify-center gap-2 backdrop-blur-xl">
                            <?php echo __('how_it_works'); ?>
                        </a>
                    <?php else: ?>
                        <a href="user/dashboard.php"
                            class="px-10 py-5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-2xl shadow-2xl shadow-indigo-600/30 transition-all duration-300 hover:scale-[1.02]">
                            <?php echo __('dashboard'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hero Visual Side -->
            <div class="<?php echo $lang === 'ar' ? 'lg:order-1' : 'lg:order-2'; ?> perspective-1000">
                <div class="relative group animate-float">
                    <!-- Glow effect -->
                    <div
                        class="absolute -inset-4 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-[3rem] blur-3xl opacity-20 group-hover:opacity-40 transition duration-1000">
                    </div>

                    <div
                        class="relative glass-card rounded-[3rem] p-4 border border-white/10 shadow-3xl overflow-hidden backdrop-blur-2xl">
                        <?php
                        $hero_img = getSetting('hero_image');
                        $hero_img_url = !empty($hero_img) ? 'uploads/' . $hero_img : 'assets/img/hero-default.png';
                        ?>
                        <div class="relative rounded-[2.5rem] overflow-hidden aspect-[4/3] lg:aspect-square">
                            <img src="<?php echo $hero_img_url; ?>" alt="Hero Visual"
                                class="w-full h-full object-cover transform group-hover:scale-110 transition duration-700">

                            <!-- Static Overlay Elements -->
                            <div
                                class="absolute inset-0 bg-gradient-to-tr from-indigo-900/40 via-transparent to-purple-900/40 mix-blend-overlay">
                            </div>

                            <!-- Floating Badge on Image -->
                            <div class="absolute bottom-6 left-6 right-6">
                                <div
                                    class="glass-card p-4 rounded-2xl flex items-center gap-4 border-white/20 backdrop-blur-md bg-white/5">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                    <div class="<?php echo $lang === 'ar' ? 'text-right' : 'text-left'; ?>">
                                        <p class="text-white font-black text-sm uppercase tracking-wider">
                                            <?php echo getSetting('hero_floating_top_' . $lang, __('hero_feature')); ?>
                                        </p>
                                        <p class="text-indigo-300 text-xs font-bold">
                                            <?php echo getSetting('hero_floating_main_' . $lang, __('hero_badge')); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decorative Floating Elements -->
                    <div
                        class="absolute -top-6 -right-6 w-20 h-20 bg-indigo-500/20 rounded-full blur-2xl animate-pulse">
                    </div>
                    <div
                        class="absolute -bottom-6 -left-6 w-24 h-24 bg-purple-500/20 rounded-full blur-2xl animate-pulse animation-delay-2000">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- About Us Section -->
<section id="about-us" class="py-24 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div
            class="glass-card rounded-[3rem] p-10 md:p-20 border border-white/10 relative overflow-hidden backdrop-blur-3xl shadow-3xl">
            <!-- Decorative Background -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl animate-pulse"></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <!-- Image/Visual Side -->
                <div class="relative group">
                    <div
                        class="absolute -inset-4 bg-gradient-to-tr from-indigo-500 to-purple-600 rounded-[2.5rem] blur-2xl opacity-20 group-hover:opacity-30 transition duration-1000">
                    </div>
                    <div
                        class="relative rounded-[2.5rem] overflow-hidden border border-white/10 shadow-2xl animate-float">
                        <?php
                        $about_img = getSetting('about_image');
                        $about_img_url = !empty($about_img) ? 'uploads/' . $about_img : 'assets/img/about-default.png';
                        ?>
                        <img src="<?php echo $about_img_url; ?>" alt="About Us"
                            class="w-full h-full object-cover transform hover:scale-105 transition duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                        <div class="absolute bottom-8 left-8 right-8">
                            <div class="glass-card p-4 rounded-2xl flex items-center gap-4 border-white/20">
                                <div
                                    class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                        </path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-white font-bold text-sm">
                                        <?php echo getSetting('about_floating_top_' . $lang, __('hero_badge')); ?>
                                    </p>
                                    <p class="text-gray-400 text-xs">
                                        <?php echo getSetting('about_floating_main_' . $lang, __('fast_secure')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Text Content Side -->
                <div class="animate-fade-in">
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 mb-6">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                        <span
                            class="text-indigo-300 text-xs font-bold uppercase tracking-widest"><?php echo getSetting('about_title_' . $lang, __('about_us')); ?></span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-black text-white mb-8 leading-tight">
                        <?php echo getSetting('about_subtitle_' . $lang, __('about_subtitle')); ?>
                    </h2>
                    <p class="text-lg text-gray-400 leading-relaxed mb-10">
                        <?php echo getSetting('about_desc_' . $lang, __('about_desc')); ?>
                    </p>

                    <!-- Mission & Vision -->
                    <?php
                    $mission = getSetting('mission_' . $lang);
                    $vision = getSetting('vision_' . $lang);
                    if ($mission || $vision):
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
                            <?php if ($mission): ?>
                                <div
                                    class="glass-card p-5 rounded-2xl border border-white/5 bg-indigo-500/5 hover:bg-indigo-500/10 transition-colors">
                                    <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                                        <span class="p-1.5 rounded-lg bg-indigo-500/20 text-indigo-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </span>
                                        <?php echo $lang === 'ar' ? 'رسالتنا' : 'Our Mission'; ?>
                                    </h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">
                                        <?php echo $mission; ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($vision): ?>
                                <div
                                    class="glass-card p-5 rounded-2xl border border-white/5 bg-purple-500/5 hover:bg-purple-500/10 transition-colors">
                                    <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                                        <span class="p-1.5 rounded-lg bg-purple-500/20 text-purple-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </span>
                                        <?php echo $lang === 'ar' ? 'رؤيتنا' : 'Our Vision'; ?>
                                    </h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">
                                        <?php echo $vision; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>



                    <a href="<?php echo getSetting('about_btn_url', '#features'); ?>"
                        class="inline-flex items-center justify-center px-8 py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all duration-300 gap-3 group/link">
                        <span><?php echo getSetting('about_btn_' . $lang, __('about_btn')); ?></span>
                        <svg class="w-5 h-5 group-hover/link:translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-24 relative overflow-hidden">
    <!-- Background Flair -->
    <div class="absolute top-1/2 left-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-[100px] -translate-x-1/2"></div>
    <div
        class="absolute bottom-0 right-0 w-80 h-80 bg-purple-600/5 rounded-full blur-[120px] translate-x-1/3 translate-y-1/3">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-20 animate-fade-in">
            <h2 class="text-4xl md:text-6xl font-black text-white mb-6 tracking-tight">
                <?php echo __('features_title'); ?>
            </h2>
            <div
                class="w-24 h-2 bg-gradient-to-r from-indigo-500 to-purple-600 mx-auto rounded-full shadow-lg shadow-indigo-500/20">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <!-- Feature 1 -->
            <div class="group relative animate-fade-in" style="animation-delay: 100ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-indigo-500 to-transparent rounded-[2.5rem] blur opacity-0 group-hover:opacity-20 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-10 rounded-[2.5rem] border border-white/5 hover:border-indigo-500/30 transition-all duration-500 h-full flex flex-col items-center text-center overflow-hidden">

                    <div class="w-24 h-24 relative mb-8 group/icon">
                        <div
                            class="absolute inset-0 bg-indigo-500/20 rounded-2xl blur-xl group-hover/icon:blur-2xl transition-all">
                        </div>
                        <div
                            class="relative w-full h-full bg-gradient-to-br from-indigo-500/20 to-indigo-600/5 border border-white/10 rounded-2xl flex items-center justify-center text-indigo-400 group-hover/icon:scale-110 group-hover/icon:rotate-3 transition-all duration-500 shadow-2xl animate-float">
                            <svg class="w-12 h-12 drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="<?php echo getSetting('feature_1_icon', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'); ?>">
                                </path>
                            </svg>
                        </div>
                    </div>

                    <h3
                        class="text-2xl font-black text-white mb-4 tracking-tight group-hover:text-indigo-300 transition-colors">
                        <?php echo __('feature_1_title'); ?>
                    </h3>
                    <p class="text-gray-400 leading-relaxed text-lg">
                        <?php echo __('feature_1_desc'); ?>
                    </p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="group relative animate-fade-in" style="animation-delay: 300ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-purple-500 to-transparent rounded-[2.5rem] blur opacity-0 group-hover:opacity-20 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-10 rounded-[2.5rem] border border-white/5 hover:border-purple-500/30 transition-all duration-500 h-full flex flex-col items-center text-center overflow-hidden">

                    <div class="w-24 h-24 relative mb-8 group/icon">
                        <div
                            class="absolute inset-0 bg-purple-500/20 rounded-2xl blur-xl group-hover/icon:blur-2xl transition-all">
                        </div>
                        <div class="relative w-full h-full bg-gradient-to-br from-purple-500/20 to-purple-600/5 border border-white/10 rounded-2xl flex items-center justify-center text-purple-400 group-hover/icon:scale-110 group-hover/icon:-rotate-3 transition-all duration-500 shadow-2xl animate-float"
                            style="animation-delay: 1s;">
                            <svg class="w-12 h-12 drop-shadow-[0_0_10px_rgba(168,85,247,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="<?php echo getSetting('feature_2_icon', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'); ?>">
                                </path>
                            </svg>
                        </div>
                    </div>

                    <h3
                        class="text-2xl font-black text-white mb-4 tracking-tight group-hover:text-purple-300 transition-colors">
                        <?php echo __('feature_2_title'); ?>
                    </h3>
                    <p class="text-gray-400 leading-relaxed text-lg">
                        <?php echo __('feature_2_desc'); ?>
                    </p>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="group relative animate-fade-in" style="animation-delay: 500ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-pink-500 to-transparent rounded-[2.5rem] blur opacity-0 group-hover:opacity-20 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-10 rounded-[2.5rem] border border-white/5 hover:border-pink-500/30 transition-all duration-500 h-full flex flex-col items-center text-center overflow-hidden">

                    <div class="w-24 h-24 relative mb-8 group/icon">
                        <div
                            class="absolute inset-0 bg-pink-500/20 rounded-2xl blur-xl group-hover/icon:blur-2xl transition-all">
                        </div>
                        <div class="relative w-full h-full bg-gradient-to-br from-pink-500/20 to-pink-600/5 border border-white/10 rounded-2xl flex items-center justify-center text-pink-400 group-hover/icon:scale-110 group-hover/icon:rotate-3 transition-all duration-500 shadow-2xl animate-float"
                            style="animation-delay: 2s;">
                            <svg class="w-12 h-12 drop-shadow-[0_0_10px_rgba(236,72,153,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="<?php echo getSetting('feature_3_icon', 'M13 10V3L4 14h7v7l9-11h-7z'); ?>">
                                </path>
                            </svg>
                        </div>
                    </div>

                    <h3
                        class="text-2xl font-black text-white mb-4 tracking-tight group-hover:text-pink-300 transition-colors">
                        <?php echo __('feature_3_title'); ?>
                    </h3>
                    <p class="text-gray-400 leading-relaxed text-lg">
                        <?php echo __('feature_3_desc'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="py-24 relative overflow-hidden bg-slate-900/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-bold mb-4">
                <?php echo getSetting('services_title_' . $lang, __('services_title_ar')); ?>
            </h2>
            <div class="w-24 h-1 bg-pink-500 mx-auto rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 py-8">
            <?php
            for ($i = 1; $i <= 6; $i++):
                $s_title = getSetting('service_' . $i . '_title_' . $lang);
                $s_desc = getSetting('service_' . $i . '_desc_' . $lang);
                $s_icon = getSetting('service_' . $i . '_icon', 'M13 10V3L4 14h7v7l9-11h-7z');
                $is_featured = (getSetting('service_' . $i . '_featured') == '1');

                if (empty($s_title))
                    continue;
                ?>
                <div class="relative group h-full">
                    <?php if ($is_featured): ?>
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-20">
                            <span
                                class="bg-gradient-to-r from-pink-500 to-rose-600 text-white text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full shadow-lg shadow-pink-500/30">
                                <?php echo __('most_popular'); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div
                        class="glass-card h-full flex flex-col p-8 rounded-[2.5rem] border border-white/5 hover:border-pink-500/30 transition-all duration-500 relative overflow-hidden <?php echo $is_featured ? 'bg-white/[0.03] scale-[1.03] ring-2 ring-pink-500/20 z-10' : ''; ?>">
                        <!-- Background Glow -->
                        <div
                            class="absolute -bottom-12 -right-12 w-32 h-32 bg-pink-500/10 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>

                        <!-- Header Icon -->
                        <div class="mb-8 relative">
                            <div
                                class="w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-500/20 to-rose-600/5 flex items-center justify-center text-pink-400 group-hover:scale-110 transition-transform duration-500">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?php echo $s_icon; ?>"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="flex-grow">
                            <h3 class="text-2xl font-black text-white mb-4 tracking-tight"><?php echo $s_title; ?></h3>
                            <div class="w-12 h-1 bg-pink-500/30 rounded-full mb-8 group-hover:w-20 transition-all"></div>

                            <!-- Checkmark List -->
                            <ul class="space-y-4 mb-10 text-left">
                                <?php
                                $features = explode("\n", $s_desc);
                                foreach ($features as $feature):
                                    $feature = trim($feature);
                                    if (empty($feature))
                                        continue;
                                    ?>
                                    <li class="flex items-start gap-3">
                                        <div
                                            class="w-5 h-5 rounded-full bg-pink-500/20 flex items-center justify-center text-pink-400 mt-1 flex-shrink-0">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-gray-300 text-sm leading-tight"><?php echo $feature; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Footer -->
                        <div class="mt-auto">
                            <?php
                            $btn_text = getSetting('service_' . $i . '_btn_' . $lang);
                            if (empty($btn_text))
                                $btn_text = __('start_now');
                            ?>
                            <a href="#contact"
                                class="flex items-center justify-center w-full py-4 rounded-2xl font-black text-sm tracking-wide transition-all duration-300 <?php echo $is_featured ? 'bg-white text-gray-900 hover:bg-gray-100 shadow-xl shadow-white/10' : 'bg-white/5 text-white border border-white/10 hover:bg-white/10'; ?>">
                                <span><?php echo $btn_text; ?></span>
                                <svg class="w-4 h-4 ml-2 rtl:mr-2 rtl:ml-0 group-hover:translate-x-1 transition-transform"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" class="py-24 relative overflow-hidden">
    <div class="absolute inset-0 bg-indigo-900/10 -skew-y-3 transform origin-top-left z-0"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-bold mb-4"><?php echo __('how_it_works_title'); ?></h2>
            <div class="w-24 h-1 bg-white/20 mx-auto rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative">
            <!-- Connecting Line (Desktop) -->
            <div
                class="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-gradient-to-r from-transparent via-indigo-500 to-transparent border-t-2 border-dashed border-indigo-500/30 z-0">
            </div>

            <!-- Step 1 -->
            <div class="relative z-10 text-center group">
                <div
                    class="w-24 h-24 bg-slate-900 border-2 border-indigo-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-indigo-500/20 group-hover:-translate-y-2 transition-transform duration-300">
                    <span class="text-3xl font-bold text-white">1</span>
                </div>
                <h3 class="text-xl font-bold mb-3"><?php echo __('step_1_title'); ?></h3>
                <p class="text-gray-400"><?php echo __('step_1_desc'); ?></p>
            </div>

            <!-- Step 2 -->
            <div class="relative z-10 text-center group">
                <div
                    class="w-24 h-24 bg-slate-900 border-2 border-purple-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-purple-500/20 group-hover:-translate-y-2 transition-transform duration-300">
                    <span class="text-3xl font-bold text-white">2</span>
                </div>
                <h3 class="text-xl font-bold mb-3"><?php echo __('step_2_title'); ?></h3>
                <p class="text-gray-400"><?php echo __('step_2_desc'); ?></p>
            </div>

            <!-- Step 3 -->
            <div class="relative z-10 text-center group">
                <div
                    class="w-24 h-24 bg-slate-900 border-2 border-pink-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-pink-500/20 group-hover:-translate-y-2 transition-transform duration-300">
                    <span class="text-3xl font-bold text-white">3</span>
                </div>
                <h3 class="text-xl font-bold mb-3"><?php echo __('step_3_title'); ?></h3>
                <p class="text-gray-400"><?php echo __('step_3_desc'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Portfolio Section (Our Work) -->
<?php
$portfolioStmt = $pdo->query("SELECT * FROM portfolio_items ORDER BY display_order ASC, id DESC");
$portfolioItems = $portfolioStmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($portfolioItems)):
    ?>
    <section id="portfolio" class="py-24 relative overflow-hidden bg-slate-900/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4 tracking-tight">
                    <?php echo getSetting('portfolio_title_' . $lang, __('portfolio_section')); ?>
                </h2>
                <div class="w-24 h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($portfolioItems as $item):
                    $item_title = $item['title_' . $lang] ?? $item['title_en'];
                    $item_desc = $item['description_' . $lang] ?? $item['description_en'];
                    ?>
                    <div class="group relative perspective-1000">
                        <div
                            class="glass-card rounded-[2rem] overflow-hidden border border-white/5 transition-all duration-500 hover:scale-[1.02] hover:shadow-2xl hover:shadow-blue-500/10 h-full flex flex-col">

                            <!-- Top Browser Frame (Mockup) -->
                            <div class="bg-gray-800/80 px-4 py-3 flex items-center gap-2 border-b border-white/5">
                                <div class="flex gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-500/50"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-500/50"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-green-500/50"></span>
                                </div>
                                <div
                                    class="flex-1 bg-white/5 rounded-lg py-1 px-3 text-[10px] text-gray-500 font-mono truncate">
                                    <?php echo $item['preview_url'] ?: 'https://work-preview.com'; ?>
                                </div>
                            </div>

                            <!-- Content Area - Increased Height -->
                            <div class="relative overflow-hidden bg-gray-900 flex-shrink-0" style="height: 400px;">
                                <?php if ($item['item_type'] == 'iframe'): ?>
                                    <iframe src="<?php echo htmlspecialchars($item['content_url']); ?>"
                                        class="w-full h-full border-0 origin-top-left" style="background: white;"
                                        sandbox="allow-scripts allow-same-origin allow-forms" loading="lazy"></iframe>
                                    <div class="absolute inset-0 bg-transparent z-20"></div>
                                <?php else: ?>
                                    <img src="uploads/<?php echo $item['content_url']; ?>"
                                        class="w-full h-full object-cover transition duration-700 group-hover:scale-110"
                                        alt="<?php echo $item_title; ?>">
                                <?php endif; ?>

                                <!-- Hover Overlay -->
                                <div
                                    class="absolute inset-0 bg-indigo-600/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm z-30">
                                    <?php if (!empty($item['preview_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($item['preview_url']); ?>" target="_blank"
                                            class="px-6 py-2 bg-white text-indigo-600 font-bold rounded-full shadow-lg hover:bg-gray-100 transition-all">
                                            <?php echo __('view'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Details Area - Expanded -->
                            <div class="p-6 flex-grow flex flex-col justify-between min-h-[180px]">
                                <div>
                                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-blue-400 transition-colors">
                                        <?php echo $item_title; ?>
                                    </h3>
                                    <p class="text-sm text-gray-400 leading-relaxed line-clamp-4"><?php echo $item_desc; ?></p>
                                </div>

                                <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                                    <span
                                        class="text-[10px] uppercase font-black tracking-widest text-indigo-400 border border-indigo-500/20 px-3 py-1 rounded-full">
                                        <?php echo htmlspecialchars($item['category_' . $lang] ?: __($item['item_type'])); ?>
                                    </span>
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-blue-500 transition-colors" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Tool Showcase Section -->
<section id="tool-showcase" class="py-24 relative overflow-hidden">
    <!-- Background Decor -->
    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-indigo-600/5 blur-[120px] rounded-full opacity-50">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            <!-- Left: Visual Mockup -->
            <div class="relative group animate-fade-in order-2 lg:order-1">
                <div
                    class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-[2.5rem] blur opacity-25 group-hover:opacity-50 transition duration-1000">
                </div>
                <div class="relative glass-card rounded-[2.5rem] p-2 border border-white/10 overflow-hidden shadow-2xl">
                    <?php
                    $t_img = getSetting('tool_image');
                    $t_img_path = !empty($t_img) ? 'uploads/' . $t_img : 'assets/img/platform-mockup.png';
                    ?>
                    <img src="<?php echo $t_img_path; ?>" alt="Platform Mockup"
                        class="w-full h-auto rounded-[2rem] transform group-hover:scale-[1.02] transition-transform duration-700">

                    <!-- Floating Overlay Stats -->
                    <div class="absolute top-8 right-8 animate-float">
                        <div class="glass-card p-4 rounded-2xl border-white/20 backdrop-blur-xl bg-white/10 shadow-xl">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center text-green-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                        <?php echo __('status_completed'); ?>
                                    </p>
                                    <p class="text-white font-black">99.9% <?php echo __('verified'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Content -->
            <div class="space-y-8 order-1 lg:order-2">
                <div
                    class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-indigo-500/10 border border-indigo-500/20">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                    </span>
                    <span
                        class="text-indigo-400 text-xs font-black uppercase tracking-[0.2em]"><?php echo getSetting('tool_badge_' . $lang) ?: __('extraction_tools'); ?></span>
                </div>

                <h2 class="text-4xl md:text-6xl font-black text-white leading-tight">
                    <?php echo getSetting('tool_title_' . $lang) ?: __('tool_showcase_title'); ?>
                </h2>

                <p class="text-xl text-gray-400 leading-relaxed">
                    <?php echo getSetting('tool_subtitle_' . $lang) ?: __('tool_showcase_subtitle'); ?>
                </p>

                <!-- Features Grid -->
                <div class="grid gap-6">
                    <!-- Item 1 -->
                    <div
                        class="flex items-start gap-5 p-6 rounded-3xl bg-white/5 border border-white/5 hover:border-indigo-500/20 transition-all group/item">
                        <div
                            class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover/item:bg-indigo-500 group-hover/item:text-white transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"></path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-1 tracking-tight">
                                <?php echo getSetting('tool_f1_t_' . $lang) ?: __('tool_feature_1'); ?>
                            </h4>
                            <p class="text-sm text-gray-400">
                                <?php echo getSetting('tool_f1_d_' . $lang) ?: __('tool_feature_1_desc'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Item 2 -->
                    <div
                        class="flex items-start gap-5 p-6 rounded-3xl bg-white/5 border border-white/5 hover:border-purple-500/20 transition-all group/item">
                        <div
                            class="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 group-hover/item:bg-purple-500 group-hover/item:text-white transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-1 tracking-tight">
                                <?php echo getSetting('tool_f2_t_' . $lang) ?: __('tool_feature_2'); ?>
                            </h4>
                            <p class="text-sm text-gray-400">
                                <?php echo getSetting('tool_f2_d_' . $lang) ?: __('tool_feature_2_desc'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Item 3 -->
                    <div
                        class="flex items-start gap-5 p-6 rounded-3xl bg-white/5 border border-white/5 hover:border-blue-500/20 transition-all group/item">
                        <div
                            class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover/item:bg-blue-500 group-hover/item:text-white transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-1 tracking-tight">
                                <?php echo getSetting('tool_f3_t_' . $lang) ?: __('tool_feature_3'); ?>
                            </h4>
                            <p class="text-sm text-gray-400">
                                <?php echo getSetting('tool_f3_d_' . $lang) ?: __('tool_feature_3_desc'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php
                $t_btn_text = getSetting('tool_btn_text_' . $lang);
                $t_btn_url = getSetting('tool_btn_url');
                if ($t_btn_text):
                    ?>
                    <div class="pt-4">
                        <a href="<?php echo htmlspecialchars($t_btn_url ?: '#'); ?>"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-black px-8 py-4 rounded-2xl shadow-xl shadow-indigo-500/20 transition-all hover:-translate-y-1">
                            <?php echo htmlspecialchars($t_btn_text); ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</section>

<!-- Pricing Section -->
<?php
$pricingPlans = [];
$pricingStmt = $pdo->query("SELECT * FROM pricing_plans WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
while ($row = $pricingStmt->fetch(PDO::FETCH_ASSOC)) {
    $pricingPlans[] = $row;
}

if (!empty($pricingPlans)):
    ?>
    <section id="pricing" class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900 via-indigo-900/10 to-slate-900"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4 tracking-tight">
                    <?php echo __('pricing_section'); ?>
                </h2>
                <div class="w-24 h-1.5 bg-gradient-to-r from-green-500 to-blue-600 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-<?php echo min(count($pricingPlans), 3); ?> gap-8">
                <?php foreach ($pricingPlans as $plan):
                    $plan_name = $plan['plan_name_' . $lang] ?? $plan['plan_name_en'];
                    $plan_desc = $plan['description_' . $lang] ?? $plan['description_en'];
                    $currency = $plan['currency_' . $lang] ?? $plan['currency_en'];
                    $period = $plan['billing_period_' . $lang] ?? $plan['billing_period_en'];
                    $btn_text = $plan['button_text_' . $lang] ?? $plan['button_text_en'];
                    $features = explode("\n", $plan['features']);
                    $is_featured = $plan['is_featured'];
                    ?>
                    <div class="group relative <?php echo $is_featured ? 'lg:scale-105 lg:-mt-4' : ''; ?>">
                        <?php if ($is_featured): ?>
                            <div class="absolute -top-5 left-1/2 -translate-x-1/2 z-10">
                                <span
                                    class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-xs font-black px-4 py-1.5 rounded-full shadow-lg uppercase tracking-wider">
                                    <?php echo __('most_popular'); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div
                            class="glass-card rounded-[2rem] overflow-hidden border <?php echo $is_featured ? 'border-green-500/30 shadow-2xl shadow-green-500/20' : 'border-white/5'; ?> transition-all duration-500 hover:scale-[1.02] hover:shadow-2xl h-full flex flex-col">
                            <!-- Header -->
                            <div
                                class="p-8 text-center <?php echo $is_featured ? 'bg-gradient-to-br from-green-600/20 to-blue-600/20' : 'bg-white/5'; ?> border-b border-white/5">
                                <h3 class="text-2xl font-black text-white mb-2"><?php echo htmlspecialchars($plan_name); ?></h3>
                                <?php if ($plan_desc): ?>
                                    <p class="text-sm text-gray-400"><?php echo htmlspecialchars($plan_desc); ?></p>
                                <?php endif; ?>

                                <div class="mt-6">
                                    <div class="flex items-baseline justify-center gap-2">
                                        <span
                                            class="text-5xl font-black <?php echo $is_featured ? 'text-green-400' : 'text-white'; ?>">
                                            <?php echo number_format($plan['price'], 2); ?>
                                        </span>
                                        <span class="text-gray-400 text-lg"><?php echo htmlspecialchars($currency); ?></span>
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2"><?php echo htmlspecialchars($period); ?></p>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="p-8 flex-grow">
                                <ul class="space-y-4">
                                    <?php foreach ($features as $feature):
                                        $feature = trim($feature);
                                        if (empty($feature))
                                            continue;
                                        ?>
                                        <li class="flex items-start gap-3 text-gray-300">
                                            <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm leading-relaxed"><?php echo htmlspecialchars($feature); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <!-- CTA Button -->
                            <div class="p-8 pt-0">
                                <?php if (!empty($plan['button_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($plan['button_url']); ?>"
                                        class="block w-full py-4 text-center font-bold rounded-2xl transition-all shadow-lg <?php echo $is_featured ? 'bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-500 hover:to-blue-500 text-white shadow-green-600/30' : 'bg-white/10 hover:bg-white/20 text-white border border-white/10'; ?>">
                                        <?php echo htmlspecialchars($btn_text); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Stats Section -->
<section class="py-24 relative overflow-hidden">
    <div class="absolute inset-0 bg-indigo-500/5 -skew-y-3 transform origin-top-left"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Stat 1 -->
            <div
                class="glass-card p-8 rounded-[2rem] border border-white/10 flex flex-col items-center text-center group hover:bg-white/5 transition-all duration-500">
                <div
                    class="w-16 h-16 bg-indigo-500/10 rounded-2xl flex items-center justify-center text-indigo-400 mb-6 group-hover:scale-110 transition-transform">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
                <div
                    class="text-4xl font-black text-white mb-2 font-mono tracking-tighter group-hover:text-indigo-400 transition-colors">
                    <?php
                    $stat1 = getSetting('stat_users_value', '5K+');
                    $num1 = preg_replace('/[^0-9]/', '', $stat1);
                    $suffix1 = preg_replace('/[0-9]/', '', $stat1);
                    ?>
                    <span class="stat-counter" data-target="<?php echo $num1; ?>">0</span><?php echo $suffix1; ?>
                </div>
                <div class="text-gray-400 font-bold uppercase text-xs tracking-[0.2em]"><?php echo __('stat_users'); ?>
                </div>
            </div>

            <!-- Stat 2 -->
            <div
                class="glass-card p-8 rounded-[2rem] border border-white/10 flex flex-col items-center text-center group hover:bg-white/5 transition-all duration-500">
                <div
                    class="w-16 h-16 bg-purple-500/10 rounded-2xl flex items-center justify-center text-purple-400 mb-6 group-hover:scale-110 transition-transform">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </div>
                <div
                    class="text-4xl font-black text-white mb-2 font-mono tracking-tighter group-hover:text-purple-400 transition-colors">
                    <?php
                    $stat2 = getSetting('stat_leads_value', '2M+');
                    $num2 = preg_replace('/[^0-9]/', '', $stat2);
                    $suffix2 = preg_replace('/[0-9]/', '', $stat2);
                    ?>
                    <span class="stat-counter" data-target="<?php echo $num2; ?>">0</span><?php echo $suffix2; ?>
                </div>
                <div class="text-gray-400 font-bold uppercase text-xs tracking-[0.2em]"><?php echo __('stat_leads'); ?>
                </div>
            </div>

            <!-- Stat 3 -->
            <div
                class="glass-card p-8 rounded-[2rem] border border-white/10 flex flex-col items-center text-center group hover:bg-white/5 transition-all duration-500">
                <div
                    class="w-16 h-16 bg-pink-500/10 rounded-2xl flex items-center justify-center text-pink-400 mb-6 group-hover:scale-110 transition-transform">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <div
                    class="text-4xl font-black text-white mb-2 font-mono tracking-tighter group-hover:text-pink-400 transition-colors">
                    <?php
                    $stat3 = getSetting('stat_satisfaction_value', '99%');
                    $num3 = preg_replace('/[^0-9]/', '', $stat3);
                    $suffix3 = preg_replace('/[0-9]/', '', $stat3);
                    ?>
                    <span class="stat-counter" data-target="<?php echo $num3; ?>">0</span><?php echo $suffix3; ?>
                </div>
                <div class="text-gray-400 font-bold uppercase text-xs tracking-[0.2em]">
                    <?php echo __('stat_satisfaction'); ?>
                </div>
            </div>

            <!-- Stat 4 -->
            <div
                class="glass-card p-8 rounded-[2rem] border border-white/10 flex flex-col items-center text-center group hover:bg-white/5 transition-all duration-500">
                <div
                    class="w-16 h-16 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-emerald-400 mb-6 group-hover:scale-110 transition-transform">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                </div>
                <div
                    class="text-4xl font-black text-white mb-2 font-mono tracking-tighter group-hover:text-emerald-400 transition-colors">
                    <?php
                    $stat4 = getSetting('stat_support_value', '24/7');
                    $num4 = preg_replace('/[^0-9]/', '', $stat4);
                    $suffix4 = preg_replace('/[0-9]/', '', $stat4);
                    ?>
                    <span class="stat-counter" data-target="<?php echo $num4; ?>">0</span><?php echo $suffix4; ?>
                </div>
                <div class="text-gray-400 font-bold uppercase text-xs tracking-[0.2em]">
                    <?php echo __('stat_support'); ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Stats Counter Animation
    const animateCounters = () => {
        const counters = document.querySelectorAll('.stat-counter');
        const speed = 200;

        counters.forEach(counter => {
            const updateCount = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const inc = target / speed;

                if (count < target) {
                    counter.innerText = Math.ceil(count + inc);
                    setTimeout(updateCount, 1);
                } else {
                    counter.innerText = target.toLocaleString();
                }
            };
            updateCount();
        });
    };

    // Intersection Observer to trigger when visible
    const statsSection = document.querySelector('.stat-counter')?.parentElement?.parentElement?.parentElement;
    if (statsSection) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                animateCounters();
                observer.unobserve(entries[0].target);
            }
        }, { threshold: 0.5 });
        observer.observe(statsSection);
    }
</script>

<!-- Testimonials -->
<section id="testimonials" class="py-24 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-bold mb-4">
                <?php echo getSetting('testimonials_title_' . $lang, __('testimonials_title')); ?>
            </h2>
            <div class="w-24 h-1 bg-indigo-500 mx-auto rounded-full"></div>
        </div>

        <div class="max-w-4xl mx-auto" x-data="{ 
            active: 0, 
            total: 0,
            init() {
                this.total = this.$refs.slides.children.length;
                setInterval(() => {
                    this.active = (this.active + 1) % this.total;
                }, 5000);
            }
        }">
            <div class="relative overflow-hidden" x-ref="slides">
                <?php
                $count = 0;
                for ($i = 1; $i <= 4; $i++):
                    $t_content = getSetting('testimonial_' . $i . '_content_' . $lang);
                    $t_author = getSetting('testimonial_' . $i . '_author_' . $lang);
                    $t_image = getSetting('testimonial_' . $i . '_image');

                    if (empty($t_content))
                        continue;

                    $initial = !empty($t_author) ? mb_substr($t_author, 0, 1, 'UTF-8') : 'U';
                    $colors = [
                        ['from-indigo-500', 'to-purple-500'],
                        ['from-pink-500', 'to-red-500'],
                        ['from-emerald-500', 'to-teal-500'],
                        ['from-blue-500', 'to-indigo-500']
                    ];
                    $color = $colors[$count % 4];
                    $slide_index = $count;
                    $count++;
                    ?>
                    <div x-show="active === <?php echo $slide_index; ?>"
                        x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0 transform translate-x-8"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-300 absolute inset-0"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-8" class="w-full">
                        <div
                            class="glass-card p-8 md:p-12 rounded-[2.5rem] relative flex flex-col items-center text-center border border-white/5 shadow-2xl">
                            <div class="text-indigo-400 mb-6 opacity-30">
                                <svg class="w-12 h-12 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M14.017 21L14.017 18C14.017 16.896 14.325 16.053 14.941 15.471C15.558 14.89 16.519 14.506 17.824 14.319L18.441 14.228C19.746 14.041 20.399 12.87 20.399 10.714C20.399 8.558 19.348 7.48 17.245 7.48C15.142 7.48 14.091 8.558 14.091 10.714C14.091 11.838 13.783 12.681 13.167 13.263C12.551 13.845 11.589 14.229 10.285 14.416L9.667 14.507C8.362 14.694 7.71 15.865 7.71 18.021C7.71 20.177 8.761 21.255 10.864 21.255C12.016 21.255 12.894 20.941 13.499 20.312C14.104 19.683 14.406 18.579 14.406 17H14.017V21ZM17.245 7.48C16.193 7.48 15.314 7.794 14.71 8.423C14.105 9.052 13.802 10.156 13.802 11.735H14.191V7.735C14.191 6.631 13.883 5.788 13.267 5.206C12.65 4.624 11.689 4.24 10.384 4.053L9.767 3.962C8.462 3.775 7.81 4.946 7.81 7.102C7.81 9.258 8.861 10.336 10.964 10.336C13.067 10.336 14.118 9.259 14.118 7.102C14.118 5.978 14.426 5.135 15.042 4.553C15.658 3.971 16.62 3.587 17.925 3.4L18.542 3.309C19.847 3.122 20.499 4.293 20.499 6.449C20.499 8.605 19.448 9.683 17.345 9.683L17.245 7.48Z" />
                                </svg>
                            </div>
                            <p class="text-lg md:text-xl text-gray-200 mb-8 leading-relaxed italic font-medium">
                                "<?php echo nl2br($t_content); ?>"
                            </p>
                            <div class="flex flex-col items-center gap-4">
                                <div
                                    class="w-14 h-14 rounded-full overflow-hidden bg-gradient-to-tr <?php echo $color[0] . ' ' . $color[1]; ?> flex items-center justify-center text-white font-bold text-xl shadow-xl shadow-indigo-500/20">
                                    <?php if (!empty($t_image)): ?>
                                        <img src="uploads/<?php echo $t_image; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-white mb-1"><?php echo $t_author; ?></h4>
                                    <div class="flex justify-center text-yellow-400 text-xs tracking-[0.3em]">★★★★★</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Pagination Dots -->
            <div class="flex justify-center gap-3 mt-12">
                <?php for ($j = 0; $j < $count; $j++): ?>
                    <button @click="active = <?php echo $j; ?>" class="w-3 h-3 rounded-full transition-all duration-300"
                        :class="active === <?php echo $j; ?> ? 'bg-indigo-500 w-8' : 'bg-white/10 hover:bg-white/20'"></button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section id="faqs" class="py-16 relative overflow-hidden">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-4xl font-black text-white mb-3">
                <?php echo getSetting('faqs_title_' . $lang, __('faqs_title_ar')); ?>
            </h2>
            <div class="w-16 h-1 bg-yellow-500 mx-auto rounded-full"></div>
        </div>

        <div class="space-y-3">
            <?php
            for ($i = 1; $i <= 5; $i++):
                $f_q = getSetting('faq_' . $i . '_q_' . $lang);
                $f_a = getSetting('faq_' . $i . '_a_' . $lang);

                if (empty($f_q) || empty($f_a))
                    continue;
                ?>
                <div
                    class="glass-card rounded-[1.5rem] border border-white/5 overflow-hidden group transition-all duration-300">
                    <button onclick="toggleFaq(<?php echo $i; ?>)"
                        class="w-full p-5 md:p-6 flex items-center justify-between text-left rtl:text-right gap-4 hover:bg-white/5 transition-colors text-white">
                        <span class="text-base md:text-lg font-bold group-hover:text-yellow-400 transition-colors">
                            <?php echo $f_q; ?>
                        </span>
                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center group-hover:bg-yellow-500/20 group-hover:text-yellow-400 transition-all transform"
                            id="faq-icon-<?php echo $i; ?>">
                            <svg class="w-5 h-5 transition-transform duration-300" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                                </path>
                            </svg>
                        </div>
                    </button>
                    <div id="faq-ans-<?php echo $i; ?>"
                        class="hidden px-5 md:px-6 pb-6 text-gray-400 leading-relaxed text-sm md:text-base border-t border-white/5 pt-4 animate-fadeIn">
                        <?php echo nl2br($f_a); ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<script>
    function toggleFaq(id) {
        const ans = document.getElementById('faq-ans-' + id);
        const icon = document.getElementById('faq-icon-' + id);

        const isHidden = ans.classList.contains('hidden');

        if (isHidden) {
            ans.classList.remove('hidden');
            icon.querySelector('svg').style.transform = 'rotate(180deg)';
        } else {
            ans.classList.add('hidden');
            icon.querySelector('svg').style.transform = 'rotate(0deg)';
        }
    }
</script>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fadeIn {
        animation: fadeIn 0.4s ease-out forwards;
    }
</style>

<!-- Call to Action -->
<section class="py-24 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl text-indigo-400"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl text-purple-400"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="relative group">
            <!-- Animated Glow -->
            <div
                class="absolute -inset-1 bg-gradient-to-r from-indigo-500 via-purple-600 to-pink-500 rounded-[3rem] blur-2xl opacity-30 group-hover:opacity-50 transition duration-1000">
            </div>

            <div
                class="relative glass-card rounded-[3rem] p-12 md:p-20 border border-white/10 overflow-hidden text-center backdrop-blur-3xl">
                <!-- Inner background pattern -->
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                    style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;">
                </div>

                <div class="max-w-3xl mx-auto relative z-10">
                    <h2 class="text-4xl md:text-6xl font-black text-white mb-8 leading-tight tracking-tight">
                        <?php echo __('cta_title'); ?>
                    </h2>
                    <p class="text-xl md:text-2xl text-indigo-200/80 mb-12 leading-relaxed">
                        <?php echo __('cta_subtitle'); ?>
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                        <a href="<?php echo getSetting('cta_btn_url', '#register'); ?>"
                            class="group w-full sm:w-auto px-12 py-5 bg-white text-gray-900 font-black rounded-2xl shadow-2xl hover:bg-gray-100 transition-all duration-300 hover:scale-105 flex items-center justify-center gap-3">
                            <span><?php echo __('cta_button'); ?></span>
                            <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Decorative Icon Floating -->
                <div class="absolute top-10 right-10 opacity-10 animate-float hidden lg:block">
                    <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="py-24 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-black text-white mb-4"><?php echo __('contact_us'); ?></h2>
            <p class="text-indigo-200/60 max-w-2xl mx-auto text-lg"><?php echo __('contact_subtitle'); ?></p>
            <div class="w-24 h-1.5 bg-gradient-to-r from-indigo-500 to-purple-600 mx-auto rounded-full mt-6"></div>
        </div>

        <div class="max-w-4xl mx-auto">
            <!-- Contact Form -->
            <div class="relative group">
                <div
                    class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-[2.5rem] blur opacity-20 group-hover:opacity-30 transition duration-1000">
                </div>
                <div
                    class="relative glass-card p-8 md:p-12 rounded-[3.5rem] border border-white/10 backdrop-blur-3xl shadow-2xl">
                    <form action="" method="POST" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="block text-gray-400 text-sm font-bold mb-3 ml-1 flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                            </path>
                                        </svg>
                                    </div>
                                    <?php echo __('your_name'); ?>
                                </label>
                                <input type="text" name="name" required
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:bg-white/10 transition-all shadow-inner"
                                    placeholder="John Doe">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm font-bold mb-3 ml-1 flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                    <?php echo __('email'); ?>
                                </label>
                                <input type="email" name="email" required
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:bg-white/10 transition-all shadow-inner"
                                    placeholder="name@example.com">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="block text-gray-400 text-sm font-bold mb-3 ml-1 flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-pink-500/10 flex items-center justify-center text-pink-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <?php echo __('subject'); ?>
                                </label>
                                <input type="text" name="subject" required
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:bg-white/10 transition-all shadow-inner"
                                    placeholder="<?php echo $lang === 'ar' ? 'الموضوع' : 'Subject'; ?>">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm font-bold mb-3 ml-1 flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                            </path>
                                        </svg>
                                    </div>
                                    <?php echo __('phone'); ?>
                                </label>
                                <input type="text" name="phone"
                                    class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:bg-white/10 transition-all shadow-inner"
                                    placeholder="+20 123 456 7890">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-400 text-sm font-bold mb-3 ml-1 flex items-center gap-2">
                                <div
                                    class="w-8 h-8 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                                        </path>
                                    </svg>
                                </div>
                                <?php echo __('your_message'); ?>
                            </label>
                            <textarea name="message" required rows="5"
                                class="w-full bg-white/5 border border-white/10 rounded-3xl px-6 py-5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:bg-white/10 transition-all resize-none shadow-inner"
                                placeholder="<?php echo $lang === 'ar' ? 'اكتب رسالتك هنا...' : 'Write your message here...'; ?>"></textarea>
                        </div>

                        <button type="submit"
                            class="w-full py-6 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-black text-lg rounded-[2rem] shadow-2xl shadow-indigo-600/30 transition-all duration-500 hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-4 group/btn">
                            <span><?php echo __('send_message'); ?></span>
                            <svg class="w-6 h-6 group-hover/btn:translate-x-1 group-hover/btn:-translate-y-1 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>