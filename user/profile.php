<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$message = get_flash('success');
$error = get_flash('error');

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. Notification Settings ---
    if (isset($_POST['update_notifications'])) {
        $preferences = [
            'notify_login' => isset($_POST['notify_login']) ? 1 : 0,
            'notify_new_exchange' => isset($_POST['notify_new_exchange']) ? 1 : 0,
            'notify_exchange_status' => isset($_POST['notify_exchange_status']) ? 1 : 0,
            'lang' => $_POST['lang'] ?? 'ar'
        ];
        
        $json_prefs = json_encode($preferences);
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
        $stmt->execute([$json_prefs, $user_id]);
        
        // Update Session & Cookie immediately
        $_SESSION['lang'] = $preferences['lang'];
        setcookie('lang', $preferences['lang'], time() + (86400 * 30), "/"); // 30 Days expiration
        
        set_flash('success', __("preferences_updated"));
        header("Location: profile.php");
        exit;
    }

    // --- 2. Security (Password) ---
    if (!empty($_POST['new_password'])) {
        $password = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if ($password === $confirm) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hash, $user_id])) {
                set_flash('success', __("password_updated"));
            } else {
                set_flash('error', __("failed_update_password"));
            }
        } else {
            set_flash('error', __("passwords_not_match"));
        }
        header("Location: profile.php");
        exit;
    }

    // --- 4. Avatar Handling ---
    // Delete Avatar
    if (isset($_POST['delete_avatar'])) {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_avatar = $stmt->fetchColumn();
        if ($old_avatar && file_exists(__DIR__ . '/../' . $old_avatar)) {
            @unlink(__DIR__ . '/../' . $old_avatar);
        }
        $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        set_flash('success', __("avatar_updated"));
        header("Location: profile.php");
        exit;
    }

    // Upload Avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $new_name = 'uploads/avatars/' . $new_filename;
            $destination = $upload_dir . DIRECTORY_SEPARATOR . $new_filename;
            
            // Delete old
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_avatar = $stmt->fetchColumn();
            if ($old_avatar && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old_avatar))) {
                @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old_avatar));
            }

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$new_name, $user_id]);
                set_flash('success', __("avatar_updated"));
            } else {
                set_flash('error', "Failed to upload avatar: Permission denied or directory missing.");
            }
        } else {
            set_flash('error', "Invalid file type");
        }
        header("Location: profile.php");
        exit;
    }

    // --- 3. Personal Details (Name, Username, Phone) ---
    if (isset($_POST['update_profile'])) {
        $name = trim(strip_tags($_POST['name']));
        $username = trim(strip_tags($_POST['username']));
        $phone = trim(strip_tags($_POST['phone']));

        // Check availability of username (exclude current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            set_flash('error', __("username_exists"));
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$name, $username, $phone, $user_id])) {
                $_SESSION['name'] = $name;
                $_SESSION['user_name'] = $username;
                set_flash('success', __("profile_updated"));
            } else {
                set_flash('error', "Failed to update profile");
            }
        }
        header("Location: profile.php");
        exit;
    }

}

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_prefs = json_decode($user['preferences'] ?? '{}', true);

require_once __DIR__ . '/../includes/header.php';
?>

<div id="main-user-container" class="main-user-container flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center space-x-4 mb-8">
            <h1 class="text-3xl font-bold"><?php echo __('profile'); ?></h1>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 border border-green-500/30">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-300 p-4 rounded-xl mb-6 border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Profile Details -->
            <div class="glass-card p-8 rounded-2xl">
                <h2 class="text-xl font-bold mb-6"><?php echo __('personal_details'); ?></h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Avatar Section -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative group">
                            <?php if ($user['avatar']): ?>
                                <img src="../<?php echo $user['avatar']; ?>"
                                    class="w-32 h-32 rounded-3xl object-cover border-4 border-indigo-500/30">
                                <button type="submit" name="delete_avatar"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white p-1.5 rounded-xl shadow-lg hover:bg-red-600 transition-colors"
                                    title="<?php echo __('remove_image'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            <?php else: ?>
                                <div class="w-32 h-32 rounded-3xl bg-indigo-500/10 flex items-center justify-center border-4 border-dashed border-indigo-500/30">
                                    <span class="text-4xl font-bold text-indigo-400">
                                        <?php echo mb_substr($user['name'], 0, 1, 'UTF-8'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <label class="cursor-pointer bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-all border border-gray-700">
                                <span><?php echo __('upload_new'); ?></span>
                                <input type="file" name="avatar" class="hidden" onchange="this.form.submit()">
                            </label>
                        </div>
                    </div>

                    <!-- Fields -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('display_name'); ?></label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required
                                class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white font-medium">
                        </div>

                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('username'); ?></label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required
                                class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white font-medium">
                        </div>

                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('phone'); ?></label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white font-medium">
                        </div>

                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('email_readonly'); ?></label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly
                                class="w-full bg-gray-800/50 border border-gray-700/50 rounded-xl px-4 py-3 text-gray-400 cursor-not-allowed">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all hover:-translate-y-1">
                            <?php echo __('save_profile'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-8">
                <!-- Security -->
                <div class="glass-card p-8 rounded-2xl">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        <?php echo __('security'); ?>
                    </h2>
                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('new_password'); ?></label>
                            <input type="password" name="new_password"
                                class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('confirm_password'); ?></label>
                            <input type="password" name="confirm_password"
                                class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                        </div>
                        <div class="pt-2">
                            <button type="submit"
                                class="w-full bg-red-500/10 hover:bg-red-500 hover:text-white text-red-500 border border-red-500/20 font-bold px-6 py-3 rounded-xl transition-all">
                                <?php echo __('update_password'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Notification Preferences -->
                <div class="glass-card p-8 rounded-2xl">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        <?php echo __('notification_settings'); ?>
                    </h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_notifications" value="1">
                        
                        <!-- Language Selection -->
                        <div class="mb-6">
                            <label class="block text-gray-400 text-sm font-medium mb-2 uppercase tracking-wide text-xs ml-1"><?php echo __('language'); ?></label>
                            <select name="lang" class="w-full bg-gray-800/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white font-medium cursor-pointer">
                                <option value="ar" <?php echo ($user_prefs['lang'] ?? 'ar') == 'ar' ? 'selected' : ''; ?>>العربية</option>
                                <option value="en" <?php echo ($user_prefs['lang'] ?? 'ar') == 'en' ? 'selected' : ''; ?>>English</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-800/30 rounded-xl border border-gray-700/30 hover:border-indigo-500/30 transition-colors">
                            <div>
                                <h3 class="font-medium text-white text-sm"><?php echo __('notify_client_new_order'); ?></h3>
                                <p class="text-[10px] text-gray-500 mt-0.5"><?php echo __('notify_client_new_order_desc'); ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_new_exchange" value="1" class="sr-only peer" 
                                    <?php echo ($user_prefs['notify_new_exchange'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/30 rounded-xl border border-gray-700/30 hover:border-indigo-500/30 transition-colors">
                            <div>
                                <h3 class="font-medium text-white text-sm"><?php echo __('notify_client_status_change'); ?></h3>
                                <p class="text-[10px] text-gray-500 mt-0.5"><?php echo __('notify_client_status_change_desc'); ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_exchange_status" value="1" class="sr-only peer" 
                                    <?php echo ($user_prefs['notify_exchange_status'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                            </label>
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold px-6 py-3 rounded-xl transition-all">
                                <?php echo __('save_changes'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
