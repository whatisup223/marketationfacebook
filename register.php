<?php
require_once __DIR__ . '/includes/functions.php';

$error = get_flash('error');
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        set_flash('error', __('fill_all_fields'));
        header("Location: register.php");
        exit;
    } elseif ($password !== $confirm_password) {
        set_flash('error', __('pass_match_error'));
        header("Location: register.php");
        exit;
    } else {
        $pdo = getDB();
        if ($pdo) {
            // Check email and username existence
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetchColumn() > 0) {
                // Determine which one exists for better error message
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    set_flash('error', __('email_exists'));
                } else {
                    set_flash('error', __('username_exists'));
                }
                header("Location: register.php");
                exit;
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
                if ($stmt->execute([$name, $username, $email, $phone, $hashed_password])) {
                    $user_id = $pdo->lastInsertId();

                    // Set Session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['role'] = 'user';
                    $_SESSION['name'] = $name;
                    $_SESSION['user_name'] = $username; // Store username in session

                    // --- Email Notifications ---
                    require_once __DIR__ . '/includes/MailService.php';
                    require_once __DIR__ . '/includes/email_templates.php';

                    // 1. Notify Admin (if enabled)
                    if (getSetting('notify_new_user_admin', '1') == '1') {
                        $adminEmail = getSetting('contact_email');
                        if ($adminEmail) {
                            $data = [
                                'name' => $name,
                                'email' => $email,
                                'username' => $username,
                                'admin_url' => getSetting('site_url') . '/admin/users.php'
                            ];
                            $tpl = getEmailTemplate('new_user_admin', $data);
                            sendEmail($adminEmail, $tpl['subject'], $tpl['body']);
                        }
                    }

                    // 2. Notify User (Welcome Email - if enabled)
                    if (getSetting('notify_new_user_client', '1') == '1') {
                        $data = [
                            'name' => $name,
                            'username' => $username,
                            'login_url' => getSetting('site_url') . '/login.php'
                        ];
                        $tpl = getEmailTemplate('welcome_user', $data, $_SESSION['lang'] ?? 'ar');
                        sendEmail($email, $tpl['subject'], $tpl['body']);
                    }
                    // ---------------------------

                    if (!empty($_SESSION['redirect_after_login'])) {
                        $redirect = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: " . $redirect);
                    } else {
                        header("Location: user/dashboard.php");
                    }
                    exit;
                } else {
                    set_flash('error', __('registration_failed'));
                    header("Location: register.php");
                    exit;
                }
            }
        } else {
            set_flash('error', __('database_error'));
            header("Location: register.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[90vh] px-4 py-12 relative overflow-hidden">
    <!-- Animated Blobs -->
    <div
        class="absolute top-0 left-0 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
    </div>
    <div
        class="absolute top-0 right-0 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
    </div>
    <div
        class="absolute -bottom-32 left-20 w-96 h-96 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000">
    </div>

    <div class="glass-card w-full max-w-2xl p-8 md:p-12 rounded-[2.5rem] relative overflow-hidden shadow-2xl border border-white/10 backdrop-blur-xl z-10"
        data-aos="fade-up">

        <div class="text-center mb-10">
            <h2 class="text-4xl font-black text-white mb-3 tracking-tight">
                <?php echo __('register_title'); ?>
            </h2>
            <p class="text-gray-400 text-lg">
                <?php echo __('join_message'); ?> <span
                    class="text-indigo-400 font-bold"><?php echo __('site_name'); ?></span>
            </p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-2xl mb-8 flex items-center gap-3 animate-headShake">
                <svg class="w-6 h-6 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-medium"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- Name & Username -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('name'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input type="text" name="name"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="John Doe" required value="<?php echo htmlspecialchars($name ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('username'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <input type="text" name="username"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="<?php echo __('username_placeholder'); ?>" required
                            value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Email & Phone -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('email'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input type="email" name="email"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="you@company.com" required
                            value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('phone'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <input type="text" name="phone"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="<?php echo __('phone_placeholder'); ?>" required
                            value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Passwords -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('password'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" name="password" id="password"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="••••••••" required>
                    </div>
                </div>

                <div>
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('password_confirm'); ?></label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <input type="password" name="confirm_password" id="confirm_password"
                            class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                            placeholder="<?php echo __('pass_confirm_placeholder'); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit"
                class="w-full bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 hover:from-indigo-500 hover:via-purple-500 hover:to-pink-500 text-white font-black py-4 rounded-2xl shadow-xl shadow-indigo-900/40 transition-all transform hover:scale-[1.02] active:scale-95 text-lg tracking-wide mt-4">
                <?php echo __('register'); ?>
            </button>

            <div class="text-center pt-2">
                <p class="text-gray-400">
                    <?php echo __('already_have_account'); ?>
                    <a href="login.php"
                        class="text-white hover:text-indigo-400 font-bold transition-colors ml-1 underline decoration-indigo-500/30">
                        <?php echo __('login'); ?>
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>