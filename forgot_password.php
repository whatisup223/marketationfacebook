<?php
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';

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
                    $message = "We have sent a password reset link to your email.";
                } else {
                    $error = "Failed to send email. Please check your SMTP settings or contact support.";
                }
            } else {
                $error = "System error. Please try again.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        // We pretend we sent it to avoid enumerating emails, or we can just say invalid email. 
        // For security, generic message is better, but for UX, specific is often preferred by clients.
        // I will stick to "If that email exists..." style or just explicit error for this project level.
        $error = "Email not found.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[60vh] px-4">
    <div class="glass-card w-full max-w-md p-8 rounded-3xl relative overflow-hidden">
        <h2 class="text-3xl font-bold text-center mb-2"><?php echo __('forgot_password_title'); ?></h2>
        <p class="text-center text-gray-400 mb-8"><?php echo __('forgot_password_desc'); ?></p>

        <?php if ($message): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 text-sm text-center border border-green-500/30">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-300 p-4 rounded-xl mb-6 text-sm text-center border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 relative z-10">
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('email'); ?></label>
                <input type="email" name="email"
                    class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                    placeholder="you@example.com" required>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-900/40 transition-all transform hover:-translate-y-1">
                <?php echo __('send_reset_link'); ?>
            </button>

            <p class="text-center text-gray-500 text-sm mt-4">
                <?php echo __('remember_password'); ?> <a href="login.php"
                    class="text-indigo-400 hover:text-white transition-colors">
                    <?php echo __('login'); ?>
                </a>
            </p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>