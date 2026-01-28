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

// Image Compression Function
function compressAndResizeImage($source_path, $destination_path, $max_width = 1200, $quality = 85)
{
    // Get image info
    $image_info = getimagesize($source_path);
    if (!$image_info)
        return false;

    $mime_type = $image_info['mime'];
    $width = $image_info[0];
    $height = $image_info[1];

    // Create image resource based on type
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }

    if (!$source)
        return false;

    // Calculate new dimensions
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = intval(($height / $width) * $max_width);
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // Create new image
    $destination = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG and GIF
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
    }

    // Resize
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save compressed image
    $result = false;
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $result = imagejpeg($destination, $destination_path, $quality);
            break;
        case 'image/png':
            // PNG quality is 0-9 (0 = no compression, 9 = max compression)
            $png_quality = intval(9 - ($quality / 100) * 9);
            $result = imagepng($destination, $destination_path, $png_quality);
            break;
        case 'image/gif':
            $result = imagegif($destination, $destination_path);
            break;
        case 'image/webp':
            $result = imagewebp($destination, $destination_path, $quality);
            break;
    }

    // Free memory
    imagedestroy($source);
    imagedestroy($destination);

    return $result;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Portfolio Item Management
    if (isset($_POST['add_portfolio'])) {
        $t_ar = $_POST['p_title_ar'] ?? '';
        $t_en = $_POST['p_title_en'] ?? '';
        $d_ar = $_POST['p_desc_ar'] ?? '';
        $d_en = $_POST['p_desc_en'] ?? '';
        $type = $_POST['p_type'] ?? 'image';
        $order = (int) ($_POST['p_order'] ?? 0);
        $preview = $_POST['p_preview'] ?? '';
        $content = '';

        if ($type == 'iframe') {
            $content = $_POST['p_iframe_url'] ?? '';
        } else {
            if (isset($_FILES['p_image_file']) && $_FILES['p_image_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['p_image_file']['name'], PATHINFO_EXTENSION));
                $filename = 'portfolio_' . time() . '.' . $ext;
                $temp_path = $_FILES['p_image_file']['tmp_name'];
                $final_path = $upload_dir . $filename;

                // Compress and resize image
                if (compressAndResizeImage($temp_path, $final_path, 1200, 85)) {
                    $content = $filename;
                } elseif (move_uploaded_file($temp_path, $final_path)) {
                    // Fallback if compression fails
                    $content = $filename;
                }
            }
        }

        if (!empty($content)) {
            $cat_ar = $_POST['p_category_ar'] ?? '';
            $cat_en = $_POST['p_category_en'] ?? '';
            $pdo->prepare("INSERT INTO portfolio_items (title_ar, title_en, description_ar, description_en, category_ar, category_en, item_type, content_url, preview_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$t_ar, $t_en, $d_ar, $d_en, $cat_ar, $cat_en, $type, $content, $preview, $order]);
            $_SESSION['settings_message'] = __('work_added_success');
            header("Location: settings.php?active_tab=portfolio");
            exit;
        }
    }

    if (isset($_POST['edit_portfolio'])) {
        $id = (int) $_POST['p_id'];
        $t_ar = $_POST['p_title_ar'] ?? '';
        $t_en = $_POST['p_title_en'] ?? '';
        $d_ar = $_POST['p_desc_ar'] ?? '';
        $d_en = $_POST['p_desc_en'] ?? '';
        $cat_ar = $_POST['p_category_ar'] ?? '';
        $cat_en = $_POST['p_category_en'] ?? '';
        $type = $_POST['p_type'] ?? 'image';
        $order = (int) ($_POST['p_order'] ?? 0);
        $preview = $_POST['p_preview'] ?? '';

        $stmt = $pdo->prepare("UPDATE portfolio_items SET title_ar=?, title_en=?, description_ar=?, description_en=?, category_ar=?, category_en=?, item_type=?, preview_url=?, display_order=? WHERE id=?");
        $stmt->execute([$t_ar, $t_en, $d_ar, $d_en, $cat_ar, $cat_en, $type, $preview, $order, $id]);

        if ($type == 'iframe' && !empty($_POST['p_iframe_url'])) {
            $pdo->prepare("UPDATE portfolio_items SET content_url=? WHERE id=?")->execute([$_POST['p_iframe_url'], $id]);
        } elseif ($type == 'image' && isset($_FILES['p_image_file']) && $_FILES['p_image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['p_image_file']['name'], PATHINFO_EXTENSION));
            $filename = 'portfolio_' . time() . '.' . $ext;
            $temp_path = $_FILES['p_image_file']['tmp_name'];
            $final_path = $upload_dir . $filename;

            // Compress and resize image
            if (compressAndResizeImage($temp_path, $final_path, 1200, 85)) {
                $pdo->prepare("UPDATE portfolio_items SET content_url=? WHERE id=?")->execute([$filename, $id]);
            } elseif (move_uploaded_file($temp_path, $final_path)) {
                // Fallback if compression fails
                $pdo->prepare("UPDATE portfolio_items SET content_url=? WHERE id=?")->execute([$filename, $id]);
            }
        }

        $_SESSION['settings_message'] = __('settings_updated');
        header("Location: settings.php?active_tab=portfolio");
        exit;
    }

    if (isset($_POST['delete_portfolio_id'])) {
        $id = (int) $_POST['delete_portfolio_id'];
        $stmt = $pdo->prepare("SELECT * FROM portfolio_items WHERE id = ?");
        $stmt->execute([$id]);
        $ritem = $stmt->fetch();
        if ($ritem && $ritem['item_type'] == 'image') {
            @unlink($upload_dir . $ritem['content_url']);
        }
        $pdo->prepare("DELETE FROM portfolio_items WHERE id = ?")->execute([$id]);
        $_SESSION['settings_message'] = __('work_deleted_success');
        header("Location: settings.php?active_tab=portfolio");
        exit;
    }

    // Pricing Plans Management
    if (isset($_POST['add_pricing_plan'])) {
        $name_ar = $_POST['plan_name_ar'] ?? '';
        $name_en = $_POST['plan_name_en'] ?? '';
        $price = $_POST['plan_price'] ?? 0;
        $currency_ar = $_POST['plan_currency_ar'] ?? 'ريال';
        $currency_en = $_POST['plan_currency_en'] ?? 'SAR';
        $period_ar = $_POST['plan_period_ar'] ?? 'شهرياً';
        $period_en = $_POST['plan_period_en'] ?? 'Monthly';
        $desc_ar = $_POST['plan_desc_ar'] ?? '';
        $desc_en = $_POST['plan_desc_en'] ?? '';
        $features = $_POST['plan_features'] ?? '';
        $is_featured = isset($_POST['plan_featured']) ? 1 : 0;
        $btn_text_ar = $_POST['plan_btn_ar'] ?? 'اشترك الآن';
        $btn_text_en = $_POST['plan_btn_en'] ?? 'Subscribe Now';
        $btn_url = $_POST['plan_btn_url'] ?? '';
        $order = (int) ($_POST['plan_order'] ?? 0);

        $stmt = $pdo->prepare("INSERT INTO pricing_plans (plan_name_ar, plan_name_en, price, currency_ar, currency_en, billing_period_ar, billing_period_en, description_ar, description_en, features, is_featured, button_text_ar, button_text_en, button_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name_ar, $name_en, $price, $currency_ar, $currency_en, $period_ar, $period_en, $desc_ar, $desc_en, $features, $is_featured, $btn_text_ar, $btn_text_en, $btn_url, $order]);
        $_SESSION['settings_message'] = __('plan_added_success');
        header("Location: settings.php?active_tab=pricing");
        exit;
    }

    if (isset($_POST['edit_pricing_plan'])) {
        $id = (int) $_POST['plan_id'];
        $name_ar = $_POST['plan_name_ar'] ?? '';
        $name_en = $_POST['plan_name_en'] ?? '';
        $price = $_POST['plan_price'] ?? 0;
        $currency_ar = $_POST['plan_currency_ar'] ?? 'ريال';
        $currency_en = $_POST['plan_currency_en'] ?? 'SAR';
        $period_ar = $_POST['plan_period_ar'] ?? 'شهرياً';
        $period_en = $_POST['plan_period_en'] ?? 'Monthly';
        $desc_ar = $_POST['plan_desc_ar'] ?? '';
        $desc_en = $_POST['plan_desc_en'] ?? '';
        $features = $_POST['plan_features'] ?? '';
        $is_featured = isset($_POST['plan_featured']) ? 1 : 0;
        $btn_text_ar = $_POST['plan_btn_ar'] ?? 'اشترك الآن';
        $btn_text_en = $_POST['plan_btn_en'] ?? 'Subscribe Now';
        $btn_url = $_POST['plan_btn_url'] ?? '';
        $order = (int) ($_POST['plan_order'] ?? 0);

        $stmt = $pdo->prepare("UPDATE pricing_plans SET plan_name_ar=?, plan_name_en=?, price=?, currency_ar=?, currency_en=?, billing_period_ar=?, billing_period_en=?, description_ar=?, description_en=?, features=?, is_featured=?, button_text_ar=?, button_text_en=?, button_url=?, display_order=? WHERE id=?");
        $stmt->execute([$name_ar, $name_en, $price, $currency_ar, $currency_en, $period_ar, $period_en, $desc_ar, $desc_en, $features, $is_featured, $btn_text_ar, $btn_text_en, $btn_url, $order, $id]);
        $_SESSION['settings_message'] = __('plan_updated_success');
        header("Location: settings.php?active_tab=pricing");
        exit;
    }

    if (isset($_POST['delete_pricing_plan'])) {
        $id = (int) $_POST['delete_pricing_plan'];
        $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?")->execute([$id]);
        $_SESSION['settings_message'] = __('plan_deleted_success');
        header("Location: settings.php?active_tab=pricing");
        exit;
    }

    // Handle Deletions first
    $image_fields = ['site_logo', 'site_favicon', 'about_image', 'hero_image', 'tool_image', 'testimonial_1_image', 'testimonial_2_image', 'testimonial_3_image', 'testimonial_4_image'];
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
        if (
            strpos($key, 'delete_') === 0 ||
            $key === 'test_smtp' ||
            $key === 'test_smtp_email' ||
            $key === 'active_tab' ||
            // Portfolio specific fields
            $key === 'add_portfolio' ||
            $key === 'edit_portfolio' ||
            $key === 'delete_portfolio_id' ||
            $key === 'p_id' ||
            $key === 'p_title_ar' || $key === 'p_title_en' ||
            $key === 'p_desc_ar' || $key === 'p_desc_en' ||
            $key === 'p_category_ar' || $key === 'p_category_en' ||
            $key === 'p_type' || $key === 'p_iframe_url' ||
            $key === 'p_preview' || $key === 'p_order' ||
            // Pricing specific fields
            $key === 'add_pricing_plan' ||
            $key === 'edit_pricing_plan' ||
            $key === 'delete_pricing_plan' ||
            $key === 'plan_id' ||
            $key === 'plan_name_ar' || $key === 'plan_name_en' ||
            $key === 'plan_price' ||
            $key === 'plan_currency_ar' || $key === 'plan_currency_en' ||
            $key === 'plan_period_ar' || $key === 'plan_period_en' ||
            $key === 'plan_desc_ar' || $key === 'plan_desc_en' ||
            $key === 'plan_features' ||
            $key === 'plan_featured' ||
            $key === 'plan_btn_ar' || $key === 'plan_btn_en' ||
            $key === 'plan_btn_url' ||
            $key === 'plan_order'
        )
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
                        <button onclick="switchTab('tool')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="tool"><?php echo __('tool_showcase_settings'); ?></button>
                        <button onclick="switchTab('portfolio')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="portfolio">
                            <?php echo __('portfolio'); ?></button>
                        <button onclick="switchTab('pricing')"
                            class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold transition-all border border-white/5"
                            data-tab="pricing"><?php echo __('pricing'); ?></button>
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
            <div id="site-tab" class="tab-content hidden space-y-6">

                <!-- 1. Brand Identity -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                        <span class="w-2 h-6 bg-indigo-500 rounded-full mr-3"></span>
                        <?php echo __('site_logo') . ' & ' . __('site_favicon'); ?>
                    </h3>

                    <div class="grid md:grid-cols-2 gap-8">
                        <!-- Logo -->
                        <div class="space-y-4">
                            <label
                                class="block text-gray-400 text-sm font-medium mb-1"><?php echo __('site_logo'); ?></label>
                            <div
                                class="relative group p-4 bg-gray-900 rounded-xl border border-gray-700/50 flex items-center justify-center">
                                <?php if (!empty($settings['site_logo'])): ?>
                                    <img src="../uploads/<?php echo $settings['site_logo']; ?>" class="h-12 object-contain">
                                    <button type="submit" name="delete_site_logo"
                                        class="absolute -top-2 -right-2 p-1.5 bg-red-600 rounded-full text-white shadow-md hover:bg-red-500 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-600 text-xs italic">No logo set</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="site_logo"
                                class="setting-input text-xs w-full file:bg-indigo-600 file:border-none file:text-white file:rounded-lg file:px-2 file:py-1 file:mr-2 file:cursor-pointer">
                        </div>

                        <!-- Favicon -->
                        <div class="space-y-4">
                            <label
                                class="block text-gray-400 text-sm font-medium mb-1"><?php echo __('site_favicon'); ?></label>
                            <div
                                class="relative group p-4 bg-gray-900 rounded-xl border border-gray-700/50 flex items-center justify-center">
                                <?php if (!empty($settings['site_favicon'])): ?>
                                    <img src="../uploads/<?php echo $settings['site_favicon']; ?>"
                                        class="h-8 object-contain">
                                    <button type="submit" name="delete_site_favicon"
                                        class="absolute -top-2 -right-2 p-1.5 bg-red-600 rounded-full text-white shadow-md hover:bg-red-500 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-600 text-xs italic">No favicon</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="site_favicon"
                                class="setting-input text-xs w-full file:bg-indigo-600 file:border-none file:text-white file:rounded-lg file:px-2 file:py-1 file:mr-2 file:cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- 2. Site Content -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Arabic -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span>
                            <?php echo __('site_info'); ?> <span
                                class="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded border border-emerald-500/20">AR</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_name_ar'); ?></label>
                                <input type="text" name="site_name_ar"
                                    value="<?php echo $settings['site_name_ar'] ?? 'الصراف الذكي'; ?>"
                                    class="setting-input">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('footer_description_ar'); ?></label>
                                <textarea name="footer_description_ar" rows="3"
                                    class="setting-input"><?php echo $settings['footer_description_ar'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- English -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
                            <?php echo __('site_info'); ?> <span
                                class="text-xs bg-indigo-500/10 text-indigo-400 px-2 py-1 rounded border border-indigo-500/20">EN</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('site_name_en'); ?></label>
                                <input type="text" name="site_name_en"
                                    value="<?php echo $settings['site_name_en'] ?? 'SmartExchange'; ?>"
                                    class="setting-input">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('footer_description_en'); ?></label>
                                <textarea name="footer_description_en" rows="3"
                                    class="setting-input"><?php echo $settings['footer_description_en'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Contact & System -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Contact Emails -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                            <span class="w-2 h-6 bg-pink-500 rounded-full mr-3"></span>
                            <?php echo __('contact_info'); ?>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_email'); ?></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500">@</span>
                                    <input type="email" name="contact_email"
                                        value="<?php echo $settings['contact_email'] ?? 'admin@example.com'; ?>"
                                        class="setting-input pl-10">
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('contact_form_email'); ?></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500">@</span>
                                    <input type="email" name="contact_form_email"
                                        value="<?php echo $settings['contact_form_email'] ?? ($settings['contact_email'] ?? ''); ?>"
                                        class="setting-input pl-10">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Modes -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                            <span class="w-2 h-6 bg-red-500 rounded-full mr-3"></span>
                            <?php echo __('system_maintenance'); ?>
                        </h3>
                        <div class="space-y-5">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('maintenance_mode'); ?></label>
                                <select name="maintenance_mode" class="setting-input bg-gray-900 appearance-none">
                                    <option value="0" <?php echo ($settings['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>><?php echo __('off_live'); ?></option>
                                    <option value="1" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>><?php echo __('on_maintenance'); ?></option>
                                </select>
                            </div>

                            <!-- Maintenance Messages -->
                            <div x-show="document.querySelector('select[name=maintenance_mode]').value == '1'"
                                class="space-y-4 pt-4 border-t border-white/5">
                                <div>
                                    <label
                                        class="block text-gray-400 text-xs font-bold uppercase mb-1"><?php echo __('maintenance_mode') . ' (' . __('arabic') . ')'; ?></label>
                                    <textarea name="maintenance_message_ar" rows="2"
                                        class="setting-input text-sm"><?php echo $settings['maintenance_message_ar'] ?? ''; ?></textarea>
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-400 text-xs font-bold uppercase mb-1"><?php echo __('maintenance_mode') . ' (' . __('english') . ')'; ?></label>
                                    <textarea name="maintenance_message_en" rows="2"
                                        class="setting-input text-sm"><?php echo $settings['maintenance_message_en'] ?? ''; ?></textarea>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <input type="hidden" name="enable_scroll_top" value="0">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <div class="relative">
                                        <input type="checkbox" name="enable_scroll_top" value="1" <?php echo ($settings['enable_scroll_top'] ?? '1') == '1' ? 'checked' : ''; ?>
                                            class="peer sr-only">
                                        <div
                                            class="w-10 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600">
                                        </div>
                                    </div>
                                    <span
                                        class="text-sm font-medium text-gray-300 group-hover:text-white transition-colors"><?php echo __('enable_scroll_top'); ?></span>
                                </label>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- 4. Localization Settings -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                        <span class="w-2 h-6 bg-blue-500 rounded-full mr-3"></span>
                        <?php echo __('localization_settings'); ?>
                    </h3>

                    <div class="grid lg:grid-cols-2 gap-6">
                        <div>
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('default_site_language'); ?></label>
                            <select name="default_site_lang"
                                class="setting-input bg-gray-900 appearance-none cursor-pointer">
                                <option value="ar" <?php echo ($settings['default_site_lang'] ?? 'ar') == 'ar' ? 'selected' : ''; ?>>العربية (Arabic)</option>
                                <option value="en" <?php echo ($settings['default_site_lang'] ?? 'ar') == 'en' ? 'selected' : ''; ?>>English</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                <?php echo __('default_lang_desc'); ?>
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Hero Section Tab -->
            <div id="hero-tab" class="tab-content hidden space-y-6">

                <!-- 1. Media & Badge Settings -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                        <span class="w-2 h-6 bg-purple-500 rounded-full mr-3"></span>
                        <?php echo __('hero_image_settings'); ?>
                    </h3>

                    <div class="grid lg:grid-cols-3 gap-8">
                        <!-- Hero Image -->
                        <div class="lg:col-span-1 space-y-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-gray-400 text-sm font-medium">Hero Image</label>
                            </div>

                            <div class="relative group">
                                <?php
                                $h_img = $settings['hero_image'] ?? '';
                                $h_img_url = !empty($h_img) ? '../uploads/' . $h_img : '../assets/img/hero-default.png';
                                ?>
                                <div
                                    class="w-full h-48 rounded-2xl bg-gray-900 border border-gray-700/50 flex items-center justify-center overflow-hidden">
                                    <img id="hero_image_preview" src="<?php echo $h_img_url; ?>"
                                        class="w-full h-full object-cover transition-all duration-700">

                                    <!-- Animated Loader Overlay -->
                                    <div id="hero_upload_loader"
                                        class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-10 transition-all">
                                        <div class="flex flex-col items-center gap-3">
                                            <div
                                                class="w-10 h-10 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin">
                                            </div>
                                            <span
                                                class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest animate-pulse"><?php echo __('uploading'); ?>...</span>
                                        </div>
                                    </div>

                                    <!-- Overlay Actions -->
                                    <div
                                        class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center gap-3">
                                        <label
                                            class="cursor-pointer p-2 bg-indigo-600 rounded-lg hover:bg-indigo-500 text-white transition-colors"
                                            title="<?php echo __('upload_image'); ?>">
                                            <input type="file" name="hero_image" id="hero_image_input" class="hidden"
                                                accept="image/*">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12">
                                                </path>
                                            </svg>
                                        </label>
                                        <?php if (!empty($h_img)): ?>
                                            <button type="submit" name="delete_hero_image"
                                                class="p-2 bg-red-600 rounded-lg hover:bg-red-500 text-white transition-colors"
                                                title="<?php echo __('delete'); ?>">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                    </path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="lg:col-span-2 grid md:grid-cols-2 gap-6">
                            <!-- AR -->
                            <div class="p-5 bg-white/5 rounded-2xl border border-white/10">
                                <h4
                                    class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <img src="https://flagcdn.com/w20/sa.png"
                                        class="w-4 rounded-sm grayscale opacity-50">
                                    <?php echo __('floating_badge'); ?> (AR)
                                </h4>
                                <div class="space-y-3">
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('top_text_feature'); ?></label>
                                        <input type="text" name="hero_floating_top_ar"
                                            value="<?php echo $settings['hero_floating_top_ar'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="مثلاً: دقة تصل إلى 99%">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('main_text_badge'); ?></label>
                                        <input type="text" name="hero_floating_main_ar"
                                            value="<?php echo $settings['hero_floating_main_ar'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="الأداة رقم #1 للتسويق">
                                    </div>
                                </div>
                            </div>

                            <!-- EN -->
                            <div class="p-5 bg-white/5 rounded-2xl border border-white/10">
                                <h4
                                    class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <img src="https://flagcdn.com/w20/us.png"
                                        class="w-4 rounded-sm grayscale opacity-50">
                                    <?php echo __('floating_badge'); ?> (EN)
                                </h4>
                                <div class="space-y-3">
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('top_text_feature'); ?></label>
                                        <input type="text" name="hero_floating_top_en"
                                            value="<?php echo $settings['hero_floating_top_en'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="e.g. 99% Accuracy">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('main_text_badge'); ?></label>
                                        <input type="text" name="hero_floating_main_en"
                                            value="<?php echo $settings['hero_floating_main_en'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="The #1 Tool for Marketing">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Content Settings -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Arabic -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span>
                            <?php echo __('hero_section'); ?> <span
                                class="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded border border-emerald-500/20">AR</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_title_ar'); ?></label>
                                <input type="text" name="hero_title_ar"
                                    value="<?php echo $settings['hero_title_ar'] ?? ''; ?>" class="setting-input">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_subtitle_ar'); ?></label>
                                <textarea name="hero_subtitle_ar" rows="3"
                                    class="setting-input"><?php echo $settings['hero_subtitle_ar'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_feature_ar'); ?></label>
                                <div class="relative">
                                    <input type="text" name="hero_feature_ar"
                                        value="<?php echo $settings['hero_feature_ar'] ?? ''; ?>"
                                        class="setting-input pl-10" placeholder="مثلاً: دقة تصل إلى 99%">
                                    <span class="absolute left-3 top-3 text-gray-500"><svg class="w-5 h-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- English -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
                            <?php echo __('hero_section'); ?> <span
                                class="text-xs bg-indigo-500/10 text-indigo-400 px-2 py-1 rounded border border-indigo-500/20">EN</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_title_en'); ?></label>
                                <input type="text" name="hero_title_en"
                                    value="<?php echo $settings['hero_title_en'] ?? ''; ?>" class="setting-input">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_subtitle_en'); ?></label>
                                <textarea name="hero_subtitle_en" rows="3"
                                    class="setting-input"><?php echo $settings['hero_subtitle_en'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('hero_feature_en'); ?></label>
                                <div class="relative">
                                    <input type="text" name="hero_feature_en"
                                        value="<?php echo $settings['hero_feature_en'] ?? ''; ?>"
                                        class="setting-input pl-10" placeholder="e.g. 99% Accuracy">
                                    <span class="absolute left-3 top-3 text-gray-500"><svg class="w-5 h-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- About Us Tab -->
            <!-- About Us Tab -->
            <div id="about-tab" class="tab-content hidden space-y-6">

                <!-- 1. Media & Badge Settings -->
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                        <span class="w-2 h-6 bg-blue-500 rounded-full mr-3"></span>
                        <?php echo __('image') . ' & ' . __('floating_badge'); ?>
                    </h3>

                    <div class="grid lg:grid-cols-3 gap-8">
                        <!-- Main Image -->
                        <div class="lg:col-span-1 space-y-4">
                            <label
                                class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_image'); ?></label>

                            <!-- Image Preview & Upload -->
                            <div class="relative group">
                                <div
                                    class="w-full h-48 rounded-xl bg-gray-900 border border-gray-700/50 flex items-center justify-center overflow-hidden">
                                    <?php
                                    $a_img = $settings['about_image'] ?? '';
                                    $a_img_url = !empty($a_img) ? '../uploads/' . $a_img : '../assets/img/about-default.png';
                                    ?>
                                    <img src="<?php echo $a_img_url; ?>" class="w-full h-full object-cover">

                                    <!-- Overlay Actions -->
                                    <div
                                        class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center gap-3">
                                        <label
                                            class="cursor-pointer p-2 bg-indigo-600 rounded-lg hover:bg-indigo-500 text-white transition-colors"
                                            title="<?php echo __('upload_image'); ?>">
                                            <input type="file" name="about_image" class="hidden" accept="image/*">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12">
                                                </path>
                                            </svg>
                                        </label>
                                        <?php if (!empty($settings['about_image'])): ?>
                                            <button type="submit" name="delete_about_image"
                                                class="p-2 bg-red-600 rounded-lg hover:bg-red-500 text-white transition-colors"
                                                title="<?php echo __('delete'); ?>">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                    </path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2 text-center">Recommended: 800x600px</p>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="lg:col-span-2 grid md:grid-cols-2 gap-6">
                            <!-- AR Badge -->
                            <div class="p-5 bg-white/5 rounded-2xl border border-white/10">
                                <h4
                                    class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <img src="https://flagcdn.com/w20/sa.png"
                                        class="w-4 rounded-sm grayscale opacity-50">
                                    <?php echo __('floating_badge'); ?> (AR)
                                </h4>
                                <div class="space-y-3">
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('top_text_feature'); ?></label>
                                        <input type="text" name="about_floating_top_ar"
                                            value="<?php echo $settings['about_floating_top_ar'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="مثلاً: الخيار الأفضل">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('main_text_badge'); ?></label>
                                        <input type="text" name="about_floating_main_ar"
                                            value="<?php echo $settings['about_floating_main_ar'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="سريع وآمن">
                                    </div>
                                </div>
                            </div>

                            <!-- EN Badge -->
                            <div class="p-5 bg-white/5 rounded-2xl border border-white/10">
                                <h4
                                    class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <img src="https://flagcdn.com/w20/us.png"
                                        class="w-4 rounded-sm grayscale opacity-50">
                                    <?php echo __('floating_badge'); ?> (EN)
                                </h4>
                                <div class="space-y-3">
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('top_text_feature'); ?></label>
                                        <input type="text" name="about_floating_top_en"
                                            value="<?php echo $settings['about_floating_top_en'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="e.g. Best Choice">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] font-bold uppercase mb-1"><?php echo __('main_text_badge'); ?></label>
                                        <input type="text" name="about_floating_main_en"
                                            value="<?php echo $settings['about_floating_main_en'] ?? ''; ?>"
                                            class="setting-input text-sm" placeholder="Fast & Secure">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Content Settings -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Arabic Content -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-emerald-500 rounded-full"></span>
                            <?php echo __('about_section'); ?>
                            <span
                                class="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded border border-emerald-500/20">AR</span>
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_title_ar'); ?></label>
                                <input type="text" name="about_title_ar"
                                    value="<?php echo $settings['about_title_ar'] ?? ''; ?>" class="setting-input"
                                    placeholder="من نحن">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_subtitle_ar'); ?></label>
                                <textarea name="about_subtitle_ar" rows="2" class="setting-input"
                                    placeholder="شريكك الذكي في عالم التسويق الرقمي"><?php echo $settings['about_subtitle_ar'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_desc_ar'); ?></label>
                                <textarea name="about_desc_ar" rows="4"
                                    class="setting-input"><?php echo $settings['about_desc_ar'] ?? ''; ?></textarea>
                            </div>
                            <div class="pt-4 border-t border-white/5">
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
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_btn_ar'); ?></label>
                                <input type="text" name="about_btn_ar"
                                    value="<?php echo $settings['about_btn_ar'] ?? ''; ?>" class="setting-input"
                                    placeholder="عرض الكل">
                            </div>
                        </div>
                    </div>

                    <!-- English Content -->
                    <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                        <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
                            <?php echo __('about_section'); ?>
                            <span
                                class="text-xs bg-indigo-500/10 text-indigo-400 px-2 py-1 rounded border border-indigo-500/20">EN</span>
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_title_en'); ?></label>
                                <input type="text" name="about_title_en"
                                    value="<?php echo $settings['about_title_en'] ?? ''; ?>" class="setting-input"
                                    placeholder="About Us">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_subtitle_en'); ?></label>
                                <textarea name="about_subtitle_en" rows="2" class="setting-input"
                                    placeholder="Your Smart Partner..."><?php echo $settings['about_subtitle_en'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_desc_en'); ?></label>
                                <textarea name="about_desc_en" rows="4"
                                    class="setting-input"><?php echo $settings['about_desc_en'] ?? ''; ?></textarea>
                            </div>
                            <div class="pt-4 border-t border-white/5">
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
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('about_btn_en'); ?></label>
                                <input type="text" name="about_btn_en"
                                    value="<?php echo $settings['about_btn_en'] ?? ''; ?>" class="setting-input"
                                    placeholder="View All">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Button Action URL -->
                <div
                    class="glass-card p-6 rounded-2xl border border-white/5 bg-gradient-to-r from-indigo-900/10 to-purple-900/10">
                    <div class="flex flex-col md:flex-row gap-6 items-center">
                        <div class="flex-1 w-full">
                            <label class="block text-indigo-300 text-sm font-bold mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                    </path>
                                </svg>
                                <?php echo __('button_url'); ?> (Target)
                            </label>
                            <input type="text" name="about_btn_url"
                                value="<?php echo $settings['about_btn_url'] ?? ''; ?>"
                                class="setting-input font-mono text-sm"
                                placeholder="<?php echo __('about_btn_url_placeholder'); ?>">
                            <p class="text-xs text-gray-500 mt-2"><?php echo __('about_btn_url_hint'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Tab -->
            <div id="contact-tab" class="tab-content hidden glass-card p-6 md:p-8 rounded-2xl">
                <div class="space-y-12">
                    <!-- Contact Info Section -->
                    <section>
                        <h3 class="text-xl font-bold text-white mb-8 flex items-center">
                            <span class="w-2 h-6 bg-pink-500 rounded-full mr-3"></span>
                            <?php echo __('contact_info_settings'); ?>
                        </h3>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-3 uppercase tracking-wider opacity-60 text-[11px] font-bold">
                                    <?php echo __('contact_phone'); ?>
                                </label>
                                <input type="text" name="contact_phone"
                                    value="<?php echo $settings['contact_phone'] ?? ''; ?>" class="setting-input"
                                    placeholder="+20...">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-3 uppercase tracking-wider opacity-60 text-[11px] font-bold">
                                    <?php echo __('contact_address_ar'); ?>
                                </label>
                                <input type="text" name="contact_address_ar"
                                    value="<?php echo $settings['contact_address_ar'] ?? ''; ?>" class="setting-input">
                            </div>
                            <div>
                                <label
                                    class="block text-gray-400 text-sm font-medium mb-3 uppercase tracking-wider opacity-60 text-[11px] font-bold">
                                    <?php echo __('contact_address_en'); ?>
                                </label>
                                <input type="text" name="contact_address_en"
                                    value="<?php echo $settings['contact_address_en'] ?? ''; ?>" class="setting-input">
                            </div>
                        </div>
                    </section>

                    <!-- Divider -->
                    <div class="h-[1px] bg-gradient-to-r from-transparent via-white/10 to-transparent w-full"></div>

                    <!-- Social Links Section -->
                    <section>
                        <h3 class="text-xl font-bold text-white mb-8 flex items-center">
                            <span class="w-2 h-6 bg-indigo-500 rounded-full mr-3"></span>
                            <?php echo __('social_links'); ?>
                        </h3>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                            <?php
                            $platforms = [
                                'facebook' => ['color' => '#1877F2'],
                                'messenger' => ['color' => '#00B2FF'],
                                'twitter' => ['color' => '#000000'],
                                'instagram' => ['color' => '#E4405F'],
                                'telegram' => ['color' => '#26A5E4'],
                                'whatsapp' => ['color' => '#25D366'],
                                'youtube' => ['color' => '#FF0000'],
                                'linkedin' => ['color' => '#0A66C2'],
                                'tiktok' => ['color' => '#000000']
                            ];
                            foreach ($platforms as $p => $meta):
                                ?>
                                <div class="group">
                                    <div class="flex items-center justify-between mb-2 px-1">
                                        <label
                                            class="text-gray-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full"
                                                style="background-color: <?php echo $meta['color']; ?>"></span>
                                            <?php echo __($p); ?>
                                        </label>
                                        <div
                                            class="flex items-center gap-2 opacity-40 group-hover:opacity-100 transition-all duration-300">
                                            <input type="hidden" name="floating_<?php echo $p; ?>" value="0">
                                            <input type="checkbox" name="floating_<?php echo $p; ?>" value="1"
                                                id="float_<?php echo $p; ?>" <?php echo ($settings['floating_' . $p] ?? '0') == '1' ? 'checked' : ''; ?>
                                                class="w-3.5 h-3.5 rounded border-gray-700 bg-gray-800 text-indigo-600 focus:ring-indigo-500">
                                            <label for="float_<?php echo $p; ?>"
                                                class="text-[9px] font-bold text-gray-500 uppercase cursor-pointer select-none">
                                                <?php echo __('floating_button'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <input type="text" name="social_<?php echo $p; ?>"
                                        value="<?php echo $settings['social_' . $p] ?? ''; ?>"
                                        class="setting-input py-2.5 text-sm border-white/5 focus:border-white/20"
                                        placeholder="Username or Link">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- SMTP Settings Tab -->
            <!-- Tool Showcase Tab -->
            <div id="tool-tab" class="tab-content hidden space-y-6">
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                        <span class="w-2 h-6 bg-blue-500 rounded-full mr-3"></span>
                        <?php echo __('tool_showcase_settings'); ?>
                    </h3>

                    <div class="grid lg:grid-cols-3 gap-8">
                        <!-- Image Upload -->
                        <div class="lg:col-span-1 space-y-4">
                            <label
                                class="block text-gray-400 text-sm font-medium"><?php echo __('tool_image'); ?></label>
                            <div
                                class="relative group aspect-video bg-gray-900 rounded-2xl border border-white/10 overflow-hidden flex items-center justify-center">
                                <?php
                                $t_img = $settings['tool_image'] ?? '';
                                $t_img_url = !empty($t_img) ? '../uploads/' . $t_img : '../assets/img/platform-mockup.png';
                                ?>
                                <img id="tool_image_preview" src="<?php echo $t_img_url; ?>"
                                    class="w-full h-full object-cover">

                                <?php if (!empty($t_img)): ?>
                                    <button type="submit" name="delete_tool_image"
                                        class="absolute top-4 right-4 p-2 bg-red-600 rounded-xl text-white shadow-xl hover:bg-red-500 transition-all opacity-0 group-hover:opacity-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                <?php endif; ?>

                                <!-- Loader -->
                                <div id="tool_upload_loader"
                                    class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center z-10">
                                    <div
                                        class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin">
                                    </div>
                                </div>
                            </div>
                            <input type="file" name="tool_image" id="tool_image_input" class="setting-input text-xs"
                                accept="image/*">
                        </div>

                        <!-- Content Settings -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Arabic -->
                                <div class="space-y-4">
                                    <h4
                                        class="text-xs font-black text-blue-400 uppercase tracking-widest flex items-center gap-2">
                                        <span class="w-1 h-1 rounded-full bg-blue-500"></span> Arabic Content
                                    </h4>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_badge_ar'); ?></label>
                                        <input type="text" name="tool_badge_ar"
                                            value="<?php echo htmlspecialchars($settings['tool_badge_ar'] ?? __('extraction_tools')); ?>"
                                            class="setting-input">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_title_ar'); ?></label>
                                        <input type="text" name="tool_title_ar"
                                            value="<?php echo htmlspecialchars($settings['tool_title_ar'] ?? __('tool_showcase_title')); ?>"
                                            class="setting-input font-bold">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_subtitle_ar'); ?></label>
                                        <textarea name="tool_subtitle_ar" rows="3"
                                            class="setting-input text-sm"><?php echo htmlspecialchars($settings['tool_subtitle_ar'] ?? __('tool_showcase_subtitle')); ?></textarea>
                                    </div>
                                </div>

                                <!-- English -->
                                <div class="space-y-4">
                                    <h4
                                        class="text-xs font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                                        <span class="w-1 h-1 rounded-full bg-indigo-500"></span> English Content
                                    </h4>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_badge_en'); ?></label>
                                        <input type="text" name="tool_badge_en"
                                            value="<?php echo htmlspecialchars($settings['tool_badge_en'] ?? 'Extraction Tools'); ?>"
                                            class="setting-input">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_title_en'); ?></label>
                                        <input type="text" name="tool_title_en"
                                            value="<?php echo htmlspecialchars($settings['tool_title_en'] ?? 'The Power of Extraction'); ?>"
                                            class="setting-input font-bold">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_subtitle_en'); ?></label>
                                        <textarea name="tool_subtitle_en" rows="3"
                                            class="setting-input text-sm"><?php echo htmlspecialchars($settings['tool_subtitle_en'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Button Settings -->
                            <div class="pt-6 border-t border-white/5 grid md:grid-cols-3 gap-4">
                                <div>
                                    <label
                                        class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_btn_text_ar'); ?></label>
                                    <input type="text" name="tool_btn_text_ar"
                                        value="<?php echo htmlspecialchars($settings['tool_btn_text_ar'] ?? ''); ?>"
                                        class="setting-input text-sm" placeholder="مثلاً: ابدأ الآن">
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_btn_text_en'); ?></label>
                                    <input type="text" name="tool_btn_text_en"
                                        value="<?php echo htmlspecialchars($settings['tool_btn_text_en'] ?? ''); ?>"
                                        class="setting-input text-sm" placeholder="e.g. Start Now">
                                </div>
                                <div>
                                    <label
                                        class="block text-gray-500 text-[10px] uppercase font-bold mb-1"><?php echo __('tool_btn_url'); ?></label>
                                    <input type="text" name="tool_btn_url"
                                        value="<?php echo htmlspecialchars($settings['tool_btn_url'] ?? ''); ?>"
                                        class="setting-input text-sm" placeholder="#register">
                                </div>
                            </div>
                            <!-- Features Settings -->
                            <div class="pt-8 border-t border-white/5 space-y-6">
                                <h4 class="text-white font-bold flex items-center gap-2">
                                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                        </path>
                                    </svg>
                                    <?php echo __('tool_features_edit'); ?>
                                </h4>

                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <div class="p-6 bg-white/5 rounded-2xl border border-white/5 space-y-4">
                                        <h5 class="text-xs font-black text-gray-500 uppercase tracking-widest">Feature
                                            #<?php echo $i; ?></h5>
                                        <div class="grid md:grid-cols-2 gap-6">
                                            <!-- AR -->
                                            <div class="space-y-3">
                                                <div>
                                                    <label
                                                        class="block text-gray-600 text-[10px] uppercase font-bold mb-1">العنوان
                                                        (AR)</label>
                                                    <input type="text" name="tool_f<?php echo $i; ?>_t_ar"
                                                        value="<?php echo htmlspecialchars($settings['tool_f' . $i . '_t_ar'] ?? __('tool_feature_' . $i)); ?>"
                                                        class="setting-input text-sm">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-gray-600 text-[10px] uppercase font-bold mb-1">الوصف
                                                        (AR)</label>
                                                    <textarea name="tool_f<?php echo $i; ?>_d_ar" rows="2"
                                                        class="setting-input text-sm"><?php echo htmlspecialchars($settings['tool_f' . $i . '_d_ar'] ?? __('tool_feature_' . $i . '_desc')); ?></textarea>
                                                </div>
                                            </div>
                                            <!-- EN -->
                                            <div class="space-y-3">
                                                <div>
                                                    <label
                                                        class="block text-gray-600 text-[10px] uppercase font-bold mb-1">Title
                                                        (EN)</label>
                                                    <input type="text" name="tool_f<?php echo $i; ?>_t_en"
                                                        value="<?php echo htmlspecialchars($settings['tool_f' . $i . '_t_en'] ?? ''); ?>"
                                                        class="setting-input text-sm">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-gray-600 text-[10px] uppercase font-bold mb-1">Description
                                                        (EN)</label>
                                                    <textarea name="tool_f<?php echo $i; ?>_d_en" rows="2"
                                                        class="setting-input text-sm"><?php echo htmlspecialchars($settings['tool_f' . $i . '_d_en'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                            <?php echo ($settings['feature_' . $i . '_icon'] ?? '') == 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' ? 'selected' : ''; ?>>🏢 Office /
                                            Targeting</option>
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
                                        <option value="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" <?php echo ($settings['service_' . $i . '_icon'] ?? '') == 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' ? 'selected' : ''; ?>>
                                            💻 Programming / Code</option>
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
                                        class="block text-gray-500 text-xs font-bold mb-1 uppercase"><?php echo __('service_url'); ?></label>
                                    <input type="text" name="service_<?php echo $i; ?>_url"
                                        value="<?php echo $settings['service_' . $i . '_url'] ?? ''; ?>"
                                        class="setting-input mb-4 text-xs font-mono text-indigo-300"
                                        placeholder="# or https://...">
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
                                value="<?php echo $settings['cta_button_en'] ?? ''; ?>" class="setting-input mb-4"
                                placeholder="Button Text">
                            <div>
                                <label
                                    class="block text-gray-400 text-[10px] font-bold uppercase mb-2"><?php echo __('cta_btn_id'); ?></label>
                                <input type="text" name="cta_btn_url"
                                    value="<?php echo $settings['cta_btn_url'] ?? ''; ?>" class="setting-input text-sm"
                                    placeholder="e.g. #register, #contact">
                            </div>
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

            <!-- Portfolio Management Tab -->
            <div id="portfolio-tab" class="tab-content hidden space-y-8">
                <div class="glass-card p-6 md:p-8 rounded-2xl border border-white/5">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                        <span class="w-2 h-8 bg-blue-500 rounded-full"></span>
                        <?php echo __('portfolio'); ?>
                    </h3>

                    <!-- Add New Item Form -->
                    <div class="bg-white/5 p-6 rounded-xl border border-white/5 mb-8">
                        <h4 class="text-white font-bold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4">
                                </path>
                            </svg>
                            <?php echo __('add_new_work'); ?>
                        </h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="space-y-4">
                                <input type="text" name="p_title_ar" class="setting-input"
                                    placeholder="<?php echo __('work_title'); ?> (AR)">
                                <input type="text" name="p_title_en" class="setting-input"
                                    placeholder="<?php echo __('work_title'); ?> (EN)">
                                <input type="text" name="p_category_ar" class="setting-input"
                                    placeholder="التصنيف (عربي: مثل ووردبريس)">
                                <input type="text" name="p_category_en" class="setting-input"
                                    placeholder="Category (English: e.g. WordPress)">
                                <textarea name="p_desc_ar" class="setting-input"
                                    placeholder="<?php echo __('work_desc'); ?> (AR)"></textarea>
                                <textarea name="p_desc_en" class="setting-input"
                                    placeholder="<?php echo __('work_desc'); ?> (EN)"></textarea>
                            </div>
                            <div class="space-y-4">
                                <select name="p_type" class="setting-input" onchange="togglePortfolioType(this.value)">
                                    <option value="image"><?php echo __('image'); ?></option>
                                    <option value="iframe"><?php echo __('iframe'); ?></option>
                                </select>

                                <div id="p_image_group">
                                    <label
                                        class="block text-xs text-gray-500 mb-1 uppercase font-bold"><?php echo __('work_image'); ?></label>
                                    <input type="file" name="p_image_file" class="setting-input" accept="image/*">
                                </div>

                                <div id="p_iframe_group" class="hidden">
                                    <label
                                        class="block text-xs text-gray-500 mb-1 uppercase font-bold"><?php echo __('work_iframe'); ?></label>
                                    <input type="url" name="p_iframe_url" class="setting-input"
                                        placeholder="https://example.com">
                                </div>

                                <input type="url" name="p_preview" class="setting-input"
                                    placeholder="<?php echo __('work_link'); ?> (Optional)">
                                <input type="number" name="p_order" class="setting-input"
                                    placeholder="<?php echo __('display_order'); ?>" value="0">

                                <button type="submit" name="add_portfolio"
                                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all">
                                    <?php echo __('add_new_work'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Items List -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left rtl:text-right text-sm">
                            <thead>
                                <tr
                                    class="text-gray-500 border-b border-white/5 uppercase text-[10px] font-black tracking-widest">
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3"><?php echo __('work_title'); ?></th>
                                    <th class="px-4 py-3"><?php echo __('work_type'); ?></th>
                                    <th class="px-4 py-3"><?php echo __('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php
                                $pStmt = $pdo->query("SELECT * FROM portfolio_items ORDER BY display_order ASC, id DESC");
                                while ($pRow = $pStmt->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                    <tr class="hover:bg-white/[0.02] transition-colors group">
                                        <td class="px-4 py-4 text-gray-500 font-mono"><?php echo $pRow['display_order']; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="font-bold text-white"><?php echo $pRow['title_' . $lang]; ?></div>
                                            <div class="text-[10px] text-blue-400">
                                                <?php echo htmlspecialchars($pRow['category_' . $lang] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?php echo $pRow['item_type'] == 'iframe' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-green-500/20 text-green-400'; ?>">
                                                <?php echo __($pRow['item_type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-right rtl:text-left flex items-center justify-end gap-2">
                                            <button type="button"
                                                onclick='openEditPortfolio(<?php echo json_encode($pRow); ?>)'
                                                class="p-2 text-blue-400 hover:bg-blue-500/20 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M18.364 5.364a9 9 0 1112.728 12.728L5.364 18.364m12.728-12.728L5.364 5.364">
                                                    </path>
                                                </svg>
                                            </button>
                                            <button type="button"
                                                onclick="confirmDeletePortfolio(<?php echo $pRow['id']; ?>)"
                                                class="p-2 text-red-500 hover:bg-red-500/20 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                    </path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                // Hero Image Upload Preview & Loader
                const heroInput = document.getElementById('hero_image_input');
                if (heroInput) {
                    heroInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            const loader = document.getElementById('hero_upload_loader');
                            const preview = document.getElementById('hero_image_preview');

                            if (loader) loader.classList.remove('hidden');
                            if (preview) preview.style.filter = 'blur(4px)';

                            reader.onload = function (event) {
                                setTimeout(() => {
                                    if (preview) preview.src = event.target.result;
                                    if (loader) loader.classList.add('hidden');
                                    if (preview) preview.style.filter = 'none';
                                }, 800);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }

                // Ensure loader shows on form submit if a file is selected
                const mainForm = document.querySelector('form');
                if (mainForm && heroInput) {
                    mainForm.addEventListener('submit', function () {
                        if (heroInput.files.length > 0) {
                            const loader = document.getElementById('hero_upload_loader');
                            const preview = document.getElementById('hero_image_preview');
                            if (loader) loader.classList.remove('hidden');
                            if (preview) preview.style.filter = 'blur(4px)';
                        }
                    });
                }

                // Tool Image Upload Preview
                const toolInput = document.getElementById('tool_image_input');
                if (toolInput) {
                    toolInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            const loader = document.getElementById('tool_upload_loader');
                            const preview = document.getElementById('tool_image_preview');

                            if (loader) loader.classList.remove('hidden');
                            if (preview) preview.style.filter = 'blur(4px)';

                            reader.onload = function (event) {
                                setTimeout(() => {
                                    if (preview) preview.src = event.target.result;
                                    if (loader) loader.classList.add('hidden');
                                    if (preview) preview.style.filter = 'none';
                                }, 800);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            </script>
            <script>
                function togglePortfolioType(val, prefix = 'p') {
                    document.getElementById(prefix + '_image_group').classList.toggle('hidden', val !== 'image');
                    document.getElementById(prefix + '_iframe_group').classList.toggle('hidden', val !== 'iframe');
                }

                function openEditPortfolio(item) {
                    document.getElementById('edit_p_id').value = item.id;
                    document.getElementById('edit_p_title_ar').value = item.title_ar;
                    document.getElementById('edit_p_title_en').value = item.title_en;
                    document.getElementById('edit_p_category_ar').value = item.category_ar || '';
                    document.getElementById('edit_p_category_en').value = item.category_en || '';
                    document.getElementById('edit_p_desc_ar').value = item.description_ar;
                    document.getElementById('edit_p_desc_en').value = item.description_en;
                    document.getElementById('edit_p_type').value = item.item_type;
                    document.getElementById('edit_p_preview').value = item.preview_url;
                    document.getElementById('edit_p_order').value = item.display_order;

                    if (item.item_type === 'iframe') {
                        document.getElementById('edit_p_iframe_url').value = item.content_url;
                    }

                    togglePortfolioType(item.item_type, 'edit_p');
                    document.getElementById('portfolioEditModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden'; // Prevent body scroll
                }

                function closeEditPortfolio() {
                    document.getElementById('portfolioEditModal').classList.add('hidden');
                    document.body.style.overflow = ''; // Restore body scroll
                }


                function confirmDeletePortfolio(id) {
                    document.getElementById('delete_p_id').value = id;
                    document.getElementById('portfolioDeleteModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden'; // Prevent body scroll
                }

                function closeDeleteModal() {
                    document.getElementById('portfolioDeleteModal').classList.add('hidden');
                    document.body.style.overflow = ''; // Restore body scroll
                }
            </script>

            <?php include __DIR__ . '/pricing_tab.php'; ?>

            <div class="flex justify-end pt-4">
                <button type="submit"
                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold px-12 py-4 rounded-2xl shadow-xl shadow-indigo-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <?php echo __('save_changes'); ?>
                </button>
            </div>
        </form>

        <?php include __DIR__ . '/pricing_modals.php'; ?>

        <!-- Modals moved outside the main form to prevent nesting -->
        <!-- Edit Portfolio Modal -->
        <div id="portfolioEditModal"
            class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-fade-in overflow-y-auto">
            <div
                class="glass-card w-full max-w-2xl my-auto rounded-[2.5rem] border border-white/10 shadow-2xl overflow-hidden animate-scale-in">
                <!-- Modal Header -->
                <div
                    class="bg-gradient-to-r from-indigo-600/20 to-purple-600/20 p-6 border-b border-white/5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-indigo-500/20 rounded-xl flex items-center justify-center text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M18.364 5.364a9 9 0 1112.728 12.728L5.364 18.364m12.728-12.728L5.364 5.364">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white"><?php echo __('edit_portfolio'); ?></h3>
                    </div>
                    <button onclick="closeEditPortfolio()" class="p-2 hover:bg-white/5 rounded-full transition-colors">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6 text-left rtl:text-right">
                    <input type="hidden" name="p_id" id="edit_p_id">
                    <input type="hidden" name="active_tab" value="portfolio">

                    <div class="grid md:grid-cols-2 gap-8">
                        <div class="space-y-5">
                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_title'); ?></label>
                                <div class="space-y-3">
                                    <input type="text" name="p_title_ar" id="edit_p_title_ar" class="setting-input"
                                        placeholder="العنوان بالعربي" required>
                                    <input type="text" name="p_title_en" id="edit_p_title_en" class="setting-input"
                                        placeholder="Title in English" required>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('category'); ?></label>
                                <div class="space-y-3">
                                    <input type="text" name="p_category_ar" id="edit_p_category_ar"
                                        class="setting-input" placeholder="التصنيف بالعربي">
                                    <input type="text" name="p_category_en" id="edit_p_category_en"
                                        class="setting-input" placeholder="Category in English">
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_desc'); ?></label>
                                <div class="space-y-3">
                                    <textarea name="p_desc_ar" id="edit_p_desc_ar" class="setting-input resize-none"
                                        rows="3" placeholder="الوصف بالعربي"></textarea>
                                    <textarea name="p_desc_en" id="edit_p_desc_en" class="setting-input resize-none"
                                        rows="3" placeholder="Description in English"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_type'); ?></label>
                                <select name="p_type" id="edit_p_type" class="setting-input"
                                    onchange="togglePortfolioType(this.value, 'edit_p')">
                                    <option value="image"><?php echo __('image'); ?></option>
                                    <option value="iframe"><?php echo __('iframe'); ?></option>
                                </select>
                            </div>

                            <div id="edit_p_image_group" class="p-4 bg-white/5 rounded-2xl border border-white/5">
                                <label
                                    class="block text-[10px] text-indigo-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_image'); ?></label>
                                <input type="file" name="p_image_file"
                                    class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500"
                                    accept="image/*">
                                <p class="text-[10px] text-gray-500 mt-2 italic">
                                    <?php echo __('leave_empty_keep_current'); ?>
                                </p>
                            </div>

                            <div id="edit_p_iframe_group" class="hidden">
                                <label
                                    class="block text-[10px] text-indigo-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_iframe'); ?></label>
                                <input type="url" name="p_iframe_url" id="edit_p_iframe_url" class="setting-input"
                                    placeholder="https://example.com">
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('work_link'); ?></label>
                                <input type="url" name="p_preview" id="edit_p_preview" class="setting-input"
                                    placeholder="https://preview.com">
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] text-gray-400 mb-2 uppercase font-black tracking-widest"><?php echo __('display_order'); ?></label>
                                <input type="number" name="p_order" id="edit_p_order" class="setting-input">
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex gap-4 pt-6 border-t border-white/5">
                        <button type="submit" name="edit_portfolio"
                            class="flex-1 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-2xl transition-all shadow-xl shadow-indigo-600/20 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                            <?php echo __('save_changes'); ?>
                        </button>
                        <button type="button" onclick="closeEditPortfolio()"
                            class="flex-1 py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all">
                            <?php echo __('cancel'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Custom Delete Confirmation Modal -->
        <div id="portfolioDeleteModal"
            class="fixed inset-0 z-[70] hidden flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md animate-fade-in">
            <div
                class="glass-card w-full max-w-md p-0 rounded-[2.5rem] border border-red-500/20 shadow-2xl text-center animate-scale-in overflow-hidden">
                <div class="p-8">
                    <div
                        class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 text-red-500">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4"><?php echo __('are_you_sure_delete_portfolio'); ?>
                    </h3>
                    <p class="text-gray-400 mb-8"><?php echo __('undone_action_warning'); ?></p>

                    <form method="POST" class="flex flex-col gap-3">
                        <input type="hidden" name="delete_portfolio_id" id="delete_p_id">
                        <input type="hidden" name="active_tab" value="portfolio">
                        <button type="submit"
                            class="w-full py-4 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl transition-all shadow-xl shadow-red-600/20">
                            <?php echo __('confirm_delete'); ?>
                        </button>
                        <button type="button" onclick="closeDeleteModal()"
                            class="w-full py-4 bg-white/5 hover:bg-white/10 text-white font-bold rounded-2xl border border-white/10 transition-all">
                            <?php echo __('cancel'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
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
        // Hideall tabs
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