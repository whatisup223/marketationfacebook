<?php
require_once __DIR__ . '/includes/functions.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $pdo = getDB();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Check if there's a pending exchange or redirect URL
            if (!empty($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
            } elseif ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Database Error';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[80vh] px-4">
    <div class="glass-card w-full max-w-md p-8 rounded-3xl relative overflow-hidden" data-aos="zoom-in">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-indigo-500 opacity-20 blur-3xl">
        </div>
        <div class="absolute bottom-0 left-0 -ml-16 -mb-16 w-64 h-64 rounded-full bg-pink-500 opacity-20 blur-3xl">
        </div>

        <h2 class="text-3xl font-bold text-center mb-2">
            <?php echo __('welcome_back'); ?>
        </h2>
        <p class="text-center text-gray-400 mb-8">
            <?php echo __('login_continue'); ?>
            <?php echo __('site_name'); ?>
        </p>

        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-300 p-3 rounded-xl mb-6 text-sm text-center border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 relative z-10">
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
                <div class="flex justify-end mt-2">
                    <a href="forgot_password.php"
                        class="text-sm text-gray-400 hover:text-indigo-400"><?php echo __('forgot_password_title'); ?></a>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-900/40 transition-all transform hover:-translate-y-1">
                <?php echo __('login'); ?>
            </button>

            <p class="text-center text-gray-500 text-sm mt-4">
                <?php echo __('dont_have_account'); ?> <a href="register.php"
                    class="text-indigo-400 hover:text-white transition-colors">
                    <?php echo __('register'); ?>
                </a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>