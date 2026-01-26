<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$id]);
        header("Location: payment_methods.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <?php if (isset($_GET['deleted'])): ?>
            <div class="bg-green-500/20 text-green-300 p-4 rounded-xl mb-6 border border-green-500/30">
                <?php echo $lang === 'ar' ? 'تم حذف وسيلة الدفع بنجاح!' : 'Payment method deleted successfully!'; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/20 text-red-300 p-4 rounded-xl mb-6 border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">
                <?php echo __('manage_payment_methods'); ?>
            </h1>
            <a href="add_payment_method.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                +
                <?php echo __('add_payment_method'); ?>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php
            // Get all payment methods with their currency info
            $stmt = $pdo->query("
                SELECT pm.*, c.symbol as currency_symbol, c.name_en as currency_name 
                FROM payment_methods pm 
                LEFT JOIN currencies c ON pm.currency_id = c.id 
                ORDER BY c.symbol, pm.name
            ");
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all to check if empty later
            foreach ($methods as $method):
                ?>
                <div class="glass-card p-6 rounded-2xl relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <?php if ($method['image']): ?>
                                <img src="<?php echo $method['image']; ?>" class="w-10 h-10 rounded-full bg-gray-700" alt="">
                            <?php else: ?>
                                <div
                                    class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($method['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-white">
                                    <?php echo $lang === 'ar' && $method['name_ar'] ? $method['name_ar'] : $method['name']; ?>
                                </div>
                                <div class="text-xs text-indigo-400 font-medium">
                                    <?php echo $method['currency_symbol']; ?> - <?php
                                        $typeText = $method['type'] == 'deposit' ? __('deposit') : ($method['type'] == 'withdraw' ? __('withdraw') : __('both'));
                                        echo $typeText;
                                        ?>
                                </div>
                            </div>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-lg <?php echo $method['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
                            <?php echo $method['is_active'] ? __('active') : __('inactive'); ?>
                        </span>
                    </div>

                    <div class="space-y-3 text-sm text-gray-300 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-500">
                                <?php echo __('min_amount'); ?>
                            </span>
                            <span class="font-mono text-indigo-300">
                                <?php echo number_format($method['min_amount'], 2); ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">
                                <?php echo __('max_amount'); ?>
                            </span>
                            <span class="font-mono text-indigo-300">
                                <?php echo number_format($method['max_amount'], 0); ?>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <button
                            onclick="showMethodInfo('<?php echo htmlspecialchars($lang === 'ar' && $method['name_ar'] ? $method['name_ar'] : $method['name']); ?>', '<?php echo htmlspecialchars($method['wallet_info']); ?>', '<?php echo htmlspecialchars($method['instructions']); ?>')"
                            class="w-full bg-gray-900/40 hover:bg-gray-800 py-2 rounded-lg text-sm transition-colors border border-gray-700 text-gray-300 flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo __('view'); ?></span>
                        </button>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="edit_payment_method.php?id=<?php echo $method['id']; ?>"
                                class="bg-gray-800 hover:bg-gray-700 py-2 rounded-lg text-sm transition-colors border border-gray-600 block text-center text-white">
                                <?php echo __('edit'); ?>
                            </a>
                            <button
                                onclick="confirmDeleteMethod(<?php echo $method['id']; ?>, '<?php echo htmlspecialchars($lang === 'ar' && $method['name_ar'] ? $method['name_ar'] : $method['name']); ?>')"
                                class="bg-red-900/30 hover:bg-red-900/50 py-2 rounded-lg text-sm transition-colors border border-red-500/30 text-red-400 hover:text-red-300"><?php echo __('delete'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($methods)): ?>
            <div class="text-center py-16 glass-card rounded-2xl">
                <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <p class="text-gray-400">
                    <?php echo $lang === 'ar' ? 'لا توجد وسائل دفع. قم بإضافة وسيلة دفع جديدة.' : 'No payment methods found. Add a new payment method.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Method Info Modal -->
<div id="methodModal"
    class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    onclick="closeMethodModal(event)">
    <div class="glass-card rounded-2xl p-6 max-w-lg w-full" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-white" id="methodTitle"></h3>
            <button onclick="closeMethodModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <div class="space-y-4">
            <div class="bg-gray-900/50 rounded-lg p-4 border border-gray-700">
                <p class="text-sm text-gray-400 mb-2">
                    <?php echo __('wallet_info_label'); ?>:
                </p>
                <code class="text-indigo-300 font-mono text-sm break-all" id="methodWallet"></code>
            </div>
            <div class="bg-gray-900/50 rounded-lg p-4 border border-gray-700">
                <p class="text-sm text-gray-400 mb-2">
                    <?php echo __('instructions'); ?>:
                </p>
                <p class="text-gray-300 text-sm whitespace-pre-wrap" id="methodInstructions"></p>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal"
    class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    onclick="closeDeleteModal(event)">
    <div class="glass-card rounded-2xl p-6 max-w-md w-full" onclick="event.stopPropagation()">
        <div class="flex items-center justify-center mb-4">
            <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center">
                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
        </div>

        <h3 class="text-xl font-bold text-white text-center mb-2">
            <?php echo $lang === 'ar' ? 'تأكيد الحذف' : 'Confirm Delete'; ?>
        </h3>

        <p class="text-gray-400 text-center mb-6" id="deleteMessage"></p>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="closeDeleteModal()"
                class="bg-gray-800 hover:bg-gray-700 text-white py-2.5 rounded-xl transition-colors font-medium">
                <?php echo $lang === 'ar' ? 'إلغاء' : 'Cancel'; ?>
            </button>
            <button onclick="executeDelete()"
                class="bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl transition-colors font-medium">
                <?php echo $lang === 'ar' ? 'حذف' : 'Delete'; ?>
            </button>
        </div>
    </div>
</div>

<script>
    let deleteId = null;

    function showMethodInfo(name, wallet, instructions) {
        document.getElementById('methodTitle').textContent = name;
        document.getElementById('methodWallet').textContent = wallet || '<?php echo $lang === 'ar' ? 'غير متوفر' : 'Not available'; ?>';
        document.getElementById('methodInstructions').textContent = instructions || '<?php echo $lang === 'ar' ? 'لا توجد تعليمات' : 'No instructions'; ?>';
        document.getElementById('methodModal').classList.remove('hidden');
    }

    function closeMethodModal(event) {
        if (!event || event.target.id === 'methodModal') {
            document.getElementById('methodModal').classList.add('hidden');
        }
    }

    function confirmDeleteMethod(id, name) {
        deleteId = id;
        const message = '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف وسيلة الدفع' : 'Are you sure you want to delete payment method'; ?>' + ' ' + name + '?';
        document.getElementById('deleteMessage').textContent = message;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal(event) {
        if (!event || event.target.id === 'deleteModal') {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteId = null;
        }
    }

    function executeDelete() {
        if (deleteId) {
            window.location.href = 'payment_methods.php?delete=' + deleteId;
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>