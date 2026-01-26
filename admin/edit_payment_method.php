<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$id = $_GET['id'] ?? null;
$message = '';
$method = null;

if ($id) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = $_POST['name'];
        $name_ar = $_POST['name_ar'];
        $currency_id = $_POST['currency_id'];
        $type = $_POST['type'];
        $wallet_info = $_POST['wallet_info'];
        $instructions = $_POST['instructions'];
        $image = $_POST['image'];
        $min_amount = $_POST['min_amount'];
        $max_amount = $_POST['max_amount'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE payment_methods SET name=?, name_ar=?, currency_id=?, type=?, wallet_info=?, instructions=?, image=?, min_amount=?, max_amount=?, is_active=? WHERE id=?");
        if ($stmt->execute([$name, $name_ar, $currency_id, $type, $wallet_info, $instructions, $image, $min_amount, $max_amount, $is_active, $id])) {
            $message = $lang === 'ar' ? 'تم تحديث وسيلة الدفع بنجاح!' : 'Payment method updated successfully!';
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->execute([$id]);
    $method = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    header("Location: payment_methods.php");
    exit;
}

// Get all currencies for dropdown
$currencies = $pdo->query("SELECT * FROM currencies ORDER BY symbol")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 p-4 md:p-8">
        <div class="flex items-center space-x-4 mb-8">
            <a href="payment_methods.php" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold">
                <?php echo __('edit_payment_method'); ?>:
                <?php echo $lang === 'ar' && $method['name_ar'] ? $method['name_ar'] : $method['name']; ?>
            </h1>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 border border-green-500/30">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="glass-card p-8 rounded-2xl max-w-2xl">
            <form method="POST" class="space-y-6">
                <div class="flex items-center space-x-4 mb-6">
                    <?php if ($method['image']): ?>
                        <img src="<?php echo $method['image']; ?>" alt="" class="w-16 h-16 rounded-full bg-gray-800">
                    <?php else: ?>
                        <div
                            class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-2xl">
                            <?php echo strtoupper(substr($method['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-lg font-bold text-white">
                            <?php echo $lang === 'ar' && $method['name_ar'] ? $method['name_ar'] : $method['name']; ?>
                        </div>
                        <div class="text-indigo-400">
                            <?php
                            $typeText = $method['type'] == 'deposit' ? __('deposit') : ($method['type'] == 'withdraw' ? __('withdraw') : __('both'));
                            echo $typeText;
                            ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('payment_method_name'); ?> (EN)
                        </label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($method['name']); ?>" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('payment_method_name'); ?> (AR)
                        </label>
                        <input type="text" name="name_ar" value="<?php echo htmlspecialchars($method['name_ar']); ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('currency_name'); ?>
                        </label>
                        <select name="currency_id" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?php echo $curr['id']; ?>" <?php echo $curr['id'] == $method['currency_id'] ? 'selected' : ''; ?>>
                                    <?php echo $curr['symbol']; ?> -
                                    <?php echo $curr['name_en']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('payment_method_type'); ?>
                        </label>
                        <select name="type"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                            <option value="both" <?php echo $method['type'] == 'both' ? 'selected' : ''; ?>>
                                <?php echo __('both'); ?>
                            </option>
                            <option value="deposit" <?php echo $method['type'] == 'deposit' ? 'selected' : ''; ?>>
                                <?php echo __('deposit'); ?>
                            </option>
                            <option value="withdraw" <?php echo $method['type'] == 'withdraw' ? 'selected' : ''; ?>>
                                <?php echo __('withdraw'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('min_amount'); ?>
                        </label>
                        <input type="number" step="0.01" name="min_amount" value="<?php echo $method['min_amount']; ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('max_amount'); ?>
                        </label>
                        <input type="number" step="0.01" name="max_amount" value="<?php echo $method['max_amount']; ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo __('wallet_info_label'); ?>
                    </label>
                    <textarea name="wallet_info" rows="3"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"><?php echo htmlspecialchars($method['wallet_info']); ?></textarea>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo __('instructions'); ?>
                    </label>
                    <textarea name="instructions" rows="4"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"><?php echo htmlspecialchars($method['instructions']); ?></textarea>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo $lang === 'ar' ? 'رابط الصورة' : 'Image URL'; ?>
                    </label>
                    <input type="text" name="image" value="<?php echo htmlspecialchars($method['image']); ?>"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                </div>

                <div class="flex items-center">
                    <input id="active" type="checkbox" name="is_active" <?php echo $method['is_active'] ? 'checked' : ''; ?>
                    class="w-5 h-5 bg-gray-900 border-gray-700 rounded focus:ring-indigo-500 focus:ring-offset-gray-900
                    text-indigo-600">
                    <label for="active" class="ml-2 text-gray-300 font-medium select-none cursor-pointer">
                        <?php echo __('active_visible'); ?>
                    </label>
                </div>

                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all">
                    <?php echo $lang === 'ar' ? 'تحديث وسيلة الدفع' : 'Update Payment Method'; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>