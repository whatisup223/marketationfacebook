<?php
require_once __DIR__ . '/includes/functions.php';

$message = get_flash('success');
$error = get_flash('error');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $pdo = getDB();

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate Token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        try {
            // Save to DB
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            if ($stmt->execute([$email, $token, $expires])) {

                // Send Email
                require_once __DIR__ . '/includes/MailService.php';
                require_once __DIR__ . '/includes/email_templates.php';

                $data = [
                    'reset_url' => getSetting('site_url') . "/reset_password.php?token=$token"
                ];
                $tpl = getEmailTemplate('forgot_password', $data);

                if (sendEmail($email, $tpl['subject'], $tpl['body'])) {
                    set_flash('success', __('reset_link_sent'));
                } else {
                    set_flash('error', __('email_send_failed'));
                }
            } else {
                set_flash('error', __('system_error'));
            }
        } catch (Exception $e) {
            set_flash('error', __('error_prefix') . $e->getMessage());
        }
    } else {
        set_flash('error', __('email_not_found'));
    }
    header("Location: forgot_password.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[85vh] px-4 py-12 relative overflow-hidden">
    <!-- Animated Blobs -->
    <div
        class="absolute -top-32 left-0 w-80 h-80 bg-blue-600 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
    </div>
    <div
        class="absolute bottom-0 -right-20 w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-3000">
    </div>

    <div class="glass-card w-full max-w-md p-8 md:p-10 rounded-[2.5rem] relative overflow-hidden shadow-2xl border border-white/10 backdrop-blur-xl z-20"
        data-aos="fade-up">

        <div class="text-center mb-8">
            <div
                class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-indigo-500/30 text-white">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 14l-1 1-1 1H6v3H2v-3l4-4a6 6 0 1110.174-1.418z">
                    </path>
                </svg>
            </div>
            <h2 class="text-2xl font-black text-white mb-2 tracking-tight">
                <?php echo __('forgot_password_title'); ?>
            </h2>
            <p class="text-gray-400 text-sm">
                <?php echo __('forgot_password_desc'); ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div
                class="bg-green-500/10 border border-green-500/20 text-green-200 p-4 rounded-2xl mb-6 text-sm text-center flex flex-col items-center">
                <svg class="w-8 h-8 mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-200 p-4 rounded-2xl mb-6 text-sm text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
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
                        placeholder="you@example.com" required>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-900/40 transition-all transform hover:scale-[1.02] active:scale-95">
                <?php echo __('send_reset_link'); ?>
            </button>

            <div class="text-center pt-2">
                <p class="text-gray-400 text-sm">
                    <?php echo __('remember_password'); ?>
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