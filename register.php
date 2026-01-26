<?php
require_once __DIR__ . '/includes/functions.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $pdo = getDB();
    if ($pdo) {
        // Check email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Email already registered';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $password])) {
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'user';
                $_SESSION['name'] = $name;

                // --- Email Notifications ---
                require_once __DIR__ . '/includes/MailService.php';
                require_once __DIR__ . '/includes/email_templates.php';

                // 1. Notify Admin (if enabled)
                if (getSetting('notify_new_user_admin', '1') == '1') {
                    $adminEmail = getSetting('contact_email'); // Or a specific admin email setting
                    if ($adminEmail) {
                        $data = [
                            'name' => $name,
                            'email' => $email,
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
                        'login_url' => getSetting('site_url') . '/login.php'
                    ];
                    $tpl = getEmailTemplate('welcome_user', $data);
                    sendEmail($email, $tpl['subject'], $tpl['body']);
                }
                // ---------------------------

                // Auto-login the user after successful registration

                // Check if there's a pending exchange
                if (!empty($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header("Location: " . $redirect);
                } else {
                    header("Location: user/dashboard.php");
                }
                exit;
            } else {
                $error = 'Registration failed';
            }
        }
    } else {
        $error = 'Database Error';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[80vh] px-4">
    <div class="glass-card w-full max-w-md p-8 rounded-3xl relative overflow-hidden">
        <div class="absolute top-0 left-0 -ml-16 -mt-16 w-64 h-64 rounded-full bg-blue-500 opacity-20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 -mr-16 -mb-16 w-64 h-64 rounded-full bg-purple-500 opacity-20 blur-3xl">
        </div>

        <h2 class="text-3xl font-bold text-center mb-2"><?php echo __('create_account'); ?></h2>
        <p class="text-center text-gray-400 mb-8"><?php echo __('join_message'); ?>
            <?php echo __('site_name'); ?>
        </p>

        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-300 p-3 rounded-xl mb-6 text-sm text-center border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 relative z-10">
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('name'); ?></label>
                <input type="text" name="name"
                    class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                    placeholder="John Doe" required>
            </div>
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('email'); ?></label>
                <input type="email" name="email"
                    class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                    placeholder="you@example.com" required>
            </div>
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('password'); ?></label>
                <input type="password" name="password"
                    class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                    placeholder="••••••••" required>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-900/40 transition-all transform hover:-translate-y-1 mt-4">
                <?php echo __('register'); ?>
            </button>

            <p class="text-center text-gray-500 text-sm mt-4">
                <?php echo __('already_have_account'); ?> <a href="login.php"
                    class="text-indigo-400 hover:text-white transition-colors">
                    <?php echo __('login'); ?>
                </a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>