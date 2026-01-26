<?php
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';
$validToken = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $pdo = getDB();

    // Validate Token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRequest) {
        $validToken = true;
    } else {
        $error = "Invalid or expired token.";
    }
} else {
    $error = "No token provided.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $validToken) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $email = $resetRequest['email'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        // Update User
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($stmt->execute([$newHash, $email])) {

            // Delete Token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $message = "Password updated successfully. You can now login.";
            $validToken = false; // Disable form
        } else {
            $error = "Failed to update password.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-center min-h-[60vh] px-4">
    <div class="glass-card w-full max-w-md p-8 rounded-3xl relative overflow-hidden">
        <h2 class="text-3xl font-bold text-center mb-2">Reset Password</h2>

        <?php if ($message): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 text-sm text-center border border-green-500/30">
                <?php echo $message; ?>
                <div class="mt-4">
                    <a href="login.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg">Go to
                        Login</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-300 p-4 rounded-xl mb-6 text-sm text-center border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <form method="POST" class="space-y-4 relative z-10">
                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('new_password'); ?></label>
                    <input type="password" name="password"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="••••••••" required>
                </div>
                <div>
                    <label
                        class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('confirm_password'); ?></label>
                    <input type="password" name="confirm_password"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="••••••••" required>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-900/40 transition-all transform hover:-translate-y-1">
                    <?php echo __('update_password'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>