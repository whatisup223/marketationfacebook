<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center space-x-4 mb-8">
            <h1 class="text-3xl font-bold">
                <?php echo __('profile'); ?>
            </h1>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 border border-green-500/30 flex items-center">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
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
                <h2 class="text-xl font-bold mb-6">
                    <?php echo __('personal_details'); ?>
                </h2>
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
                                <span>
                                    <?php echo __('upload_new'); ?>
                                </span>
                                <input type="file" name="avatar" class="hidden" onchange="this.form.submit()">
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('display_name'); ?>
                        </label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('email_readonly'); ?>
                        </label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                            class="w-full bg-gray-800/50 border border-gray-700/50 rounded-xl px-4 py-3 text-gray-400 cursor-not-allowed"
                            readonly>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo __('email_readonly'); ?>
                        </p>
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 transition-all w-full md:w-auto">
                            <?php echo __('save_profile'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security -->
            <div class="glass-card p-8 rounded-2xl">
                <h2 class="text-xl font-bold mb-6">
                    <?php echo __('security'); ?>
                </h2>
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('new_password'); ?>
                        </label>
                        <input type="password" name="new_password"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('confirm_password'); ?>
                        </label>
                        <input type="password" name="confirm_password"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div class="pt-4">
                        <button type="submit"
                            class="bg-red-600/80 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-red-500/30 transition-all w-full md:w-auto">
                            <?php echo __('update_password'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>