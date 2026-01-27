</main>
<footer class="bg-gray-900 border-t border-gray-800 pt-16 pb-8 relative z-10 mt-auto text-center">
    <!-- Gradient Line Top -->
    <div
        class="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent">
    </div>

    <div class="container mx-auto px-4">
        <!-- Brand & Description -->
        <div class="mb-8">
            <a href="<?php echo $prefix; ?>index.php"
                class="flex items-center justify-center space-x-2 rtl:space-x-reverse mb-4">
                <?php if (getSetting('site_logo')): ?>
                    <img src="<?php echo $prefix; ?>uploads/<?php echo getSetting('site_logo'); ?>" class="h-10 w-auto"
                        alt="Logo">
                <?php else: ?>
                    <div
                        class="w-8 h-8 rounded-lg bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-lg">
                        <?php echo mb_substr(__('site_name'), 0, 1, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <span
                    class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400">
                    <?php echo __('site_name'); ?>
                </span>
            </a>
            <p class="text-gray-400 text-base leading-relaxed max-w-2xl mx-auto">
                <?php echo getSetting('footer_description_' . $lang, 'أقوى أداة لاستخراج واستهداف بيانات العملاء من فيسبوك.'); ?>
            </p>
        </div>

        <!-- Social Media -->
        <div class="flex flex-wrap justify-center gap-4 mb-8">
            <?php if (getSetting('social_facebook')): ?>
                <a href="<?php echo getSetting('social_facebook'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-indigo-600 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_messenger')): ?>
                <?php 
                $msgr_link = getSetting('social_messenger');
                if (!preg_match("~^(?:f|ht)tps?://~i", $msgr_link)) {
                    $msgr_link = "https://m.me/" . $msgr_link;
                }
                ?>
                <a href="<?php echo $msgr_link; ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-[#00B2FF] hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.912 1.452 5.513 3.717 7.21v3.532c0 .248.188.468.433.497l.067.003c.18 0 .344-.092.443-.242L8.62 19.38c.15.207.308.406.474.596 1.157.653 2.477 1.022 3.882 1.022 5.523 0 10-4.145 10-9.258C22 6.145 17.523 2 12 2zm1.026 12.185l-2.454-2.62-4.787 2.62 5.263-5.592 2.52 2.62 4.717-2.62-5.259 5.592z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_twitter')): ?>
                <a href="<?php echo getSetting('social_twitter'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-black hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_instagram')): ?>
                <a href="<?php echo getSetting('social_instagram'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-pink-600 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_telegram')): ?>
                <a href="<?php echo getSetting('social_telegram'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-blue-500 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.52-1.4.51-.46-.01-1.35-.26-2.01-.48-.81-.27-1.45-.42-1.39-.88.03-.24.37-.48 1.02-.73 4-1.74 6.67-2.88 8.01-3.41 3.81-1.51 4.61-1.78 5.12-1.78.11 0 .37.03.53.15.14.1.18.24.2.34.02.1.04.33.02.51z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_whatsapp')): ?>
                <a href="<?php echo getSetting('social_whatsapp'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-green-500 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.484 8.412 0 6.556-5.338 11.892-11.893 11.892-2.01-.001-3.98-.51-5.725-1.479l-6.272 1.687zm5.831-3.354c1.547.919 3.447 1.405 5.385 1.406 5.756 0 10.439-4.683 10.441-10.441 0-2.781-1.083-5.396-3.048-7.361s-4.58-3.048-7.362-3.048c-5.758 0-10.441 4.683-10.444 10.441 0 1.945.541 3.846 1.564 5.396l-.999 3.649 3.733-.966zm10.37-6.848c-.282-.142-1.67-.824-1.928-.918-.258-.094-.446-.142-.634.142-.188.282-.728.918-.891 1.106-.164.188-.328.212-.61.071-.282-.142-1.191-.439-2.27-1.402-.839-.748-1.405-1.673-1.569-1.956-.164-.282-.018-.435.123-.574.127-.124.282-.329.423-.494.141-.165.188-.282.282-.47.094-.188.047-.353-.024-.494-.071-.141-.634-1.528-.868-2.093-.229-.553-.46-.477-.634-.486-.164-.008-.353-.01-.541-.01s-.494.071-.752.353c-.258.282-.987.965-.987 2.353s1.011 2.729 1.152 2.917c.141.188 1.989 3.04 4.818 4.259.673.29 1.199.463 1.609.593.676.214 1.291.184 1.777.112.542-.081 1.67-.682 1.905-1.34s.235-1.223.165-1.341c-.071-.118-.258-.188-.541-.33z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_youtube')): ?>
                <a href="<?php echo getSetting('social_youtube'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-red-600 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_linkedin')): ?>
                <a href="<?php echo getSetting('social_linkedin'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-blue-700 hover:text-white transition-all duration-300 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z" />
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (getSetting('social_tiktok')): ?>
                <a href="<?php echo getSetting('social_tiktok'); ?>" target="_blank"
                    class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:bg-black hover:text-white transition-all duration-300 group shadow-lg shadow-pink-500/20">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.17-2.86-.6-4.12-1.31a8.15 8.15 0 01-1.89-1.35c-.01 2.18 0 4.35-.01 6.52 0 2.39-.73 4.79-2.25 6.46-1.43 1.58-3.41 2.57-5.41 2.66-2.31.1-4.6-.61-6.3-2.22-1.88-1.78-2.81-4.47-2.36-6.98.37-2.03 1.6-3.82 3.39-4.83.85-.48 1.75-.86 2.63-1.1.01 1.46-.01 2.92.02 4.38-.5.13-1 .31-1.42.58-1.03.66-1.57 1.83-1.47 3.03.06 1.15.59 2.26 1.44 3.07a4.1 4.1 0 005.15.4c.83-.55 1.4-1.42 1.61-2.4.15-.71.13-1.44.13-2.15-.02-3.14-.02-6.28-.01-9.42-.11-.05-.11-.05-.11-.11z" />
                    </svg>
                </a>
            <?php endif; ?>
        </div>

        <!-- Contact Email -->
        <div class="mb-8">
            <h4 class="text-white font-bold mb-2"><?php echo __('contact_info'); ?></h4>
            <div class="flex flex-col items-center gap-2">
                <a href="mailto:<?php echo getSetting('contact_email'); ?>"
                    class="text-indigo-400 hover:text-indigo-300 transition-colors font-mono"><?php echo getSetting('contact_email'); ?></a>
                <?php if (getSetting('contact_phone')): ?>
                    <span class="text-gray-400 font-mono"><?php echo getSetting('contact_phone'); ?></span>
                <?php endif; ?>
                <?php
                $c_address = ($lang == 'ar') ? getSetting('contact_address_ar') : getSetting('contact_address_en');
                if (empty($c_address)) {
                    $c_address = ($lang == 'ar') ? 'جمهورية مصر العربية' : 'Arab Republic of Egypt';
                }
                ?>
                <span class="text-gray-400 text-sm"><?php echo htmlspecialchars($c_address); ?></span>
            </div>
        </div>

        <div class="border-t border-gray-800 pt-8 flex flex-col items-center justify-center text-sm text-gray-500">
            <p class="mb-4 dir-ltr flex flex-wrap items-center justify-center gap-2">
                <?php if ($lang === 'ar'): ?>
                    جميع الحقوق محفوظة لـ <strong class="text-white"><?php echo __('site_name'); ?></strong>
                    <span title="Verified System"
                        class="text-blue-500 bg-blue-500/10 rounded-full px-2 py-0.5 text-[10px] inline-flex items-center gap-1 border border-blue-500/20">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo __('verified'); ?>
                    </span>
                    <?php echo date('Y'); ?> صنع بكل <span class="text-red-500">❤️</span> بواسطة <a
                        href="https://facebook.com/marketati0n/" target="_blank"
                        class="text-indigo-400 hover:text-white transition-colors font-bold">ماركتيشن</a>
                <?php else: ?>
                    All rights reserved <strong class="text-white"><?php echo __('site_name'); ?></strong>
                    <span title="Verified System"
                        class="text-blue-500 bg-blue-500/10 rounded-full px-2 py-0.5 text-[10px] inline-flex items-center gap-1 border border-blue-500/20">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo __('verified'); ?>
                    </span>
                    <?php echo date('Y'); ?> Made with <span class="text-red-500">❤️</span> by <a
                        href="https://facebook.com/marketati0n/" target="_blank"
                        class="text-indigo-400 hover:text-white transition-colors font-bold">Marketation</a>
                <?php endif; ?>
            </p>

        </div>
    </div>
</footer>

<!-- Scroll to Top Button -->
<?php if (getSetting('enable_scroll_top', '1') == '1'): ?>
    <button id="scrollToTop"
        class="fixed bottom-8 right-8 z-[100] p-4 rounded-2xl bg-indigo-600 text-white shadow-2xl shadow-indigo-500/40 hover:bg-indigo-500 hover:scale-110 active:scale-95 transition-all duration-300 opacity-0 invisible">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>

    <script>
        const scrollBtn = document.getElementById('scrollToTop');
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollBtn.classList.remove('opacity-0', 'invisible');
                scrollBtn.classList.add('opacity-100', 'visible');
            } else {
                scrollBtn.classList.add('opacity-0', 'invisible');
                scrollBtn.classList.remove('opacity-100', 'visible');
            }
        });
        scrollBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
<?php endif; ?>

<!-- Floating Social Buttons -->
<?php if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false): ?>
    <div class="fixed bottom-8 left-8 z-[100] flex flex-col-reverse gap-4">
        <?php
        $platforms = [
            'facebook' => ['color' => '#1877F2', 'icon' => '<path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" />'],
            'messenger' => ['color' => '#00B2FF', 'icon' => '<path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.912 1.452 5.513 3.717 7.21v3.532c0 .248.188.468.433.497l.067.003c.18 0 .344-.092.443-.242L8.62 19.38c.15.207.308.406.474.596 1.157.653 2.477 1.022 3.882 1.022 5.523 0 10-4.145 10-9.258C22 6.145 17.523 2 12 2zm1.026 12.185l-2.454-2.62-4.787 2.62 5.263-5.592 2.52 2.62 4.717-2.62-5.259 5.592z" />'],
            'twitter' => ['color' => '#000000', 'icon' => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />'],
            'instagram' => ['color' => '#E4405F', 'icon' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />'],
            'telegram' => ['color' => '#26A5E4', 'icon' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.52-1.4.51-.46-.01-1.35-.26-2.01-.48-.81-.27-1.45-.42-1.39-.88.03-.24.37-.48 1.02-.73 4-1.74 6.67-2.88 8.01-3.41 3.81-1.51 4.61-1.78 5.12-1.78.11 0 .37.03.53.15.14.1.18.24.2.34.02.1.04.33.02.51z" />'],
            'whatsapp' => ['color' => '#25D366', 'icon' => '<path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.484 8.412 0 6.556-5.338 11.892-11.893 11.892-2.01-.001-3.98-.51-5.725-1.479l-6.272 1.687zm5.831-3.354c1.547.919 3.447 1.405 5.385 1.406 5.756 0 10.439-4.683 10.441-10.441 0-2.781-1.083-5.396-3.048-7.361s-4.58-3.048-7.362-3.048c-5.758 0-10.441 4.683-10.444 10.441 0 1.945.541 3.846 1.564 5.396l-.999 3.649 3.733-.966zm10.37-6.848c-.282-.142-1.67-.824-1.928-.918-.258-.094-.446-.142-.634.142-.188.282-.728.918-.891 1.106-.164.188-.328.212-.61.071-.282-.142-1.191-.439-2.27-1.402-.839-.748-1.405-1.673-1.569-1.956-.164-.282-.018-.435.123-.574.127-.124.282-.329.423-.494.141-.165.188-.282.282-.47.094-.188.047-.353-.024-.494-.071-.141-.634-1.528-.868-2.093-.229-.553-.46-.477-.634-.486-.164-.008-.353-.01-.541-.01s-.494.071-.752.353c-.258.282-.987.965-.987 2.353s1.011 2.729 1.152 2.917c.141.188 1.989 3.04 4.818 4.259.673.29 1.199.463 1.609.593.676.214 1.291.184 1.777.112.542-.081 1.67-.682 1.905-1.34s.235-1.223.165-1.341c-.071-.118-.258-.188-.541-.33z" />'],
            'youtube' => ['color' => '#FF0000', 'icon' => '<path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" />'],
            'linkedin' => ['color' => '#0A66C2', 'icon' => '<path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z" />'],
            'tiktok' => ['color' => '#000000', 'icon' => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.17-2.86-.6-4.12-1.31a8.15 8.15 0 01-1.89-1.35c-.01 2.18 0 4.35-.01 6.52 0 2.39-.73 4.79-2.25 6.46-1.43 1.58-3.41 2.57-5.41 2.66-2.31.1-4.6-.61-6.3-2.22-1.88-1.78-2.81-4.47-2.36-6.98.37-2.03 1.6-3.82 3.39-4.83.85-.48 1.75-.86 2.63-1.1.01 1.46-.01 2.92.02 4.38-.5.13-1 .31-1.42.58-1.03.66-1.57 1.83-1.47 3.03.06 1.15.59 2.26 1.44 3.07a4.1 4.1 0 005.15.4c.83-.55 1.4-1.42 1.61-2.4.15-.71.13-1.44.13-2.15-.02-3.14-.02-6.28-.01-9.42-.11-.05-.11-.05-.11-.11z" />']
        ];

        foreach ($platforms as $key => $p):
            $link = getSetting('social_' . $key);
            $is_floating = getSetting('floating_' . $key) == '1';

            if ($link && $is_floating):
                // Smart link formatting
                if ($key == 'whatsapp' && !preg_match("~^(?:f|ht)tps?://~i", $link)) {
                    $link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $link);
                } elseif ($key == 'messenger' && !preg_match("~^(?:f|ht)tps?://~i", $link)) {
                    $link = "https://m.me/" . $link;
                }
                ?>
                <a href="<?php echo $link; ?>" target="_blank"
                    class="w-14 h-14 rounded-full text-white flex items-center justify-center shadow-2xl hover:scale-110 active:scale-95 transition-all duration-300 animate-in slide-in-from-left-8"
                    style="background-color: <?php echo $p['color']; ?>; box-shadow: 0 10px 25px -5px <?php echo $p['color']; ?>66;">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                        <?php echo $p['icon']; ?>
                    </svg>
                </a>
            <?php endif; endforeach; ?>
    </div>
<?php endif; ?>

</script>


</body>

</html>