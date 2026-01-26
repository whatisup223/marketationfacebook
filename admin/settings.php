<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

$active_tab = $_POST['active_tab'] ?? 'site';

// Fetch current settings first so they are available for POST logic
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$upload_dir = __DIR__ . '/../uploads/';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Deletions first
    $image_fields = ['site_logo', 'site_favicon', 'about_image', 'testimonial_1_image', 'testimonial_2_image', 'testimonial_3_image', 'testimonial_4_image'];
    foreach ($image_fields as $field) {
        if (isset($_POST['delete_' . $field])) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $old_file = $upload_dir . $settings[$field];
                if (file_exists($old_file))
                    unlink($old_file);
            }
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$field, '', '']);
            $settings[$field] = ''; // Update local settings variable
        }
    }

    // Handle File Uploads
    foreach ($image_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $old_file = $upload_dir . $settings[$field];
                if (file_exists($old_file))
                    unlink($old_file);
            }

            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename)) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$field, $filename, $filename]);
                $settings[$field] = $filename; // Update local settings
            }
        }
    }

    // Handle SMTP Test
    if (isset($_POST['test_smtp'])) {
        $test_email = $_POST['test_smtp_email'] ?? '';
        if (!empty($test_email)) {
            require_once __DIR__ . '/../includes/MailService.php';
            $success = sendEmail($test_email, 'SMTP Test - ' . ($settings['site_name_ar'] ?? 'System'), '<p>This is a test email to verify your SMTP settings.</p>');
            if ($success) {
                $smtp_status = ['success' => true, 'message' => __('smtp_test_success')];
            } else {
                $smtp_status = ['success' => false, 'message' => __('smtp_test_failed')];
            }
        }
    }

    // Update other settings
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'delete_') === 0 || $key === 'test_smtp' || $key === 'test_smtp_email' || $key === 'active_tab')
            continue; // Skip deletion buttons and non-setting fields

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        $settings[$key] = $value; // Update local settings
    }

    // Set success message in session for PRG pattern
    $_SESSION['settings_message'] = __('settings_updated');
    if (isset($smtp_status)) {
        $_SESSION['smtp_status'] = $smtp_status;
    }

    // Redirect to prevent resubmission (PRG Pattern)
    header("Location: settings.php?active_tab=" . urlencode($active_tab));
    exit;
}

// Retrieve messages from session if they exist
if (isset($_SESSION['settings_message'])) {
    $message = $_SESSION['settings_message'];
    unset($_SESSION['settings_message']);
}
if (isset($_SESSION['smtp_status'])) {
    $smtp_status = $_SESSION['smtp_status'];
    unset($_SESSION['smtp_status']);
}

// Override active_tab from GET if available (from redirect)
if (isset($_GET['active_tab'])) {
    $active_tab = $_GET['active_tab'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex flex-col mb-8 gap-6">
            <h1 class="text-3xl font-bold"><?php echo __('settings'); ?></h1>

            <!-- Responsive Tabs Container -->
            <div class="w-full relative overflow-hidden">
                <div id="tabs-scroll"
                    class="flex overflow-x-auto pb-4 scrollbar-hide touch-pan-x -mx-4 px-4 md:mx-0 md:px-0"
                    style="scroll-behavior: smooth; -webkit-overflow-scrolling: touch;">
                    <div class="flex gap-2 whitespace-nowrap min-w-max">
                        <button onclick="switchTab('site')"
                            class="tab-btn active px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="site"><?php echo __('site_info'); ?></button>
                        <button onclick="switchTab('hero')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="hero"><?php echo __('hero_section'); ?></button>
                        <button onclick="switchTab('about')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="about"><?php echo __('about_section'); ?></button>
                        <button onclick="switchTab('contact')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="contact"><?php echo __('contact_info_settings'); ?></button>
                        <button onclick="switchTab('smtp')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="smtp"><?php echo __('smtp_settings'); ?></button>
                        <button onclick="switchTab('landing')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="landing"><?php echo __('landing_content'); ?></button>
                        <button onclick="switchTab('notifications')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="notifications"><?php echo __('notification_settings'); ?></button>
                    </div>
                </div>
                <!-- Edge Fades (Optional Visual Hint) -->
                <div class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-slate-900 to-transparent md:hidden opacity-0"
                    id="left-fade"></div>
                <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-slate-900 to-transparent md:hidden"
                    id="right-fade"></div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 border border-green-500/30 flex items-center">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="active_tab" id="active_tab_input"
                value="<?php echo htmlspecialchars($active_tab); ?>">

            <!-- Site Info Tab -->
            <div id="site-tab" class="tab-content glass-card p-6 md:p-8 rounded-2xl">
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-indigo-500 rounded-full mr-3"></span>
                            <?php echo __('site_info'); ?>
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_logo'); ?></label>
                                <input type="file" name="site_logo" class="setting-input text-xs">
                                <?php if (!empty($settings['site_logo'])): ?>
                                    <div class="flex items-center mt-2 gap-2">
                                        <img src="../uploads/<?php echo $settings['site_logo']; ?>" class="h-10 rounded">
                                        <button type="submit" name="delete_site_logo"
                                            class="p-2 bg-red-500/20 hover:bg-red-500/40 text-red-500 rounded-lg transition-colors border border-red-500/20"
                                            title="<?php echo __('delete'); ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_favicon'); ?></label>
                                <input type="file" name="site_favicon" class="setting-input text-xs">
                                <?php if (!empty($settings['site_favicon'])): ?>
                                    <div class="flex items-center mt-2 gap-2">
                                        <img src="../uploads/<?php echo $settings['site_favicon']; ?>" class="h-8 rounded">
                                        <button type="submit" name="delete_site_favicon"
                                            class="p-2 bg-red-500/20 hover:bg-red-500/40 text-red-500 rounded-lg transition-colors border border-red-500/20"
                                            title="<?php echo __('delete'); ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_name_ar'); ?></label>
                            <input type="text" name="site_name_ar"
                                value="<?php echo $settings['site_name_ar'] ?? 'الصراف الذكي'; ?>"
                                class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_name_en'); ?></label>
                            <input type="text" name="site_name_en"
                                value="<?php echo $settings['site_name_en'] ?? 'SmartExchange'; ?>"
                                class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('footer_description_ar'); ?></label>
                            <textarea name="footer_description_ar" rows="2"
                                class="setting-input"><?php echo $settings['footer_description_ar'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('footer_description_en'); ?></label>
                            <textarea name="footer_description_en" rows="2"
                                class="setting-input"><?php echo $settings['footer_description_en'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_email'); ?></label>
                            <input type="email" name="contact_email"
                                value="<?php echo $settings['contact_email'] ?? 'admin@example.com'; ?>"
                                class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_form_email'); ?></label>
                            <input type="email" name="contact_form_email"
                                value="<?php echo $settings['contact_form_email'] ?? ($settings['contact_email'] ?? ''); ?>"
                                class="setting-input">
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center opacity-0">...</h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('maintenance_mode'); ?></label>
                            <select name="maintenance_mode" class="setting-input">
                                <option value="0" <?php echo ($settings['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>><?php echo __('off_live'); ?></option>
                                <option value="1" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>><?php echo __('on_maintenance'); ?></option>
                            </select>
                        </div>

                        <!-- Maintenance Messages -->
                        <div x-show="document.querySelector('select[name=maintenance_mode]').value == '1'"
                            class="space-y-4 pt-2 border-t border-gray-700/50 mt-2 mb-4">
                            <div>
                                <label class="block text-gray-400 text-sm font-medium mb-2">
                                    <?php echo __('maintenance_mode') . ' (' . __('arabic') . ')'; ?>
                                </label>
                                <textarea name="maintenance_message_ar" rows="3" class="setting-input"
                                    placeholder="<?php echo __('maintenance_placeholder'); ?>"><?php echo $settings['maintenance_message_ar'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm font-medium mb-2">
                                    <?php echo __('maintenance_mode') . ' (' . __('english') . ')'; ?>
                                </label>
                                <textarea name="maintenance_message_en" rows="3" class="setting-input"
                                    placeholder="<?php echo __('maintenance_placeholder'); ?>"><?php echo $settings['maintenance_message_en'] ?? ''; ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-4 bg-indigo-500/5 rounded-xl border border-indigo-500/10">
                            <input type="hidden" name="enable_scroll_top" value="0">
                            <input type="checkbox" name="enable_scroll_top" value="1" id="enable_scroll_top" <?php echo ($settings['enable_scroll_top'] ?? '1') == '1' ? 'checked' : ''; ?>
                                class="w-5 h-5 rounded border-gray-700 bg-gray-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-gray-900">
                            <label for="enable_scroll_top"
                                class="text-gray-300 text-sm font-medium cursor-pointer selection:none select-none"><?php echo __('enable_scroll_top'); ?></label>
                        </div>
                        <div>
                            <textarea name="announcement" rows="3"
                                class="setting-input"><?php echo $settings['announcement'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2">
                                <?php echo __('sim_title'); ?>
                            </label>
                            <input type="number" name="exchange_timer"
                                value="<?php echo $settings['exchange_timer'] ?? '30'; ?>" class="setting-input" min="1"
                                max="120">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hero Section Tab -->
            <div id="hero-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-purple-500 rounded-full mr-3"></span>
                            <?php echo __('hero_section'); ?> (AR)
                        </h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_title_ar'); ?></label>
                            <input type="text" name="hero_title_ar"
                                value="<?php echo $settings['hero_title_ar'] ?? ''; ?>" class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_feature_ar'); ?></label>
                            <input type="text" name="hero_feature_ar"
                                value="<?php echo $settings['hero_feature_ar'] ?? 'سريع وآمن'; ?>"
                                class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_subtitle_ar'); ?></label>
                            <textarea name="hero_subtitle_ar" rows="3"
                                class="setting-input"><?php echo $settings['hero_subtitle_ar'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-purple-400 rounded-full mr-3"></span>
                            <?php echo __('hero_section'); ?> (EN)
                        </h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_title_en'); ?></label>
                            <input type="text" name="hero_title_en"
                                value="<?php echo $settings['hero_title_en'] ?? ''; ?>" class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_feature_en'); ?></label>
                            <input type="text" name="hero_feature_en"
                                value="<?php echo $settings['hero_feature_en'] ?? 'Fast & Secure'; ?>"
                                class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_subtitle_en'); ?></label>
                            <textarea name="hero_subtitle_en" rows="3"
                                class="setting-input"><?php echo $settings['hero_subtitle_en'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- About Us Tab -->
            <div id="about-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-blue-500 rounded-full mr-3"></span>
                            <?php echo __('about_section'); ?> (AR)
                        </h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_image'); ?></label>
                            <input type="file" name="about_image" class="setting-input text-xs mb-2">
                            <?php if (!empty($settings['about_image'])): ?>
                                <div class="relative group/img inline-block mb-4">
                                    <img src="../uploads/<?php echo $settings['about_image']; ?>"
                                        class="h-32 rounded-xl border border-white/10 shadow-lg">
                                    <button type="submit" name="delete_about_image"
                                        class="absolute -top-2 -right-2 p-1.5 bg-red-600 text-white rounded-full shadow-lg hover:bg-red-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2">
                                <?php echo __('about_title_ar'); ?>
                            </label>
                            <input type="text" name="about_title_ar"
                                value="<?php echo $settings['about_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="من نحن">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_subtitle_ar'); ?></label>
                            <input type="text" name="about_subtitle_ar"
                                value="<?php echo $settings['about_subtitle_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="شريكك الذكي في عالم التسويق الرقمي">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_desc_ar'); ?></label>
                            <textarea name="about_desc_ar" rows="3"
                                class="setting-input"><?php echo $settings['about_desc_ar'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2">
                                <?php echo __('about_btn_ar'); ?>
                            </label>
                            <input type="text" name="about_btn_ar"
                                value="<?php echo $settings['about_btn_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="عرض الكل">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('mission_ar'); ?></label>
                            <textarea name="mission_ar" rows="2"
                                class="setting-input"><?php echo $settings['mission_ar'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('vision_ar'); ?></label>
                            <textarea name="vision_ar" rows="2"
                                class="setting-input"><?php echo $settings['vision_ar'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-blue-400 rounded-full mr-3"></span>
                            <?php echo __('about_section'); ?> (EN)
                        </h3>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2">
                                <?php echo __('about_title_en'); ?>
                            </label>
                            <input type="text" name="about_title_en"
                                value="<?php echo $settings['about_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="About Us">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_subtitle_en'); ?></label>
                            <input type="text" name="about_subtitle_en"
                                value="<?php echo $settings['about_subtitle_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Your Smart Partner...">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_desc_en'); ?></label>
                            <textarea name="about_desc_en" rows="3"
                                class="setting-input"><?php echo $settings['about_desc_en'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2">
                                <?php echo __('about_btn_en'); ?>
                            </label>
                            <input type="text" name="about_btn_en"
                                value="<?php echo $settings['about_btn_en'] ?? ''; ?>" class="setting-input"
                                placeholder="View All">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('mission_en'); ?></label>
                            <textarea name="mission_en" rows="2"
                                class="setting-input"><?php echo $settings['mission_en'] ?? ''; ?></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('vision_en'); ?></label>
                            <textarea name="vision_en" rows="2"
                                class="setting-input"><?php echo $settings['vision_en'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Tab -->
            <div id="contact-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-pink-500 rounded-full mr-3"></span>
                            <?php echo __('contact_info_settings'); ?>
                        </h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_phone'); ?></label>
                            <input type="text" name="contact_phone"
                                value="<?php echo $settings['contact_phone'] ?? ''; ?>" class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_address_ar'); ?></label>
                            <input type="text" name="contact_address_ar"
                                value="<?php echo $settings['contact_address_ar'] ?? ''; ?>" class="setting-input">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_address_en'); ?></label>
                            <input type="text" name="contact_address_en"
                                value="<?php echo $settings['contact_address_en'] ?? ''; ?>" class="setting-input">
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-pink-400 rounded-full mr-3"></span>
                            <?php echo __('social_links'); ?>
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('facebook'); ?></label>
                                <input type="text" name="social_facebook"
                                    value="<?php echo $settings['social_facebook'] ?? ''; ?>"
                                    class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('twitter'); ?></label>
                                <input type="text" name="social_twitter"
                                    value="<?php echo $settings['social_twitter'] ?? ''; ?>" class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('instagram'); ?></label>
                                <input type="text" name="social_instagram"
                                    value="<?php echo $settings['social_instagram'] ?? ''; ?>"
                                    class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('telegram'); ?></label>
                                <input type="text" name="social_telegram"
                                    value="<?php echo $settings['social_telegram'] ?? ''; ?>"
                                    class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('whatsapp'); ?></label>
                                <input type="text" name="social_whatsapp"
                                    value="<?php echo $settings['social_whatsapp'] ?? ''; ?>"
                                    class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('youtube'); ?></label>
                                <input type="text" name="social_youtube"
                                    value="<?php echo $settings['social_youtube'] ?? ''; ?>" class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('linkedin'); ?></label>
                                <input type="text" name="social_linkedin"
                                    value="<?php echo $settings['social_linkedin'] ?? ''; ?>"
                                    class="setting-input py-2">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-500 text-xs font-medium mb-1 uppercase"><?php echo __('tiktok'); ?></label>
                                <input type="text" name="social_tiktok"
                                    value="<?php echo $settings['social_tiktok'] ?? ''; ?>" class="setting-input py-2">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP Settings Tab -->
            <div id="smtp-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <span class="w-2 h-6 bg-yellow-500 rounded-full mr-3"></span>
                            <?php echo __('smtp_configuration'); ?>
                        </h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_host'); ?></label>
                            <input type="text" name="smtp_host" value="<?php echo $settings['smtp_host'] ?? ''; ?>"
                                class="setting-input" placeholder="smtp.example.com">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_port'); ?></label>
                            <input type="text" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? '587'; ?>"
                                class="setting-input" placeholder="587">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_encryption'); ?></label>
                            <select name="smtp_encryption" class="setting-input">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center opacity-0">...</h3>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_username'); ?></label>
                            <input type="text" name="smtp_username"
                                value="<?php echo $settings['smtp_username'] ?? ''; ?>" class="setting-input"
                                placeholder="user@example.com">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_password'); ?></label>
                            <input type="password" name="smtp_password"
                                value="<?php echo $settings['smtp_password'] ?? ''; ?>" class="setting-input"
                                placeholder="••••••••">
                        </div>
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('smtp_from_name'); ?></label>
                            <input type="text" name="smtp_from_name"
                                value="<?php echo $settings['smtp_from_name'] ?? ''; ?>" class="setting-input"
                                placeholder="<?php echo __('site_name'); ?>">
                        </div>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-white/5">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <span class="w-2 h-6 bg-emerald-500 rounded-full mr-3"></span>
                        <?php echo __('test_smtp_settings'); ?>
                    </h3>

                    <?php if (isset($smtp_status)): ?>
                        <div
                            class="mb-4 p-4 rounded-xl border <?php echo $smtp_status['success'] ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400'; ?>">
                            <?php echo $smtp_status['message']; ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="flex-1 w-full">
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('test_email_address'); ?></label>
                            <input type="email" name="test_smtp_email" class="setting-input"
                                placeholder="test@example.com">
                        </div>
                        <button type="submit" name="test_smtp"
                            class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-emerald-600/20 whitespace-nowrap">
                            <?php echo __('send_test_email'); ?>
                        </button>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-indigo-500/10 rounded-xl border border-indigo-500/20">
                    <p class="text-sm text-indigo-300">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo __('smtp_info_note'); ?>
                    </p>
                </div>
            </div>

            <!-- Landing Content Tab -->
            <div id="landing-tab" class="tab-content hidden space-y-8">
                <!-- Features Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-indigo-500 rounded-full"></span>
                        <?php echo __('features_section'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <label class="block text-gray-500 text-xs font-bold mb-1 uppercase">
                                <?php echo __('features_title_ar'); ?>
                            </label>
                            <input type="text" name="features_title_ar"
                                value="<?php echo $settings['features_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="لماذا تستخدم أداتنا؟">
                        </div>
                        <div>
                            <label class="block text-gray-500 text-xs font-bold mb-1 uppercase">
                                <?php echo __('features_title_en'); ?>
                            </label>
                            <input type="text" name="features_title_en"
                                value="<?php echo $settings['features_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Why Choose Us?">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-3 gap-6">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="space-y-4 p-4 bg-white/5 rounded-xl border border-white/5">
                                <h4 class="text-indigo-400 font-bold border-b border-white/5 pb-2 mb-4">
                                    <?php echo __('feature') . ' ' . $i; ?>
                                </h4>
                                <!-- Icon Selection -->
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-2 uppercase"><?php echo __('choose_icon'); ?></label>
                                    <select name="feature_<?php echo $i; ?>_icon" class="setting-input text-sm">
                                        <option
                                            value="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' ? 'selected' : ''; ?>>🏢 Office / Targeting</option>
                                        <option
                                            value="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' ? 'selected' : ''; ?>>🔒 Security / Lock</option>
                                        <option value="M13 10V3L4 14h7v7l9-11h-7z" <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M13 10V3L4 14h7v7l9-11h-7z' ? 'selected' : ''; ?>>⚡ Speed /
                                            Lightning</option>
                                        <option value="M21 12a9 9 0 11-18 0 9 9 0 0118 0z M12 12a3 3 0 100-6 3 3 0 000 6z"
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M21 12a9 9 0 11-18 0 9 9 0 0118 0z M12 12a3 3 0 100-6 3 3 0 000 6z' ? 'selected' : ''; ?>>🎯 Target
                                        </option>
                                        <option
                                            value="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' ? 'selected' : ''; ?>>📊 Analytics</option>
                                        <option value="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4' ? 'selected' : ''; ?>>📥 Export / Download
                                        </option>
                                        <option
                                            value="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' ? 'selected' : ''; ?>>👥 Users</option>
                                    </select>
                                </div>

                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('arabic'); ?></label>
                                    <input type="text" name="feature_<?php echo $i; ?>_title_ar"
                                        value="<?php echo $settings['feature_' . $i . '_title_ar'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="العنوان">
                                    <textarea name="feature_<?php echo $i; ?>_desc_ar" rows="2"
                                        class="setting-input text-sm"
                                        placeholder="الوصف"><?php echo $settings['feature_' . $i . '_desc_ar'] ?? ''; ?></textarea>
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('english'); ?></label>
                                    <input type="text" name="feature_<?php echo $i; ?>_title_en"
                                        value="<?php echo $settings['feature_' . $i . '_title_en'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="Title">
                                    <textarea name="feature_<?php echo $i; ?>_desc_en" rows="2"
                                        class="setting-input text-sm"
                                        placeholder="Description"><?php echo $settings['feature_' . $i . '_desc_en'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Services Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-pink-500 rounded-full"></span>
                        <?php echo __('services_section'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('services_title_ar'); ?></label>
                            <input type="text" name="services_title_ar"
                                value="<?php echo $settings['services_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="خدماتنا">
                        </div>
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('services_title_en'); ?></label>
                            <input type="text" name="services_title_en"
                                value="<?php echo $settings['services_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Our Services">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-3 gap-6">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <div class="space-y-4 p-4 bg-white/5 rounded-xl border border-white/5">
                                <h4
                                    class="text-pink-400 font-bold border-b border-white/5 pb-2 mb-4 flex justify-between items-center">
                                    <span><?php echo __('service') . ' ' . $i; ?></span>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="service_<?php echo $i; ?>_featured" value="0">
                                        <input type="checkbox" name="service_<?php echo $i; ?>_featured" value="1" <?php echo (getSetting('service_' . $i . '_featured') == '1') ? 'checked' : ''; ?>
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600">
                                        </div>
                                        <span
                                            class="ms-3 text-xs font-medium text-gray-500"><?php echo __('featured_service'); ?></span>
                                    </label>
                                </h4>
                                <!-- Icon Selection -->
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-2 uppercase"><?php echo __('choose_icon'); ?></label>
                                    <select name="service_<?php echo $i; ?>_icon" class="setting-input text-sm">
                                        <option value="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' ? 'selected' : ''; ?>>🛍️ Marketing / Shop</option>
                                        <option value="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' ? 'selected' : ''; ?>>💻 Programming / Code</option>
                                        <option
                                            value="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                            <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' ? 'selected' : ''; ?>>📱 Social Media / Users</option>
                                        <option
                                            value="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' ? 'selected' : ''; ?>>🎨
                                            Design / Image</option>
                                        <option
                                            value="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"
                                            <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z' ? 'selected' : ''; ?>>📈 Growth / Chart</option>
                                        <option
                                            value="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                            <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' ? 'selected' : ''; ?>>💡 Idea
                                            / Lightbulb</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('arabic'); ?></label>
                                    <input type="text" name="service_<?php echo $i; ?>_title_ar"
                                        value="<?php echo $settings['service_' . $i . '_title_ar'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="اسم الخدمة">
                                    <textarea name="service_<?php echo $i; ?>_desc_ar" rows="4"
                                        class="setting-input text-sm mb-2"
                                        placeholder="أدخل المزايا (كل سطر يعتبر نقطة)"><?php echo $settings['service_' . $i . '_desc_ar'] ?? ''; ?></textarea>
                                    <input type="text" name="service_<?php echo $i; ?>_btn_ar"
                                        value="<?php echo $settings['service_' . $i . '_btn_ar'] ?? ''; ?>"
                                        class="setting-input text-xs" placeholder="نص الزر (مثل: اشترك الآن)">
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('english'); ?></label>
                                    <input type="text" name="service_<?php echo $i; ?>_title_en"
                                        value="<?php echo $settings['service_' . $i . '_title_en'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="Service Name">
                                    <textarea name="service_<?php echo $i; ?>_desc_en" rows="4"
                                        class="setting-input text-sm mb-2"
                                        placeholder="Enter features (One per line)"><?php echo $settings['service_' . $i . '_desc_en'] ?? ''; ?></textarea>
                                    <input type="text" name="service_<?php echo $i; ?>_btn_en"
                                        value="<?php echo $settings['service_' . $i . '_btn_en'] ?? ''; ?>"
                                        class="setting-input text-xs" placeholder="Button Label (e.g. Get Started)">
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Steps Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-purple-500 rounded-full"></span>
                        <?php echo __('steps_section'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <label class="block text-gray-500 text-xs font-bold mb-1 uppercase">
                                <?php echo __('how_it_works_title_ar'); ?>
                            </label>
                            <input type="text" name="how_it_works_title_ar"
                                value="<?php echo $settings['how_it_works_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="خطوات العمل">
                        </div>
                        <div>
                            <label class="block text-gray-500 text-xs font-bold mb-1 uppercase">
                                <?php echo __('how_it_works_title_en'); ?>
                            </label>
                            <input type="text" name="how_it_works_title_en"
                                value="<?php echo $settings['how_it_works_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="How It Works">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-3 gap-6">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="space-y-4 p-4 bg-white/5 rounded-xl border border-white/5">
                                <h4 class="text-purple-400 font-bold border-b border-white/5 pb-2 mb-4">
                                    <?php echo __('step') . ' ' . $i; ?>
                                </h4>
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('arabic'); ?></label>
                                    <input type="text" name="step_<?php echo $i; ?>_title_ar"
                                        value="<?php echo $settings['step_' . $i . '_title_ar'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="العنوان">
                                    <textarea name="step_<?php echo $i; ?>_desc_ar" rows="2" class="setting-input text-sm"
                                        placeholder="الوصف"><?php echo $settings['step_' . $i . '_desc_ar'] ?? ''; ?></textarea>
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('english'); ?></label>
                                    <input type="text" name="step_<?php echo $i; ?>_title_en"
                                        value="<?php echo $settings['step_' . $i . '_title_en'] ?? ''; ?>"
                                        class="setting-input mb-2" placeholder="Title">
                                    <textarea name="step_<?php echo $i; ?>_desc_en" rows="2" class="setting-input text-sm"
                                        placeholder="Description"><?php echo $settings['step_' . $i . '_desc_en'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-emerald-500 rounded-full"></span>
                        <?php echo __('stats_section'); ?>
                    </h3>
                    <div class="grid md:grid-cols-4 gap-4">
                        <?php
                        $stats = ['users', 'leads', 'satisfaction', 'support'];
                        foreach ($stats as $stat):
                            ?>
                            <div class="p-4 bg-white/5 rounded-xl border border-white/5">
                                <label
                                    class="block text-emerald-400 text-xs font-bold mb-2 uppercase"><?php echo __('stat_' . $stat); ?></label>
                                <input type="text" name="stat_<?php echo $stat; ?>_value"
                                    value="<?php echo $settings['stat_' . $stat . '_value'] ?? ''; ?>"
                                    class="setting-input mb-2 font-mono" placeholder="5K+, 99%, etc">
                                <div class="space-y-2 text-xs">
                                    <input type="text" name="stat_<?php echo $stat; ?>_ar"
                                        value="<?php echo $settings['stat_' . $stat . '_ar'] ?? ''; ?>"
                                        class="setting-input py-1.5" placeholder="التسمية (عربي)">
                                    <input type="text" name="stat_<?php echo $stat; ?>_en"
                                        value="<?php echo $settings['stat_' . $stat . '_en'] ?? ''; ?>"
                                        class="setting-input py-1.5" placeholder="Label (English)">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Testimonials Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-indigo-400 rounded-full"></span>
                        <?php echo __('testimonials'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('testimonials_title_ar'); ?></label>
                            <input type="text" name="testimonials_title_ar"
                                value="<?php echo $settings['testimonials_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="قصص نجاح">
                        </div>
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('testimonials_title_en'); ?></label>
                            <input type="text" name="testimonials_title_en"
                                value="<?php echo $settings['testimonials_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Success Stories">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <div class="p-4 bg-white/5 rounded-xl border border-white/5 space-y-4">
                                <h4 class="text-indigo-400 font-bold border-b border-white/5 pb-2 mb-2 text-sm uppercase">
                                    <?php echo __('testimonial') . ' ' . $i; ?>
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <h5 class="text-[10px] text-gray-500 font-black uppercase">
                                            <?php echo __('arabic'); ?>
                                        </h5>
                                        <textarea name="testimonial_<?php echo $i; ?>_content_ar" rows="3"
                                            class="setting-input text-xs"
                                            placeholder="نص الرأي"><?php echo $settings['testimonial_' . $i . '_content_ar'] ?? ''; ?></textarea>
                                        <input type="text" name="testimonial_<?php echo $i; ?>_author_ar"
                                            value="<?php echo $settings['testimonial_' . $i . '_author_ar'] ?? ''; ?>"
                                            class="setting-input text-xs" placeholder="اسم العميل">
                                    </div>
                                    <div class="space-y-3">
                                        <h5 class="text-[10px] text-gray-500 font-black uppercase">
                                            <?php echo __('english'); ?>
                                        </h5>
                                        <textarea name="testimonial_<?php echo $i; ?>_content_en" rows="3"
                                            class="setting-input text-xs"
                                            placeholder="Testimonial Content"><?php echo $settings['testimonial_' . $i . '_content_en'] ?? ''; ?></textarea>
                                        <input type="text" name="testimonial_<?php echo $i; ?>_author_en"
                                            value="<?php echo $settings['testimonial_' . $i . '_author_en'] ?? ''; ?>"
                                            class="setting-input text-xs" placeholder="Author Name">
                                    </div>
                                </div>
                                <!-- Avatar Upload -->
                                <div class="pt-4 border-t border-white/5">
                                    <label
                                        class="block text-[10px] text-gray-500 font-black uppercase mb-3"><?php echo __('testimonial_image'); ?></label>
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-12 h-12 rounded-xl bg-white/5 border border-white/5 overflow-hidden flex-shrink-0">
                                            <?php if (!empty($settings['testimonial_' . $i . '_image'])): ?>
                                                <img src="../uploads/<?php echo $settings['testimonial_' . $i . '_image']; ?>"
                                                    class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-gray-600">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                        </path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 space-y-2">
                                            <input type="file" name="testimonial_<?php echo $i; ?>_image" accept="image/*"
                                                class="w-full text-[10px] text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-indigo-600/20 file:text-indigo-400 hover:file:bg-indigo-600/30">
                                            <?php if (!empty($settings['testimonial_' . $i . '_image'])): ?>
                                                <button type="submit" name="delete_testimonial_<?php echo $i; ?>_image"
                                                    class="text-[10px] text-red-400 hover:text-red-300 font-bold underline transition-colors">
                                                    Delete Image
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-yellow-400 rounded-full"></span>
                        <?php echo __('faqs'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('faqs_title_ar'); ?></label>
                            <input type="text" name="faqs_title_ar"
                                value="<?php echo $settings['faqs_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="الأسئلة الشائعة">
                        </div>
                        <div>
                            <label
                                class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('faqs_title_en'); ?></label>
                            <input type="text" name="faqs_title_en"
                                value="<?php echo $settings['faqs_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Frequently Asked Questions">
                        </div>
                    </div>

                    <div class="space-y-6">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="p-4 bg-white/5 rounded-xl border border-white/5 space-y-4">
                                <h4 class="text-yellow-400 font-bold border-b border-white/5 pb-2 mb-2 text-sm uppercase">
                                    <?php echo __('faq') . ' ' . $i; ?>
                                </h4>
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div class="space-y-3">
                                        <h5 class="text-[10px] text-gray-500 font-black uppercase">
                                            <?php echo __('arabic'); ?>
                                        </h5>
                                        <input type="text" name="faq_<?php echo $i; ?>_q_ar"
                                            value="<?php echo $settings['faq_' . $i . '_q_ar'] ?? ''; ?>"
                                            class="setting-input text-xs" placeholder="السؤال">
                                        <textarea name="faq_<?php echo $i; ?>_a_ar" rows="2" class="setting-input text-xs"
                                            placeholder="الإجابة"><?php echo $settings['faq_' . $i . '_a_ar'] ?? ''; ?></textarea>
                                    </div>
                                    <div class="space-y-3">
                                        <h5 class="text-[10px] text-gray-500 font-black uppercase">
                                            <?php echo __('english'); ?>
                                        </h5>
                                        <input type="text" name="faq_<?php echo $i; ?>_q_en"
                                            value="<?php echo $settings['faq_' . $i . '_q_en'] ?? ''; ?>"
                                            class="setting-input text-xs" placeholder="Question">
                                        <textarea name="faq_<?php echo $i; ?>_a_en" rows="2" class="setting-input text-xs"
                                            placeholder="Answer"><?php echo $settings['faq_' . $i . '_a_en'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-pink-500 rounded-full"></span>
                        <?php echo __('cta_section'); ?>
                    </h3>
                    <div class="grid md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <h4 class="text-pink-400 text-sm font-bold uppercase"><?php echo __('arabic'); ?></h4>
                            <input type="text" name="cta_title_ar"
                                value="<?php echo $settings['cta_title_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="العنوان الكبير">
                            <textarea name="cta_subtitle_ar" rows="2" class="setting-input"
                                placeholder="العنوان الفرعي"><?php echo $settings['cta_subtitle_ar'] ?? ''; ?></textarea>
                            <input type="text" name="cta_button_ar"
                                value="<?php echo $settings['cta_button_ar'] ?? ''; ?>" class="setting-input"
                                placeholder="نص الزر">
                        </div>
                        <div class="space-y-4">
                            <h4 class="text-pink-400 text-sm font-bold uppercase"><?php echo __('english'); ?></h4>
                            <input type="text" name="cta_title_en"
                                value="<?php echo $settings['cta_title_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Main Title">
                            <textarea name="cta_subtitle_en" rows="2" class="setting-input"
                                placeholder="Subtitle"><?php echo $settings['cta_subtitle_en'] ?? ''; ?></textarea>
                            <input type="text" name="cta_button_en"
                                value="<?php echo $settings['cta_button_en'] ?? ''; ?>" class="setting-input"
                                placeholder="Button Text">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Settings Tab -->
            <div id="notifications-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                    <span class="w-2 h-6 bg-red-500 rounded-full mr-3"></span>
                    <?php echo __('email_notifications_control'); ?>
                </h3>

                <div class="grid md:grid-cols-2 gap-8">
                    <!-- Registration Notifications -->
                    <div class="space-y-4">
                        <h4 class="text-indigo-400 font-medium border-b border-indigo-500/20 pb-2 mb-4">
                            <?php echo __('registration_events'); ?>
                        </h4>

                        <div
                            class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h5 class="font-medium text-white"><?php echo __('notify_admin_new_user'); ?></h5>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_admin_new_user_desc'); ?>
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="notify_new_user_admin" value="0">
                                <input type="checkbox" name="notify_new_user_admin" value="1" class="sr-only peer" <?php echo ($settings['notify_new_user_admin'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div
                                    class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                            </label>
                        </div>

                        <div
                            class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h5 class="font-medium text-white"><?php echo __('notify_client_welcome'); ?></h5>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_client_welcome_desc'); ?>
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="notify_new_user_client" value="0">
                                <input type="checkbox" name="notify_new_user_client" value="1" class="sr-only peer"
                                    <?php echo ($settings['notify_new_user_client'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div
                                    class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Exchange Notifications -->
                    <div class="space-y-4">
                        <h4 class="text-indigo-400 font-medium border-b border-indigo-500/20 pb-2 mb-4">
                            <?php echo __('exchange_events'); ?>
                        </h4>

                        <div
                            class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h5 class="font-medium text-white"><?php echo __('notify_admin_new_order'); ?></h5>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_admin_new_order_desc'); ?>
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="notify_new_exchange_admin" value="0">
                                <input type="checkbox" name="notify_new_exchange_admin" value="1" class="sr-only peer"
                                    <?php echo ($settings['notify_new_exchange_admin'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div
                                    class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                            </label>
                        </div>

                        <div
                            class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h5 class="font-medium text-white"><?php echo __('notify_client_new_order'); ?></h5>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_client_new_order_desc'); ?>
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="notify_new_exchange_user" value="0">
                                <input type="checkbox" name="notify_new_exchange_user" value="1" class="sr-only peer"
                                    <?php echo ($settings['notify_new_exchange_user'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div
                                    class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                            </label>
                        </div>

                        <div
                            class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h5 class="font-medium text-white"><?php echo __('notify_client_status_change'); ?></h5>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo __('notify_client_status_change_desc'); ?>
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="notify_exchange_status_user" value="0">
                                <input type="checkbox" name="notify_exchange_status_user" value="1" class="sr-only peer"
                                    <?php echo ($settings['notify_exchange_status_user'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div
                                    class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-red-500/10 rounded-xl border border-red-500/20">
                    <p class="text-sm text-red-300">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                        <?php echo __('security_note'); ?>: <?php echo __('forgot_password_always_active'); ?>
                    </p>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit"
                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold px-12 py-4 rounded-2xl shadow-xl shadow-indigo-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <?php echo __('save_changes'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .setting-input {
        width: 100%;
        background: rgba(17, 24, 39, 0.5);
        border: 1px solid rgba(55, 65, 81, 0.5);
        border-radius: 1rem;
        padding: 0.75rem 1rem;
        color: white;
        transition: all 0.3s;
    }

    .setting-input:focus {
        outline: none;
        border-color: #6366f1;
        background: rgba(17, 24, 39, 0.8);
    }

    .tab-btn {
        color: #9ca3af;
    }

    .tab-btn.active {
        background: rgba(99, 102, 241, 0.2);
        color: #818cf8;
        border-color: rgba(99, 102, 241, 0.3);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
    }

    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<script>
    function switchTab(tabId) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
        // Show selected tab
        const targetTab = document.getElementById(tabId + '-tab');
        if (targetTab) targetTab.classList.remove('hidden');

        // Update button styles
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        const targetBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if (targetBtn) targetBtn.classList.add('active');

        // Update hidden input for persistence
        const input = document.getElementById('active_tab_input');
        if (input) input.value = tabId;
    }

    // Restore active tab on load
    window.addEventListener('DOMContentLoaded', () => {
        const savedTab = "<?php echo $active_tab; ?>";
        switchTab(savedTab);
    });

    // Scroll indicators for tabs
    const tabsScroll = document.getElementById('tabs-scroll');
    const leftFade = document.getElementById('left-fade');
    const rightFade = document.getElementById('right-fade');

    if (tabsScroll) {
        const updateFades = () => {
            const scrollLeft = tabsScroll.scrollLeft;
            const maxScroll = tabsScroll.scrollWidth - tabsScroll.clientWidth;

            if (leftFade) leftFade.style.opacity = scrollLeft > 10 ? '1' : '0';
            if (rightFade) rightFade.style.opacity = scrollLeft < maxScroll - 10 ? '1' : '0';
        };

        tabsScroll.addEventListener('scroll', updateFades);
        window.addEventListener('resize', updateFades);
        // Initial check
        setTimeout(updateFades, 100);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>