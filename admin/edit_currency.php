<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$id = $_GET['id'] ?? null;
$message = '';
$curr = null;

if ($id) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $rate_buy = $_POST['exchange_rate_buy'];
        $rate_sell = $_POST['exchange_rate_sell'];
        $image = $_POST['image'];
        $reserve = $_POST['reserve'];
        $active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE currencies SET exchange_rate_buy=?, exchange_rate_sell=?, image=?, reserve=?, is_active=? WHERE id=?");
            if ($stmt->execute([$rate_buy, $rate_sell, $image, $reserve, $active, $id])) {
                $message = $lang === 'ar' ? 'تم تحديث العملة بنجاح!' : 'Currency updated successfully!';
            }
        } catch (PDOException $e) {
            $message = ($lang === 'ar' ? 'خطأ: ' : 'Error: ') . $e->getMessage();
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM currencies WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Handle Add New Logic later if needed
    header("Location: currencies.php");
    exit;
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
            <h1 class="text-3xl font-bold"><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?>
                <?php echo $curr['name_en']; ?> (
                <?php echo $curr['symbol']; ?>)
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
                    <img src="<?php echo $curr['image']; ?>" alt="" class="w-16 h-16 rounded-full bg-gray-800">
                    <div>
                        <div class="text-lg font-bold text-white">
                            <?php echo $curr['name_en']; ?>
                        </div>
                        <div class="text-indigo-400">
                            <?php echo $curr['symbol']; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'سعر الشراء' : 'Buy Rate (Platform Buys)'; ?></label>
                        <input type="number" step="0.00000001" name="exchange_rate_buy"
                            value="<?php echo $curr['exchange_rate_buy'] ?? 0; ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $lang === 'ar' ? 'السعر عندما يرسل المستخدم هذه العملة' : 'Rate when user SENDS this currency.'; ?>
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'سعر البيع' : 'Sell Rate (Platform Sells)'; ?></label>
                        <input type="number" step="0.00000001" name="exchange_rate_sell"
                            value="<?php echo $curr['exchange_rate_sell'] ?? 0; ?>"
                            class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $lang === 'ar' ? 'السعر عندما يستقبل المستخدم هذه العملة' : 'Rate when user RECEIVES this currency.'; ?>
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        class="block text-gray-400 text-sm font-medium mb-2"><?php echo $lang === 'ar' ? 'رابط الصورة' : 'Image URL'; ?></label>
                    <input type="text" name="image" value="<?php echo $curr['image']; ?>"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                </div>
                <div>
                    <label
                        class="block text-gray-400 text-sm font-medium mb-2"><?php echo __('reserve_amount'); ?></label>
                    <input type="number" step="0.01" name="reserve" value="<?php echo $curr['reserve']; ?>"
                        class="w-full bg-gray-900/50 border border-gray-700/50 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 transition-colors text-white">
                </div>
                <!-- Wallet info removed - now managed through Payment Methods -->


                <div class="flex items-center">
                    <input id="active" type="checkbox" name="is_active"
                        class="w-5 h-5 bg-gray-900 border-gray-700 rounded focus:ring-indigo-500 focus:ring-offset-gray-900 text-indigo-600"
                        <?php echo $curr['is_active'] ? 'checked' : ''; ?>>
                    <label for="active"
                        class="ml-2 text-gray-300 font-medium select-none cursor-pointer"><?php echo __('active_visible'); ?></label>
                </div>

                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all">
                    <?php echo $lang === 'ar' ? 'تحديث العملة' : 'Update Currency'; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>