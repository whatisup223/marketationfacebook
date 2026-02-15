<?php
include '../includes/header.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = $_POST['business_name'] ?? '';
    $business_desc = $_POST['business_description'] ?? '';
    $products = $_POST['products_services'] ?? '';
    $tone = $_POST['tone_of_voice'] ?? 'friendly';
    $custom = $_POST['custom_instructions'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // simplistic upsert
    $sql = "INSERT INTO ai_advisor_settings (user_id, business_name, business_description, products_services, tone_of_voice, custom_instructions, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            business_name = VALUES(business_name),
            business_description = VALUES(business_description),
            products_services = VALUES(products_services),
            tone_of_voice = VALUES(tone_of_voice),
            custom_instructions = VALUES(custom_instructions),
            is_active = VALUES(is_active)";

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$user_id, $business_name, $business_desc, $products, $tone, $custom, $is_active])) {
        $message = __('settings_saved_success');
    } else {
        $message = __('save_failed');
    }
}

// Fetch Current Settings
$stmt = $pdo->prepare("SELECT * FROM ai_advisor_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Defaults
$s = [
    'business_name' => $settings['business_name'] ?? '',
    'business_description' => $settings['business_description'] ?? '',
    'products_services' => $settings['products_services'] ?? '',
    'tone_of_voice' => $settings['tone_of_voice'] ?? 'friendly',
    'custom_instructions' => $settings['custom_instructions'] ?? '',
    'is_active' => $settings['is_active'] ?? 0
];

?>

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <?php include '../includes/user_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 p-4 lg:p-8 ml-0 lg:ml-64 transition-all duration-300">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">
                        <?php echo __('ai_advisor_settings'); ?>
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">
                        <?php echo __('ai_advisor_desc'); ?>
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 mb-6">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">

                <!-- Active Toggle -->
                <div class="glass-panel p-6 rounded-2xl border border-white/10 bg-gray-800/40 backdrop-blur-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-white">
                                <?php echo __('enable_ai_advisor'); ?>
                            </h3>
                            <p class="text-sm text-gray-400">
                                <?php echo __('enable_ai_advisor_desc'); ?>
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" class="sr-only peer" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                            <div
                                class="w-14 h-7 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600">
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Business Info -->
                <div
                    class="glass-panel p-6 rounded-2xl border border-white/10 bg-gray-800/40 backdrop-blur-xl space-y-6">
                    <h3 class="text-lg font-bold text-white border-b border-white/5 pb-4">
                        <?php echo __('business_profile'); ?>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <?php echo __('business_name'); ?>
                            </label>
                            <input type="text" name="business_name"
                                value="<?php echo htmlspecialchars($s['business_name']); ?>"
                                class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                placeholder="<?php echo __('business_name_placeholder'); ?>">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <?php echo __('tone_of_voice'); ?>
                            </label>
                            <select name="tone_of_voice"
                                class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                <option value="friendly" <?php echo $s['tone_of_voice'] == 'friendly' ? 'selected' : ''; ?>><?php echo __('tone_friendly'); ?></option>
                                <option value="professional" <?php echo $s['tone_of_voice'] == 'professional' ? 'selected' : ''; ?>><?php echo __('tone_professional'); ?></option>
                                <option value="urgent" <?php echo $s['tone_of_voice'] == 'urgent' ? 'selected' : ''; ?>>
                                    <?php echo __('tone_urgent'); ?>
                                </option>
                                <option value="empathetic" <?php echo $s['tone_of_voice'] == 'empathetic' ? 'selected' : ''; ?>><?php echo __('tone_empathetic'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <?php echo __('business_description'); ?>
                        </label>
                        <textarea name="business_description" rows="3"
                            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                            placeholder="<?php echo __('business_desc_placeholder'); ?>"><?php echo htmlspecialchars($s['business_description']); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <?php echo __('products_services'); ?>
                        </label>
                        <textarea name="products_services" rows="3"
                            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                            placeholder="<?php echo __('products_services_placeholder'); ?>"><?php echo htmlspecialchars($s['products_services']); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <?php echo __('custom_instructions'); ?> (<?php echo __('optional'); ?>)
                        </label>
                        <textarea name="custom_instructions" rows="2"
                            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                            placeholder="<?php echo __('custom_instructions_placeholder'); ?>"><?php echo htmlspecialchars($s['custom_instructions']); ?></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit"
                        class="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-1">
                        <?php echo __('save_settings'); ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>