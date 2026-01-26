<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';
    $currency_id = $_POST['currency_id'] ?? 0;
    $type = $_POST['type'] ?? 'both';
    $wallet_info = $_POST['wallet_info'] ?? '';
    $instructions = $_POST['instructions'] ?? '';
    $image = $_POST['image'] ?? '';
    $min_amount = $_POST['min_amount'] ?? 0;
    $max_amount = $_POST['max_amount'] ?? 999999999;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name && $currency_id) {
        try {
            $sql = "INSERT INTO payment_methods (name, name_ar, currency_id, type, wallet_info, instructions, image, min_amount, max_amount, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$name, $name_ar, $currency_id, $type, $wallet_info, $instructions, $image, $min_amount, $max_amount, $is_active])) {
                $message = $lang === 'ar' ? 'تم إضافة وسيلة الدفع بنجاح!' : 'Payment method added successfully!';
            } else {
                $error = $lang === 'ar' ? 'فشل في إضافة وسيلة الدفع' : 'Failed to add payment method.';
            }
        } catch (PDOException $e) {
            $error = ($lang === 'ar' ? 'خطأ في قاعدة البيانات: ' : 'Database error: ') . $e->getMessage();
        }
    } else {
        $error = $lang === 'ar' ? 'يرجى ملء جميع الحقول المطلوبة' : 'Please fill in all required fields.';
    }
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
                <?php echo __('add_payment_method'); ?>
            </h1>
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

        <div class="glass-card p-8 rounded-2xl max-w-2xl">
            <form method="POST" class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('payment_method_name'); ?> (EN)
                        </label>
                        <input type="text" name="name" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="e.g. Vodafone Cash">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('payment_method_name'); ?> (AR)
                        </label>
                        <input type="text" name="name_ar"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="مثال: فودافون كاش">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('currency_name'); ?>
                        </label>
                        <select name="currency_id" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                            <option value="">
                                <?php echo $lang === 'ar' ? 'اختر العملة' : 'Select Currency'; ?>
                            </option>
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?php echo $curr['id']; ?>">
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
                            <option value="both">
                                <?php echo __('both'); ?>
                            </option>
                            <option value="deposit">
                                <?php echo __('deposit'); ?>
                            </option>
                            <option value="withdraw">
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
                        <input type="number" step="0.01" name="min_amount" value="0"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-2">
                            <?php echo __('max_amount'); ?>
                        </label>
                        <input type="number" step="0.01" name="max_amount" value="999999999"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo __('wallet_info_label'); ?>
                    </label>
                    <textarea name="wallet_info" rows="3"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="e.g. 01012345678 or account@example.com"></textarea>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo __('instructions'); ?>
                    </label>
                    <textarea name="instructions" rows="4"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="<?php echo $lang === 'ar' ? 'تعليمات للمستخدم حول كيفية الدفع...' : 'Instructions for users on how to pay...'; ?>"></textarea>
                </div>

                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-2">
                        <?php echo $lang === 'ar' ? 'رابط الصورة' : 'Image URL'; ?>
                    </label>
                    <input type="text" name="image"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="e.g. https://example.com/logo.png">
                </div>

                <div class="flex items-center">
                    <input id="active" type="checkbox" name="is_active" checked
                        class="w-5 h-5 bg-gray-900 border-gray-700 rounded focus:ring-indigo-500 focus:ring-offset-gray-900 text-indigo-600">
                    <label for="active" class="ml-2 text-gray-300 font-medium select-none cursor-pointer">
                        <?php echo __('active_visible'); ?>
                    </label>
                </div>

                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all">
                    <?php echo __('add_payment_method'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>