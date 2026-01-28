<?php
require_once __DIR__ . '/includes/functions.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['email']); // Check for both email and username
    $password = $_POST['password'];

    $pdo = getDB();
    if ($pdo) {
        // Query both email and username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['user_name'] = $user['username'] ?? '';

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
            $error = __('invalid_credentials');
        }
    } else {
        $error = __('database_error');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[90vh] px-4 py-12 relative overflow-hidden">
    <!-- Animated Blobs -->
    <div
        class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
    </div>
    <div
        class="absolute top-40 -right-20 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
    </div>
    <div
        class="absolute -bottom-20 left-40 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000">
    </div>

    <div class="glass-card w-full max-w-md p-8 rounded-[2.5rem] relative overflow-hidden shadow-2xl border border-white/10 backdrop-blur-xl z-20"
        data-aos="zoom-in">

        <div class="text-center mb-10">
            <h2 class="text-3xl font-black text-white mb-2 tracking-tight">
                <?php echo __('welcome_back'); ?> 👋
            </h2>
            <p class="text-gray-400">
                <?php echo __('login_continue'); ?> <span
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
            <div>
                <label
                    class="block text-gray-300 text-xs font-bold uppercase tracking-wider mb-2 ml-1"><?php echo __('email_or_username'); ?></label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <!-- Type text instead of email to allow username -->
                    <input type="text" name="email"
                        class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                        placeholder="<?php echo __('email_or_username_placeholder'); ?>" required>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-2 ml-1">
                    <label
                        class="block text-gray-300 text-xs font-bold uppercase tracking-wider"><?php echo __('password'); ?></label>
                    <a href="forgot_password.php"
                        class="text-xs font-bold text-indigo-400 hover:text-indigo-300 transition-colors">
                        <?php echo __('forgot_password_title'); ?>
                    </a>
                </div>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" name="password"
                        class="w-full bg-black/20 border border-white/10 rounded-2xl pl-12 pr-4 py-4 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                        placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 hover:from-indigo-500 hover:via-purple-500 hover:to-pink-500 text-white font-black py-4 rounded-2xl shadow-xl shadow-indigo-900/40 transition-all transform hover:scale-[1.02] active:scale-95 text-lg tracking-wide mt-2">
                <?php echo __('login'); ?>
            </button>

            <div class="text-center pt-2">
                <p class="text-gray-400">
                    <?php echo __('dont_have_account'); ?>
                    <a href="register.php"
                        class="text-white hover:text-indigo-400 font-bold transition-colors ml-1 underline decoration-indigo-500/30">
                        <?php echo __('register'); ?>
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>