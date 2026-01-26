<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Notification Settings
    if (isset($_POST['update_notifications'])) {
        $allowed_notifs = ['notify_new_exchange_user', 'notify_exchange_status_user'];
        // Ideally these should be in a separate table 'user_settings', but for now we might need to store them.
        // Since the current requirement is to control them, and the DB schema for users might not have these columns.
        // We will assume these are global settings that the user can toggle for themselves if we add columns, OR 
        // we can store them in a JSON column if exists. 
        // HOWEVER, based on the previous helper functions, it checks `getSetting('key')` which pulls from `settings` table (GLOBAL).
        // To allow USER specific settings, we need `user_settings` table or columns in `users`.
        // Given the instructions, "settings in admin panel... allow turn on/off notifications in following cases", 
        // and "add options for user... to control in their dashboards".
        
        // Let's create `user_options` table or add columns to `users`. 
        // A simple way for now without major schema changes is adding a JSON column `preferences` to users.
        
        // For this step, I will add the logic to update if the columns existed, but since I cannot migrate easily without risk,
        // I will create a migration file to add a `preferences` column to `users` and use that.
        
        $preferences = [
            'notify_login' => isset($_POST['notify_login']) ? 1 : 0, // Example
            'notify_new_exchange' => isset($_POST['notify_new_exchange']) ? 1 : 0,
            'notify_exchange_status' => isset($_POST['notify_exchange_status']) ? 1 : 0,
        ];
        
        $json_prefs = json_encode($preferences);
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
        $stmt->execute([$json_prefs, $user_id]);
        $message = __("preferences_updated");
    }

    // Check if Password Update
    if (!empty($_POST['new_password'])) {
        $password = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if ($password === $confirm) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hash, $user_id])) {
                $message = __("password_updated");
            } else {
                $error = __("failed_update_password");
            }
        } else {
            $error = __("passwords_not_match");
        }
    }

    // Check Name Update
    if (isset($_POST['name'])) {
        $name = strip_tags($_POST['name']);
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        if ($stmt->execute([$name, $user_id])) {
            $_SESSION['name'] = $name; // Update session
            $message = $message ?: __("profile_updated");
        }
    }

    // Handle Avatar Deletion
    if (isset($_POST['delete_avatar'])) {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old_avatar = $stmt->fetchColumn();
        if ($old_avatar && file_exists(__DIR__ . '/../' . $old_avatar)) {
            unlink(__DIR__ . '/../' . $old_avatar);
        }
        $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = __("avatar_updated");
    }

    // Handle Avatar Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_name = 'uploads/avatars/avatar_' . $user_id . '_' . time() . '.' . $ext;

            // Delete old avatar
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_avatar = $stmt->fetchColumn();
            if ($old_avatar && file_exists(__DIR__ . '/../' . $old_avatar)) {
                @unlink(__DIR__ . '/../' . $old_avatar);
            }

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/../' . $new_name)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$new_name, $user_id]);
                $message = __("avatar_updated");
            }
        }
    }
}

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$user_prefs = json_decode($user['preferences'] ?? '{}', true);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
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
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative group">
                            <?php if ($user['avatar']): ?>
                                <img src="../<?php echo $user['avatar']; ?>"
                                    class="w-32 h-32 rounded-3xl object-cover border-4 border-indigo-500/30">
                                <button type="submit" name="delete_avatar"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white p-1.5 rounded-xl shadow-lg hover:bg-red-600 transition-colors"
                                    title="<?php echo __('remove_image'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            <?php else: ?>
                                <div
                                    class="w-32 h-32 rounded-3xl bg-indigo-500/10 flex items-center justify-center border-4 border-dashed border-indigo-500/30">
                                    <svg class="w-12 h-12 text-indigo-400/50" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <label
                                class="cursor-pointer bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-all border border-gray-700">
                                <span><?php echo __('upload_new'); ?></span>
                                <input type="file" name="avatar" class="hidden" onchange="this.form.submit()">
                            </label>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('display_name'); ?></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('email_readonly'); ?></label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                            class="w-full bg-gray-800/50 border border-gray-700/50 rounded-xl px-4 py-3 text-gray-400 cursor-not-allowed"
                            readonly>
                        <p class="text-xs text-gray-500 mt-1"><?php echo __('email_readonly'); ?></p>
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all"><?php echo __('save_profile'); ?></button>
                    </div>
                </form>
            </div>

            <!-- Security -->
            <div class="glass-card p-8 rounded-2xl">
                <h2 class="text-xl font-bold mb-6"><?php echo __('security'); ?></h2>
                <form method="POST" class="space-y-6">
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('new_password'); ?></label>
                        <input type="password" name="new_password"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('confirm_password'); ?></label>
                        <input type="password" name="confirm_password"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="bg-red-600/80 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-red-500/30 transition-all"><?php echo __('update_password'); ?></button>
                    </div>
                </form>
            </div>
            
            <!-- Notification Preferences -->
            <div class="glass-card p-8 rounded-2xl md:col-span-2">
                <h2 class="text-xl font-bold mb-6"><?php echo __('notification_settings'); ?></h2>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_notifications" value="1">
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h3 class="font-medium text-white"><?php echo __('notify_client_new_order'); ?></h3>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_client_new_order_desc'); ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_new_exchange" value="1" class="sr-only peer" 
                                    <?php echo ($user_prefs['notify_new_exchange'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                            <div>
                                <h3 class="font-medium text-white"><?php echo __('notify_client_status_change'); ?></h3>
                                <p class="text-xs text-gray-500 mt-1"><?php echo __('notify_client_status_change_desc'); ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_exchange_status" value="1" class="sr-only peer" 
                                    <?php echo ($user_prefs['notify_exchange_status'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all"><?php echo __('save_preferences'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>