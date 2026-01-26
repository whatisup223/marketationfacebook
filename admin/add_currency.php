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
    $name_en = $_POST['name_en'] ?? '';
    $name_ar = $_POST['name_ar'] ?? $name_en; // Use English name if Arabic not provided
    $symbol = $_POST['symbol'] ?? '';
    $code = $_POST['code'] ?? $symbol; // Use symbol as code if not provided
    $rate_buy = $_POST['exchange_rate_buy'] ?? 0;
    $rate_sell = $_POST['exchange_rate_sell'] ?? 0;
    $reserve = $_POST['reserve'] ?? 0;
    $image = $_POST['image'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name_en && $symbol && $rate_buy && $rate_sell) {
        try {
            // Check if symbol exists
            $stmt = $pdo->prepare("SELECT id FROM currencies WHERE symbol = ?");
            $stmt->execute([$symbol]);
            if ($stmt->fetch()) {
                $error = $lang === 'ar' ? 'عملة بهذا الرمز موجودة بالفعل' : 'Currency with this symbol already exists.';
            } else {
                $sql = "INSERT INTO currencies (name_en, name_ar, symbol, code, exchange_rate_buy, exchange_rate_sell, reserve, image, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$name_en, $name_ar, $symbol, $code, $rate_buy, $rate_sell, $reserve, $image, $is_active])) {
                    $message = $lang === 'ar' ? 'تم إضافة العملة بنجاح!' : 'Currency added successfully!';
                } else {
                    $error = $lang === 'ar' ? 'فشل في إضافة العملة' : 'Failed to add currency.';
                }
            }
        } catch (PDOException $e) {
            $error = ($lang === 'ar' ? 'خطأ في قاعدة البيانات: ' : 'Database error: ') . $e->getMessage();
        }
    } else {
        $error = $lang === 'ar' ? 'يرجى ملء جميع الحقول المطلوبة' : 'Please fill in all required fields.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 p-4 md:p-8">
        <div class="flex items-center space-x-4 mb-8">
            <a href="currencies.php" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold"><?php echo $lang === 'ar' ? 'إضافة عملة جديدة' : 'Add New Currency'; ?></h1>
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
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'اسم العملة (بالإنجليزية)' : 'Currency Name (EN)'; ?></label>
                        <input type="text" name="name_en" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="e.g. US Dollar">
                    </div>
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'الرمز' : 'Symbol (Code)'; ?></label>
                        <input type="text" name="symbol" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="e.g. USD">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'سعر الشراء' : 'Buy Rate (Platform Buys)'; ?></label>
                        <input type="number" step="0.00000001" name="exchange_rate_buy" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="e.g. 48.50">
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $lang === 'ar' ? 'السعر عندما يرسل المستخدم هذه العملة' : 'Rate when user SENDS this currency.'; ?>
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'سعر البيع' : 'Sell Rate (Platform Sells)'; ?></label>
                        <input type="number" step="0.00000001" name="exchange_rate_sell" required
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                            placeholder="e.g. 50.00">
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $lang === 'ar' ? 'السعر عندما يستقبل المستخدم هذه العملة' : 'Rate when user RECEIVES this currency.'; ?>
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('reserve_amount'); ?></label>
                    <input type="number" step="0.01" name="reserve" value="0.00"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                </div>

                <!-- Wallet info removed - now managed through Payment Methods -->

                <div>
                    <label
                        class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'رابط الصورة' : 'Image URL'; ?></label>
                    <input type="text" name="image"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white"
                        placeholder="e.g. https://flagcdn.com/w80/us.png">
                </div>

                <div class="flex items-center">
                    <input id="active" type="checkbox" name="is_active" checked
                        class="w-5 h-5 bg-gray-900 border-gray-700 rounded focus:ring-indigo-500 focus:ring-offset-gray-900 text-indigo-600">
                    <label for="active"
                        class="ml-2 text-gray-300 font-medium select-none cursor-pointer"><?php echo __('active_visible'); ?></label>
                </div>

                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all">
                    <?php echo __('add_currency'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>