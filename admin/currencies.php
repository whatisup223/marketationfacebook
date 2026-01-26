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
        $pdo->prepare("DELETE FROM currencies WHERE id = ?")->execute([$id]);
        header("Location: currencies.php?deleted=1");
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
                <?php echo $lang === 'ar' ? 'تم حذف العملة بنجاح!' : 'Currency deleted successfully!'; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/20 text-red-300 p-4 rounded-xl mb-6 border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold"><?php echo __('manage_currencies'); ?></h1>
            <a href="add_currency.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                + <?php echo __('add_currency'); ?>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $stmt = $pdo->query("SELECT * FROM currencies");
            while ($curr = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <div class="glass-card p-6 rounded-2xl relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <img src="<?php echo $curr['image']; ?>" class="w-10 h-10 rounded-full bg-gray-700" alt="">
                            <div>
                                <div class="font-bold">
                                    <?php echo $curr['symbol']; ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo $curr['name_en']; ?>
                                </div>
                            </div>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-lg <?php echo $curr['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
                            <?php echo $curr['is_active'] ? __('active') : __('inactive'); ?>
                        </span>
                    </div>

                    <div class="space-y-3 text-sm text-gray-300 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-500"><?php echo __('buy_price'); ?></span>
                            <span class="font-mono text-green-400">
                                <?php echo $curr['exchange_rate_buy'] ?? 0; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500"><?php echo __('sell_price'); ?></span>
                            <span class="font-mono text-indigo-400">
                                <?php echo $curr['exchange_rate_sell'] ?? 0; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500"><?php echo __('reserve_amount'); ?></span>
                            <span class="font-mono">
                                <?php echo number_format($curr['reserve']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <button
                            onclick="showCurrencyInfo('<?php echo htmlspecialchars($curr['symbol']); ?>', '<?php echo htmlspecialchars($curr['name_en']); ?>', '<?php echo $curr['exchange_rate_buy'] ?? 0; ?>', '<?php echo $curr['exchange_rate_sell'] ?? 0; ?>', '<?php echo number_format($curr['reserve']); ?>')"
                            class="w-full bg-gray-900/40 hover:bg-gray-800 py-2 rounded-lg text-sm transition-colors border border-gray-700 text-gray-300 flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo __('view'); ?></span>
                        </button>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="edit_currency.php?id=<?php echo $curr['id']; ?>"
                                class="bg-gray-800 hover:bg-gray-700 py-2 rounded-lg text-sm transition-colors border border-gray-600 block text-center text-white"><?php echo __('edit'); ?></a>
                            <button
                                onclick="confirmDelete(<?php echo $curr['id']; ?>, '<?php echo htmlspecialchars($curr['symbol']); ?>')"
                                class="bg-red-900/30 hover:bg-red-900/50 py-2 rounded-lg text-sm transition-colors border border-red-500/30 text-red-400 hover:text-red-300"><?php echo __('delete'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Currency Info Modal -->
<div id="currencyModal"
    class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    onclick="closeCurrencyModal(event)">
    <div class="glass-card rounded-2xl p-6 max-w-md w-full" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-white flex items-center">
                <span id="modalSymbolBadge"
                    class="bg-indigo-500/20 text-indigo-400 px-3 py-1 rounded-lg text-sm mr-3"></span>
                <span id="modalTitle"></span>
            </h3>
            <button onclick="closeCurrencyModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <div class="space-y-4">
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-700/50">
                <div class="flex justify-between mb-3 border-b border-gray-800 pb-2">
                    <span class="text-gray-400 text-sm"><?php echo __('buy_price'); ?></span>
                    <span id="modalBuyRate" class="font-mono text-green-400 font-bold"></span>
                </div>
                <div class="flex justify-between mb-3 border-b border-gray-800 pb-2">
                    <span class="text-gray-400 text-sm"><?php echo __('sell_price'); ?></span>
                    <span id="modalSellRate" class="font-mono text-indigo-400 font-bold"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400 text-sm"><?php echo __('reserve_amount'); ?></span>
                    <span id="modalReserve" class="font-mono text-white font-bold"></span>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <button onclick="closeCurrencyModal()"
                class="w-full bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-xl transition-colors">
                <?php echo __('close') ?? 'إغلاق'; ?>
            </button>
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

    function showCurrencyInfo(symbol, name, buyRate, sellRate, reserve) {
        document.getElementById('modalSymbolBadge').textContent = symbol;
        document.getElementById('modalTitle').textContent = name;
        document.getElementById('modalBuyRate').textContent = buyRate;
        document.getElementById('modalSellRate').textContent = sellRate;
        document.getElementById('modalReserve').textContent = reserve + ' ' + symbol;
        document.getElementById('currencyModal').classList.remove('hidden');
    }

    function closeCurrencyModal(event) {
        if (!event || event.target.id === 'currencyModal') {
            document.getElementById('currencyModal').classList.add('hidden');
        }
    }

    function confirmDelete(id, symbol) {
        deleteId = id;
        const message = '<?php echo $lang === 'ar' ? 'هل أنت متأكد من حذف العملة' : 'Are you sure you want to delete currency'; ?>' + ' ' + symbol + '?';
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
            window.location.href = 'currencies.php?delete=' + deleteId;
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>