<?php
include '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Handle Add Account Logic
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $user_id = $_SESSION['user_id'];
    $pdo = getDB();
    $fb_name = trim($_POST['fb_name']);
    $fb_id = trim($_POST['fb_id']);
    $access_token = trim($_POST['access_token']);

    if (empty($fb_name) || empty($access_token)) {
        $error = __('error_fields_required');
    } else {
        try {
            $insert_name = !empty($fb_name) ? $fb_name : 'New Account';
            $stmt = $pdo->prepare("INSERT INTO fb_accounts (user_id, fb_name, fb_id, access_token, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $insert_name, $fb_id, $access_token]);
            // Redirect to same page with success msg
            header("Location: settings.php?msg=account_added");
            exit;
        } catch (PDOException $e) {
            $error = "DB Error: " . $e->getMessage();
        }
    }
}

// Handle Delete Account Logic
if (isset($_GET['delete_account'])) {
    $user_id = $_SESSION['user_id'];
    $pdo = getDB();
    $del_id = $_GET['delete_account'];
    try {
        $stmt = $pdo->prepare("DELETE FROM fb_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$del_id, $user_id]);
        header("Location: settings.php?msg=account_deleted");
        exit;
    } catch (PDOException $e) {
        $error = "DB Error: " . $e->getMessage();
    }
}

// Check for success message
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'account_added') {
        $message = __('msg_account_linked');
    } elseif ($_GET['msg'] === 'account_deleted') {
        $message = __('msg_account_deleted');
    }
}

// Fetch Linked Accounts
$user_id = $_SESSION['user_id'];
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM fb_accounts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('settings');
include '../includes/header.php';

?>

<div id="main-user-container" class="main-user-container flex min-h-screen pt-4">
    <?php include '../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Header -->
            <h2 class="text-3xl font-black text-white">
                <?php echo __('settings'); ?>
            </h2>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-xl mb-6">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div x-data="{ activeTab: 'connections' }">
                <!-- Tabs Navigation (SaaS Style) -->
                <div class="w-full relative overflow-hidden">
                    <div class="flex overflow-x-auto pb-4 scrollbar-hide touch-pan-x -mx-4 px-4 md:mx-0 md:px-0"
                        style="scroll-behavior: smooth; -webkit-overflow-scrolling: touch;">
                        <div class="flex gap-2 whitespace-nowrap min-w-max">
                            <button @click="activeTab = 'connections'"
                                :class="activeTab === 'connections' ? 'bg-indigo-600/20 text-indigo-400 border-indigo-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border-white/5'"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all border flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826L10.242 9.172a4 4 0 015.656 0l4 4a4 4 0 11-5.656 5.656l-1.101-1.102">
                                    </path>
                                </svg>
                                <?php echo __('connection_settings'); ?>
                            </button>

                            <button @click="activeTab = 'smtp'"
                                :class="activeTab === 'smtp' ? 'bg-indigo-600/20 text-indigo-400 border-indigo-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border-white/5'"
                                class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all border flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <?php echo __('smtp_settings'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Connection Settings Tab -->
                <div x-show="activeTab === 'connections'" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8" x-data="connectionSettings()">

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <!-- Accounts Manager (Left) -->
                        <div class="glass-panel p-8 rounded-[2rem] border border-white/5 relative overflow-hidden flex flex-col h-full"
                            x-data="{ showAddForm: <?php echo empty($all_accounts) ? 'true' : 'false'; ?> }">
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/10 blur-3xl -mr-16 -mt-16 pointer-events-none">
                            </div>

                            <!-- Header Area -->
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h3 class="text-xl font-bold text-white flex items-center gap-3">
                                    <div class="p-2.5 bg-indigo-600/20 rounded-xl border border-indigo-500/10">
                                        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path x-show="showAddForm" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z">
                                            </path>
                                            <path x-show="!showAddForm" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                            </path>
                                        </svg>
                                    </div>
                                    <span
                                        x-text="showAddForm ? '<?php echo __('link_new_account'); ?>' : '<?php echo __('your_linked_accounts'); ?>'"></span>
                                </h3>

                                <template x-if="!showAddForm">
                                    <button @click="showAddForm = true"
                                        class="p-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white transition-colors shadow-lg shadow-indigo-600/20"
                                        title="<?php echo __('add_account'); ?>">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </button>
                                </template>
                                <template x-if="showAddForm && <?php echo !empty($all_accounts) ? 'true' : 'false'; ?>">
                                    <button @click="showAddForm = false"
                                        class="text-xs text-gray-400 hover:text-white underline transition-colors">
                                        <?php echo __('cancel'); ?>
                                    </button>
                                </template>
                            </div>

                            <!-- List of Accounts -->
                            <template x-if="!showAddForm">
                                <div class="space-y-4 overflow-y-auto max-h-[500px] custom-scrollbar pr-2 -mr-2">
                                    <?php if (empty($all_accounts)): ?>
                                        <div class="text-center py-8 text-gray-500">
                                            <?php echo __('no_accounts_linked'); ?>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($all_accounts as $acc): ?>
                                            <div
                                                class="group bg-white/5 hover:bg-white/10 border border-white/5 rounded-xl p-4 transition-all flex items-center justify-between gap-4">
                                                <div class="flex items-center gap-4 min-w-0">
                                                    <!-- Avatar Placeholder -->
                                                    <!-- Facebook Icon -->
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-[#1877F2] flex items-center justify-center text-white shrink-0 shadow-lg shadow-[#1877F2]/20">
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                                            <path
                                                                d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036c-2.148 0-2.971.956-2.971 3.594v.376h3.617l-.571 3.667h-3.046v7.98c5-.999 9.049-6.393 9.049-11.415C23 6.988 18.077 2 12.077 2s-10.923 4.988-10.923 11.716c0 5.022 4.049 10.416 9.049 11.415z">
                                                            </path>
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between mb-1">
                                                            <h4 class="text-white font-bold text-sm truncate">
                                                                <?php echo htmlspecialchars($acc['fb_name']); ?>
                                                            </h4>
                                                            <!-- Active Status Badge -->
                                                            <?php if ($acc['is_active']): ?>
                                                                <span
                                                                    class="text-[10px] uppercase font-bold text-green-400 bg-green-500/10 border border-green-500/20 px-2 py-0.5 rounded-full flex items-center gap-1">
                                                                    <span
                                                                        class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                                    <?php echo __('status_active'); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span
                                                                    class="text-[10px] uppercase font-bold text-red-400 bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded-full">
                                                                    <?php echo __('status_inactive'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="space-y-1">
                                                            <div
                                                                class="flex items-center gap-2 text-[10px] text-gray-500 font-mono">
                                                                <span>ID: <?php echo $acc['fb_id']; ?></span>
                                                            </div>

                                                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                                                <!-- Token Type -->
                                                                <span
                                                                    class="text-[10px] bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded flex items-center gap-1">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                                        stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    توكن ثابت / دائم
                                                                </span>
                                                                <!-- Expiry -->
                                                                <span
                                                                    class="text-[10px] bg-white/5 text-gray-300 border border-white/10 px-1.5 py-0.5 rounded flex items-center gap-1">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                                        stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    مدى الحياة
                                                                </span>
                                                                <!-- Token Checker Link -->
                                                                <a href="https://developers.facebook.com/tools/debug/accesstoken/?access_token=<?php echo $acc['access_token']; ?>"
                                                                    target="_blank"
                                                                    class="text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-1.5 py-0.5 rounded flex items-center gap-1 hover:bg-emerald-500/20 transition-colors">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                                        stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                                                    </svg>
                                                                    فاحص التوكن
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <a href="?delete_account=<?php echo $acc['id']; ?>"
                                                        onclick="return confirm('<?php echo __('confirm_delete_account'); ?>')"
                                                        class="p-2 hover:bg-red-500/20 text-gray-400 hover:text-red-500 rounded-lg transition-colors"
                                                        title="<?php echo __('delete'); ?>">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </template>

                            <!-- Add Account / Connect Button -->
                            <template x-if="showAddForm">
                                <div
                                    class="flex-1 flex flex-col items-center justify-center animate-in fade-in slide-in-from-right-4 duration-300 gap-6 text-center">

                                    <?php
                                    // Fetch App ID for button
                                    $stmt_appid = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'fb_app_id'");
                                    $fb_app_id = $stmt_appid->fetchColumn();

                                    if ($fb_app_id):
                                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                                        $host = $_SERVER['HTTP_HOST'];
                                        $current_path = strtok($_SERVER["REQUEST_URI"], '?');
                                        // Assume callback is at user/fb_callback.php relative to root? No, relative to current usually.
                                        // user/settings.php is current. user/fb_callback.php is sibling.
                                        $redirect_uri = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/fb_callback.php';

                                        // CSRF Protection
                                        $_SESSION['fb_oauth_state'] = bin2hex(random_bytes(16));

                                        $permissions = [
                                            'public_profile',
                                            'pages_show_list',
                                            'pages_read_engagement',
                                            'pages_manage_metadata',
                                            'pages_read_user_content',
                                            'pages_manage_posts',
                                            'pages_messaging',
                                            // Instagram Permissions
                                            'instagram_basic',
                                            'instagram_manage_comments',
                                            'instagram_manage_messages',
                                            'instagram_content_publish'
                                        ];
                                        $login_url = "https://www.facebook.com/v18.0/dialog/oauth?client_id={$fb_app_id}&redirect_uri=" . urlencode($redirect_uri) . "&state={$_SESSION['fb_oauth_state']}&scope=" . implode(',', $permissions);
                                        ?>

                                        <div class="space-y-4 max-w-sm">
                                            <div
                                                class="w-16 h-16 bg-[#1877F2]/10 rounded-full flex items-center justify-center mx-auto text-[#1877F2]">
                                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                                </svg>
                                            </div>
                                            <h3 class="text-xl font-bold text-white">
                                                <?php echo __('connect_facebook_title') ?? 'Connect Facebook'; ?>
                                            </h3>
                                            <p class="text-sm text-gray-400 leading-relaxed">
                                                <?php echo __('connect_facebook_desc') ?? 'Connect your Facebook account to manage pages, auto-reply to comments, and schedule posts automatically.'; ?>
                                            </p>
                                        </div>

                                        <a href="<?php echo htmlspecialchars($login_url); ?>"
                                            class="px-8 py-4 bg-[#1877F2] hover:bg-[#166fe5] text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-all transform hover:-translate-y-1 flex items-center gap-3 w-full max-w-xs justify-center group">
                                            <svg class="w-6 h-6 transition-transform group-hover:scale-110"
                                                fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                            </svg>
                                            <?php echo __('btn_connect_facebook') ?? 'Continue with Facebook'; ?>
                                        </a>

                                        <p class="text-xs text-gray-500 mt-4">
                                            <?php echo __('secure_connection_note') ?? 'We adhere to strict Facebook data privacy policies.'; ?>
                                        </p>

                                    <?php else: ?>
                                        <div
                                            class="text-center p-8 border border-yellow-500/20 bg-yellow-500/5 rounded-2xl">
                                            <div
                                                class="w-12 h-12 bg-yellow-500/10 rounded-full flex items-center justify-center mx-auto text-yellow-500 mb-4">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            </div>
                                            <h3 class="text-white font-bold mb-2"><?php echo __('config_required_title'); ?>
                                            </h3>
                                            <p class="text-gray-400 text-sm">
                                                <?php echo __('config_required_desc'); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </template>
                        </div>

                        <!-- Webhook Configuration (Right) -->
                        <div
                            class="glass-panel p-8 rounded-[2rem] border border-amber-500/20 bg-amber-500/5 relative overflow-hidden flex flex-col h-full">
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-amber-600/10 blur-3xl -mr-16 -mt-16 pointer-events-none">
                            </div>

                            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-3 relative z-10">
                                <div class="p-2.5 bg-amber-500/20 rounded-xl border border-amber-500/10">
                                    <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <?php echo __('webhook_configuration'); ?>
                            </h3>

                            <div class="relative z-10 flex-1 flex flex-col">
                                <div class="bg-amber-900/20 border border-amber-500/10 rounded-xl p-4 mb-8">
                                    <div class="flex gap-3">
                                        <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                            </path>
                                        </svg>
                                        <p class="text-sm text-amber-200/80 leading-relaxed">
                                            <?php echo __('webhook_global_warning'); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <!-- Callback URL -->
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-amber-500/70 uppercase tracking-widest mb-2 ml-1"><?php echo __('callback_url'); ?></label>
                                        <div class="relative group">
                                            <input type="text" readonly :value="webhookUrl"
                                                class="w-full bg-black/40 border border-amber-500/20 rounded-xl text-sm text-gray-300 p-4 font-mono truncate focus:outline-none pr-14 transition-colors group-hover:border-amber-500/40">
                                            <button @click="copyToClipboard(webhookUrl)"
                                                class="absolute right-2 top-2 p-2 bg-amber-600/20 hover:bg-amber-600 text-amber-500 hover:text-white rounded-lg transition-all"
                                                title="<?php echo __('copy'); ?>">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                                                    </path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Verify Token -->
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-amber-500/70 uppercase tracking-widest mb-2 ml-1"><?php echo __('verify_token'); ?></label>
                                        <div class="relative group">
                                            <input type="text" readonly :value="verifyToken"
                                                class="w-full bg-black/40 border border-amber-500/20 rounded-xl text-sm text-gray-300 p-4 font-mono truncate focus:outline-none pr-14 transition-colors group-hover:border-amber-500/40">
                                            <button @click="copyToClipboard(verifyToken)"
                                                class="absolute right-2 top-2 p-2 bg-amber-600/20 hover:bg-amber-600 text-amber-500 hover:text-white rounded-lg transition-all"
                                                title="<?php echo __('copy'); ?>">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                                                    </path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="pt-4">
                                        <p class="text-xs text-gray-400 text-center leading-relaxed italic opacity-60">
                                            <?php echo __('webhook_help') ?? 'Copy these credentials to your Facebook Developer App Basic Settings.'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMTP Settings Tab -->
                <div x-show="activeTab === 'smtp'" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6" x-data="smtpSettings()">

                    <div class="glass-panel p-8 rounded-[2rem] border border-white/5 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 blur-[80px] pointer-events-none">
                        </div>

                        <!-- Header -->
                        <div
                            class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6 border-b border-white/5 pb-8">
                            <div class="flex items-center gap-4">
                                <div
                                    class="p-3 bg-indigo-600/20 rounded-2xl border border-indigo-500/20 md:block hidden">
                                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white mb-1">
                                        <?php echo __('smtp_configuration'); ?>
                                    </h3>
                                    <p class="text-gray-400 text-sm"><?php echo __('smtp_user_config_desc'); ?></p>
                                </div>
                            </div>

                            <!-- Enable Switch -->
                            <div class="flex items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/5 pl-4">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-300">
                                    <?php echo __('enable_email_alerts'); ?>
                                </span>
                                <div class="relative inline-block w-12 align-middle select-none">
                                    <input type="checkbox" x-model="enabled" id="toggleSmtp" class="peer sr-only" />
                                    <label for="toggleSmtp"
                                        class="block h-7 w-12 rounded-full bg-gray-700 cursor-pointer transition-colors peer-checked:bg-indigo-500 relative">
                                        <span
                                            class="absolute left-1 top-1 h-5 w-5 bg-white rounded-full transition-transform peer-checked:translate-x-5 shadow-sm"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Form Content -->
                        <form @submit.prevent="saveSettings()" class="relative z-10 space-y-8">

                            <!-- Section 1: Server Config -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <div class="lg:col-span-1">
                                    <h4 class="text-lg font-bold text-white mb-2"><?php echo __('server_details'); ?>
                                    </h4>
                                    <p class="text-xs text-gray-400 leading-relaxed">
                                        <?php echo __('server_details_desc'); ?>
                                    </p>
                                </div>
                                <div
                                    class="lg:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6 bg-black/20 p-6 rounded-2xl border border-white/5">
                                    <div class="md:col-span-2">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_host'); ?></label>
                                        <input type="text" x-model="host" placeholder="smtp.example.com"
                                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all font-mono text-sm">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_port'); ?></label>
                                        <input type="number" x-model="port" placeholder="587"
                                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all font-mono text-sm">
                                    </div>
                                    <div class="md:col-span-3">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_encryption'); ?></label>
                                        <div class="grid grid-cols-3 gap-4">
                                            <label class="cursor-pointer">
                                                <input type="radio" value="tls" x-model="encryption"
                                                    class="peer sr-only">
                                                <div
                                                    class="text-center py-2 rounded-lg border border-white/10 bg-white/5 text-gray-400 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-500 transition-all text-sm font-bold">
                                                    TLS</div>
                                            </label>
                                            <label class="cursor-pointer">
                                                <input type="radio" value="ssl" x-model="encryption"
                                                    class="peer sr-only">
                                                <div
                                                    class="text-center py-2 rounded-lg border border-white/10 bg-white/5 text-gray-400 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-500 transition-all text-sm font-bold">
                                                    SSL</div>
                                            </label>
                                            <label class="cursor-pointer">
                                                <input type="radio" value="none" x-model="encryption"
                                                    class="peer sr-only">
                                                <div
                                                    class="text-center py-2 rounded-lg border border-white/10 bg-white/5 text-gray-400 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-500 transition-all text-sm font-bold">
                                                    None</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Authentication -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-6 border-t border-white/5">
                                <div class="lg:col-span-1">
                                    <h4 class="text-lg font-bold text-white mb-2"><?php echo __('auth_details'); ?></h4>
                                    <p class="text-xs text-gray-400 leading-relaxed">
                                        <?php echo __('auth_details_desc'); ?>
                                    </p>
                                </div>
                                <div
                                    class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 bg-black/20 p-6 rounded-2xl border border-white/5">
                                    <!-- User -->
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_username'); ?></label>
                                        <input type="text" x-model="username"
                                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                    <!-- Pass -->
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_password'); ?></label>
                                        <input type="password" x-model="password"
                                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Sender Info -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-6 border-t border-white/5">
                                <div class="lg:col-span-1">
                                    <h4 class="text-lg font-bold text-white mb-2"><?php echo __('sender_info'); ?></h4>
                                    <p class="text-xs text-gray-400 leading-relaxed">
                                        <?php echo __('sender_info_desc'); ?>
                                    </p>
                                </div>
                                <div
                                    class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 bg-black/20 p-6 rounded-2xl border border-white/5">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_from_email'); ?></label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-3.5 text-gray-500">@</span>
                                            <input type="email" x-model="from_email"
                                                class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 pl-1"><?php echo __('smtp_from_name'); ?></label>
                                        <input type="text" x-model="from_name"
                                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Actions Footer -->
                            <div class="flex items-center justify-end gap-4 pt-8 border-t border-white/5">
                                <button type="submit"
                                    class="px-8 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all transform active:scale-95 flex items-center gap-2">
                                    <span x-show="!saving"><?php echo __('save_settings'); ?></span>
                                    <span x-show="saving" class="flex items-center gap-2">
                                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        <?php echo __('saving_settings'); ?>...
                                    </span>
                                </button>
                            </div>

                            <!-- Status Message -->
                            <div x-show="message"
                                :class="status === 'success' ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400'"
                                class="p-4 rounded-xl border flex items-center gap-3 animate-in fade-in slide-in-from-bottom-2">
                                <span x-text="message" class="font-medium text-sm"></span>
                            </div>
                        </form>
                    </div>

                    <!-- Test Connection Card -->
                    <div class="glass-panel p-8 rounded-[2rem] border border-emerald-500/20 bg-emerald-500/5 relative overflow-hidden"
                        x-show="enabled">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6 relative z-10">
                            <div class="flex items-center gap-4">
                                <div
                                    class="p-3 bg-emerald-500/20 rounded-xl border border-emerald-500/20 text-emerald-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-white"><?php echo __('test_smtp_settings'); ?>
                                    </h4>
                                    <p class="text-xs text-emerald-400/70"><?php echo __('smtp_info_note'); ?></p>
                                </div>
                            </div>

                            <div class="flex w-full md:w-auto gap-2">
                                <input type="email" x-model="testEmail" placeholder="your@email.com"
                                    class="bg-black/40 border border-emerald-500/20 rounded-xl px-4 py-2.5 text-white/90 placeholder-emerald-500/30 text-sm focus:outline-none focus:border-emerald-500/50 w-full md:w-64">
                                <button @click="testConnection()"
                                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all active:scale-95 whitespace-nowrap text-sm flex items-center gap-2">
                                    <span x-show="!testing"><?php echo __('send_test_email'); ?></span>
                                    <span x-show="testing" class="animate-spin">
                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <style>
            /* Custom Toggle Styles */
            .toggle-checkbox:checked {
                right: 0;
            }

            .toggle-checkbox {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
        </style>

        <script>
            function smtpSettings() {
                return {
                    enabled: false,
                    host: '',
                    port: '587',
                    encryption: 'tls',
                    username: '',
                    password: '',
                    from_email: '',
                    from_name: '',
                    saving: false,
                    testing: false,

                    init() {
                        this.fetchSettings();
                    },

                    fetchSettings() {
                        fetch('ajax_settings.php?action=fetch_smtp')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.config) {
                                    const c = data.config;
                                    this.enabled = c.enabled === true || c.enabled === 'true' || c.enabled === 1;
                                    this.host = c.host || '';
                                    this.port = c.port || '587';
                                    this.encryption = c.encryption || 'tls';
                                    this.username = c.username || '';
                                    this.password = c.password || '';
                                    this.from_email = c.from_email || '';
                                    this.from_name = c.from_name || '';
                                }
                            });
                    },

                    saveSettings() {
                        this.saving = true;
                        const formData = new FormData();
                        formData.append('enabled', this.enabled ? 1 : 0);
                        formData.append('host', this.host);
                        formData.append('port', this.port);
                        formData.append('encryption', this.encryption);
                        formData.append('username', this.username);
                        formData.append('password', this.password);
                        formData.append('from_email', this.from_email);
                        formData.append('from_name', this.from_name);

                        fetch('ajax_settings.php?action=save_smtp', {
                            method: 'POST',
                            body: formData
                        })
                            .then(res => res.json())
                            .then(data => {
                                this.saving = false;
                                alert(data.message || data.error);
                            })
                            .catch(() => {
                                this.saving = false;
                                alert('Network Error');
                            });
                    },

                    testSmtp() {
                        this.testing = true;
                        fetch('ajax_settings.php?action=test_smtp')
                            .then(res => res.json())
                            .then(data => {
                                this.testing = false;
                                alert(data.message || data.error);
                            })
                            .catch(() => {
                                this.testing = false;
                                alert('Network Error');
                            });
                    }
                }
            }

            function connectionSettings() {
                return {
                    webhookUrl: '',
                    verifyToken: '',

                    init() {
                        this.fetchWebhookInfo();
                    },

                    fetchWebhookInfo() {
                        fetch('ajax_auto_reply.php?action=get_webhook_info')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    this.webhookUrl = data.webhook_url;
                                    this.verifyToken = data.verify_token;
                                }
                            });
                    },

                    copyToClipboard(text) {
                        navigator.clipboard.writeText(text).then(() => {
                            alert('<?php echo __('copied'); ?>');
                        });
                    }
                }
            }
        </script>
    </div>
</div>

<?php include '../includes/footer.php'; ?>