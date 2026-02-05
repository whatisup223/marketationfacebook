<?php
ob_start();
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$message = '';
$error = '';

// Handle Update Token (Recovery)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account_token'])) {
    $acc_id_to_update = $_POST['account_id'];
    $new_token = trim($_POST['new_access_token']);

    if (!empty($acc_id_to_update) && !empty($new_token)) {
        try {
            $stmt = $pdo->prepare("UPDATE fb_accounts SET access_token = ?, is_active = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_token, $acc_id_to_update, $user_id]);
            header("Location: fb_accounts.php?msg=token_updated");
            exit;
        } catch (PDOException $e) {
            $error = "DB Error: " . $e->getMessage();
        }
    }
}

// Handle Sync Pages
if (isset($_GET['sync'])) {
    require_once __DIR__ . '/../includes/facebook_api.php';
    $acc_id = $_GET['sync'];

    // Scoped Clean: Only clean duplicates for THIS account to prevent UI issues, 
    // but without touching other accounts or users.
    $pdo->prepare("DELETE FROM fb_pages WHERE account_id = ? AND id NOT IN (
        SELECT max_id FROM (SELECT MAX(id) as max_id FROM fb_pages WHERE account_id = ? GROUP BY page_id) as t
    )")->execute([$acc_id, $acc_id]);

    // Ensure the account actually belongs to the user

    $stmt = $pdo->prepare("SELECT * FROM fb_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$acc_id, $user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        $fb = new FacebookAPI();
        $response = $fb->getAccounts($account['access_token']);

        if (isset($response['error'])) {
            if (is_array($response['error']) || is_object($response['error'])) {
                $error_str = json_encode($response['error']);
            } else {
                $error_str = (string) $response['error'];
            }

            if (strpos($error_str, 'Application request limit reached') !== false) {
                $error = __('msg_rate_limit_error');
            } elseif (strpos($error_str, 'Session has expired') !== false || strpos($error_str, 'Error validating access token') !== false) {
                // Token Expired - Trigger Modal AND Mark Inactive
                $token_error_account_id = $acc_id;
                $pdo->prepare("UPDATE fb_accounts SET is_active = 0 WHERE id = ?")->execute([$acc_id]);
                $error = __('token_expired_msg');
            } else {
                $error = "FB API: " . $error_str;
            }
        } elseif (isset($response['data'])) {
            $count = 0;
            // The UNIQUE KEY unique_page_id (page_id) ensures NO DUPLICATES are possible.
            // ON DUPLICATE KEY UPDATE will simply refresh the data for existing pages.
            $insertPage = $pdo->prepare("INSERT INTO fb_pages (account_id, page_name, page_id, page_access_token, category, picture_url) 
                                         VALUES (?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE 
                                            account_id = VALUES(account_id),
                                            page_name = VALUES(page_name), 
                                            page_access_token = VALUES(page_access_token), 
                                            category = VALUES(category),
                                            picture_url = VALUES(picture_url)");

            $total_pages_found = count($response['data']);
            $failed_pages = [];

            foreach ($response['data'] as $page) {
                try {
                    $picture = $page['picture']['data']['url'] ?? '';
                    // Ensure all required fields present
                    if (empty($page['id']) || empty($page['access_token'])) {
                        $failed_pages[] = "Page: " . ($page['name'] ?? 'Unknown') . " (Missing ID/Token)";
                        continue;
                    }

                    $result = $insertPage->execute([
                        $acc_id,
                        $page['name'] ?? 'Unknown Page',
                        trim($page['id']),
                        $page['access_token'],
                        $page['category'] ?? 'General',
                        $picture
                    ]);

                    if ($result) {
                        $count++;
                    } else {
                        $failed_pages[] = "Page: " . ($page['name'] ?? 'Unknown') . " (Insert Failed)";
                    }
                } catch (PDOException $e) {
                    $failed_pages[] = "Page: " . ($page['name'] ?? 'Unknown') . " Error: " . $e->getMessage();
                }
            }

            if (empty($failed_pages)) {
                header("Location: fb_accounts.php?msg=synced&count=" . $count);
                exit;
            } else {
                $message = sprintf(__('msg_sync_success'), $count);
                $error = "Warning: Some pages failed to sync:<br>" . implode("<br>", $failed_pages);
            }
        } else {
            $message = __('msg_no_pages');
        }
    }
}

// Handle Delete Account
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM fb_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: fb_accounts.php?msg=deleted");
    exit;
}

// Handle specific messages
// Handle specific messages
if (empty($message) && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = __('msg_account_deleted');
    } elseif ($_GET['msg'] === 'added') {
        $message = __('msg_account_linked');
    } elseif ($_GET['msg'] === 'synced' && isset($_GET['count'])) {
        $message = sprintf(__('msg_sync_success'), htmlspecialchars($_GET['count']));
    } elseif ($_GET['msg'] === 'token_updated') {
        $message = __('token_updated_success');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div id="main-user-container" class="main-user-container flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white"><?php echo __('fb_accounts'); ?></h1>
                <p class="text-gray-500 text-sm mt-1"><?php echo __('fb_accounts_desc'); ?></p>
            </div>
            <?php
            // Dynamic Status Logic
            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total, SUM(is_active) as active FROM fb_accounts WHERE user_id = ?");
            $stmt_count->execute([$user_id]);
            $stats = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $total_acc = (int) $stats['total'];
            $active_acc = (int) $stats['active'];

            $status_class = 'text-red-400 bg-red-500/10 border-red-500/20';
            $dot_class = 'bg-red-500';
            $status_text = __('not_connected');

            if ($total_acc > 0) {
                if ($active_acc == $total_acc) {
                    // All good
                    $status_class = 'text-green-400 bg-green-500/10 border-green-500/20';
                    $dot_class = 'bg-green-500 animate-pulse';
                    $status_text = __('connected');
                } else {
                    // Some expired
                    $status_class = 'text-orange-400 bg-orange-500/10 border-orange-500/20';
                    $dot_class = 'bg-orange-500 animate-pulse';
                    $status_text = __('token_expired');
                }
            }
            ?>
            <div
                class="flex items-center gap-2 text-xs font-bold <?php echo $status_class; ?> px-4 py-2 rounded-xl border">
                <div class="w-2 h-2 rounded-full <?php echo $dot_class; ?>">
                </div>
                <?php echo __('api_status'); ?>: <span
                    class="uppercase tracking-wide"><?php echo $status_text; ?></span>
            </div>
        </div>

        <?php if ($message || isset($_GET['msg'])): ?>
            <div id="success-alert"
                class="glass-card border-green-500/20 bg-green-500/5 p-4 rounded-2xl flex items-center justify-between gap-3 mb-8 animate-in fade-in slide-in-from-top-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-500/20 text-green-500 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="text-green-400 font-bold"><?php echo $message ?: __('save_changes'); ?></div>
                </div>
                <button onclick="document.getElementById('success-alert').remove()"
                    class="text-gray-400 hover:text-white transition-colors p-1 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div id="error-alert"
                class="glass-card border-red-500/20 bg-red-500/5 p-4 rounded-2xl flex items-center justify-between gap-3 mb-8 animate-in fade-in slide-in-from-top-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-500/20 text-red-500 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </div>
                    <div class="text-red-400 font-bold"><?php echo $error; ?></div>
                </div>
                <button onclick="document.getElementById('error-alert').remove()"
                    class="text-gray-400 hover:text-white transition-colors p-1 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Linked Accounts Section (Full Width) -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white"><?php echo __('your_linked_accounts'); ?></h2>
                <span
                    class="text-xs bg-gray-800 text-gray-400 px-3 py-1 rounded-full uppercase tracking-tighter font-bold"><?php echo __('manage_profiles'); ?></span>
            </div>
            <?php
            $stmt = $pdo->prepare("SELECT * FROM fb_accounts WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($accounts) > 0):
                foreach ($accounts as $acc):
                    ?>
                    <div
                        class="glass-card p-5 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between group mb-4 border border-white/5 hover:border-indigo-500/30 transition-all duration-300">
                        <div class="flex items-center gap-5 mb-4 sm:mb-0">
                            <div class="relative">
                                <div
                                    class="w-14 h-14 rounded-2xl bg-[#1877F2] flex items-center justify-center text-white shadow-xl shadow-blue-600/10">
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                    </svg>
                                </div>
                                <div
                                    class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-[#1e293b] animate-pulse">
                                </div>
                            </div>
                            <div>
                                <h3 class="font-bold text-white text-lg"><?php echo htmlspecialchars($acc['fb_name']); ?></h3>
                                <div class="flex items-center gap-3">
                                    <p class="text-xs text-gray-500 font-mono"><?php echo __('fb_id_label'); ?>: <span
                                            class="text-gray-400"><?php echo $acc['fb_id'] ?: __('no_data'); ?></span></p>
                                    <!-- Hidden token for JS access -->
                                    <input type="hidden" class="account-current-token"
                                        value="<?php echo htmlspecialchars($acc['access_token']); ?>">
                                    <div id="account-status-badge-<?php echo $acc['id']; ?>"
                                        data-acc-id="<?php echo $acc['id']; ?>" class="account-status-container">
                                        <?php if ($acc['is_active']): ?>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <div
                                                    class="flex items-center gap-1.5 bg-indigo-500/5 px-2 py-0.5 rounded border border-indigo-500/10">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                                                    <span
                                                        class="text-[10px] text-indigo-400 font-bold uppercase tracking-wider"><?php echo __('active'); ?></span>
                                                </div>
                                                <div id="check-spinner-<?php echo $acc['id']; ?>"
                                                    class="flex items-center gap-1.5 opacity-50">
                                                    <svg class="animate-spin h-2.5 w-2.5 text-gray-400" fill="none"
                                                        viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                            stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor"
                                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                        </path>
                                                    </svg>
                                                    <span
                                                        class="text-[9px] text-gray-500 font-bold uppercase"><?php echo __('verifying'); ?>...</span>
                                                </div>
                                                <?php
                                                $type = !empty($acc['token_type']) ? $acc['token_type'] : 'Active';
                                                $is_long = ($type !== 'Short-lived');
                                                $localized_type = $type;
                                                if ($type === 'Long-lived')
                                                    $localized_type = __('token_long_lived');
                                                elseif ($type === 'Short-lived')
                                                    $localized_type = __('token_short_lived');
                                                elseif ($type === 'Long-lived / Indefinite' || $type === 'Static')
                                                    $localized_type = __('token_static');
                                                elseif ($type === 'Active')
                                                    $localized_type = __('active');

                                                $theme_color = $is_long ? 'blue' : 'purple';
                                                ?>
                                                <div
                                                    class="flex items-center gap-1.5 bg-<?php echo $theme_color; ?>-500/5 px-2 py-0.5 rounded border border-<?php echo $theme_color; ?>-500/10">
                                                    <span
                                                        class="text-[9px] font-bold text-<?php echo $theme_color; ?>-400 uppercase">
                                                        <?php echo $localized_type; ?>
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex items-center gap-1.5 bg-gray-500/5 px-2 py-0.5 rounded border border-white/5">
                                                    <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="text-[9px] text-gray-400 font-bold">
                                                        <?php echo __('token_expiry'); ?>:
                                                        <?php echo !empty($acc['expires_at']) ? date('Y-m-d', strtotime($acc['expires_at'])) : __('token_never'); ?>
                                                    </span>
                                                </div>

                                                <a href="https://developers.facebook.com/tools/debug/accesstoken/?access_token=<?php echo urlencode($acc['access_token']); ?>"
                                                    target="_blank"
                                                    class="flex items-center gap-1 bg-white/5 hover:bg-white/10 px-2 py-0.5 rounded border border-white/5 text-[9px] text-gray-400 transition-all group/link"
                                                    title="Open in Official Facebook Token Debugger">
                                                    <svg class="w-2.5 h-2.5 group-hover/link:text-[#1877F2]" fill="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path
                                                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                                    </svg>
                                                    <span><?php echo __('token_debugger'); ?></span>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span
                                                class="text-[10px] text-red-400 font-bold bg-red-500/10 px-2 py-0.5 rounded border border-red-500/20 uppercase flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <?php echo __('token_expired'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 self-end sm:self-auto">
                            <?php
                            // Check if this account has pages already
                            $stmt_pcheck = $pdo->prepare("SELECT COUNT(*) FROM fb_pages WHERE account_id = ?");
                            $stmt_pcheck->execute([$acc['id']]);
                            $has_pages = $stmt_pcheck->fetchColumn() > 0;

                            if ($has_pages): ?>
                                <a href="?sync=<?php echo $acc['id']; ?>"
                                    class="px-5 py-2.5 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 rounded-2xl text-xs font-bold transition-all flex items-center gap-2 border border-indigo-500/20 shadow-lg shadow-indigo-500/5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    <?php echo __('update_sync'); ?>
                                </a>
                            <?php else: ?>
                                <a href="?sync=<?php echo $acc['id']; ?>"
                                    class="px-5 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-2xl text-xs font-bold transition-all flex items-center gap-2 border border-white/5">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    <?php echo __('btn_sync_pages'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $acc['id']; ?>"
                                onclick="return confirm('<?php echo __('confirm_delete_account'); ?>');"
                                class="p-2.5 text-red-400 hover:bg-red-500/10 rounded-2xl transition-colors border border-transparent hover:border-red-500/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                <div class="text-center py-16 text-gray-500 glass-card rounded-3xl border border-white/5 border-dashed">
                    <div
                        class="w-16 h-16 bg-gray-800/50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <p><?php echo __('no_accounts_linked'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Synced Pages Section -->
        <div class="glass-card rounded-3xl overflow-hidden border border-white/5 h-fit mb-8">
            <div class="p-6 border-b border-gray-700/50 bg-white/5">
                <h2 class="text-xl font-bold text-white"><?php echo __('synced_pages_title'); ?></h2>
                <p class="text-xs text-gray-500 mt-1"><?php echo __('synced_pages_subtitle'); ?></p>
            </div>
            <?php
            $pdo->exec("SET NAMES utf8mb4");
            // Use Robust subquery to ensure UI never shows duplicates while including all user's pages
            $stmt = $pdo->prepare("SELECT p.*, a.fb_name as account_name FROM fb_pages p 
                                    JOIN fb_accounts a ON p.account_id = a.id 
                                    WHERE p.id IN (
                                        SELECT MIN(p2.id) FROM fb_pages p2 
                                        JOIN fb_accounts a2 ON p2.account_id = a2.id 
                                        WHERE a2.user_id = ? 
                                        GROUP BY p2.page_id
                                    )
                                    ORDER BY p.created_at DESC");
            $stmt->execute([$user_id]);
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($pages) > 0):
                ?>
                <div class="overflow-y-auto max-h-[600px] custom-scrollbar">
                    <table class="w-full text-left">
                        <thead
                            class="bg-[#1e293b] text-gray-400 text-[10px] uppercase font-bold sticky top-0 z-10 shadow-lg shadow-black/20">
                            <tr>
                                <th class="px-6 py-4 tracking-widest"><?php echo __('page_info'); ?></th>
                                <th class="px-6 py-4 tracking-widest"><?php echo __('category'); ?></th>
                                <th class="px-6 py-4 text-right tracking-widest"><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <?php foreach ($pages as $page): ?>
                                <tr class="hover:bg-white/5 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="relative shrink-0">
                                                <?php if ($page['picture_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($page['picture_url']); ?>"
                                                        class="w-10 h-10 rounded-xl object-cover aspect-square ring-2 ring-white/5">
                                                <?php else: ?>
                                                    <div
                                                        class="w-10 h-10 rounded-xl bg-gray-700 flex items-center justify-center text-xs font-bold aspect-square">
                                                        P</div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-white group-hover:text-indigo-400 transition-colors">
                                                    <?php echo htmlspecialchars($page['page_name']); ?>
                                                </div>
                                                <div class="text-[10px] text-gray-500 font-medium">
                                                    <?php echo __('account_prefix'); ?><span
                                                        class="text-gray-400"><?php echo htmlspecialchars($page['account_name']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-3 py-1 bg-gray-800 text-gray-400 rounded-full text-[10px] font-bold"><?php echo htmlspecialchars($page['category']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="page_inbox.php?page_id=<?php echo $page['id']; ?>"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all transform hover:-translate-y-0.5 text-xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                                                </path>
                                            </svg>
                                            <?php echo __('open_inbox'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-16 text-center text-gray-500">
                    <div
                        class="w-16 h-16 bg-gray-800/30 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                    </div>
                    <p class="max-w-xs mx-auto text-sm leading-relaxed"><?php echo __('no_pages_synced_desc'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Clean URL query parameters to prevent message persistence on refresh
    if (window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.delete('msg');
        url.searchParams.delete('count');
        window.history.replaceState(null, '', url);
    }
</script>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const badges = document.querySelectorAll('.account-status-container');
        badges.forEach(badge => {
            checkAccountStatus(badge);
        });

        async function checkAccountStatus(badge) {
            const accId = badge.getAttribute('data-acc-id');
            if (!accId) return;

            try {
                const formData = new FormData();
                formData.append('account_id', accId);

                const response = await fetch('ajax_check_status.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                // Remove spinner
                const spinner = document.getElementById('check-spinner-' + accId);
                if (spinner) spinner.remove();

                if (data.status === 'active') {
                    const isLongLived = data.is_long_lived !== undefined ? data.is_long_lived : true;
                    badge.innerHTML = `
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <div class="flex items-center gap-1.5 bg-indigo-500/5 px-2 py-0.5 rounded border border-indigo-500/10">
                                <div class="w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                                <span class="text-[10px] text-indigo-400 font-bold uppercase tracking-wider"><?php echo __('active'); ?></span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-${isLongLived ? 'blue' : 'purple'}-500/5 px-2 py-0.5 rounded border border-${isLongLived ? 'blue' : 'purple'}-500/10">
                                <span class="text-[9px] font-bold text-${isLongLived ? 'blue' : 'purple'}-400 uppercase">
                                    ${data.token_type || 'Active'}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-gray-500/5 px-2 py-0.5 rounded border border-white/5">
                                <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="text-[9px] text-gray-400 font-bold">
                                    ${data.expiry_prefix || 'Expiry'}: ${data.expires_at || 'Never'}
                                </span>
                            </div>
                            <a href="https://developers.facebook.com/tools/debug/accesstoken/?access_token=${encodeURIComponent(badge.closest('.glass-card').querySelector('.account-current-token')?.value || '')}" 
                                target="_blank"
                                class="flex items-center gap-1 bg-white/5 hover:bg-white/10 px-2 py-0.5 rounded border border-white/5 text-[9px] text-gray-400 transition-all group/link"
                                onclick="this.href='https://developers.facebook.com/tools/debug/accesstoken/?access_token=' + encodeURIComponent(this.closest('.glass-card').querySelector('.account-current-token')?.value || '');"
                                title="Open in Official Facebook Token Debugger">
                                <svg class="w-2.5 h-2.5 group-hover/link:text-[#1877F2]" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                <span><?php echo __('token_debugger'); ?></span>
                            </a>
                        </div>`;
                }
                else if (data.status === 'expired') {
                    badge.innerHTML = `
                        <span class="text-[10px] text-red-400 font-bold bg-red-500/10 px-2 py-0.5 rounded border border-red-500/20 uppercase flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?php echo __('token_expired'); ?>
                        </span>`;
                }
            } catch (e) {
                console.error('Check failed for account ' + accId, e);
            }
        }
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<!-- Token Update Modal -->
<?php if (isset($token_error_account_id)): ?>
    <div id="token-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-in fade-in duration-300">
        <div
            class="glass-card w-full max-w-lg rounded-3xl p-8 border border-red-500/30 relative overflow-hidden shadow-2xl shadow-red-500/20">
            <!-- Background Glow -->
            <div
                class="absolute top-0 right-0 w-64 h-64 bg-red-500/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none">
            </div>

            <div class="relative z-10">
                <div class="w-16 h-16 bg-red-500/10 rounded-2xl flex items-center justify-center mb-6 text-red-500 mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>

                <h3 class="text-2xl font-bold text-white text-center mb-2"><?php echo __('token_expired_title'); ?></h3>
                <p class="text-gray-400 text-center mb-8 text-sm leading-relaxed">
                    <?php echo __('token_expired_msg'); ?>
                </p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_account_token" value="1">
                    <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($token_error_account_id); ?>">

                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2"><?php echo __('new_token_placeholder'); ?></label>
                        <textarea name="new_access_token" rows="3"
                            class="w-full bg-black/40 border border-red-500/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-500/50 transition-all font-mono text-xs"
                            required></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="fb_accounts.php"
                            class="flex-1 px-4 py-3 bg-gray-700/50 hover:bg-gray-700 text-white rounded-xl font-bold text-center transition-all text-sm flex items-center justify-center">
                            <?php echo __('cancel'); ?>
                        </a>
                        <button type="submit"
                            class="flex-2 w-full bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-red-600/20 transition-all transform active:scale-95 text-sm">
                            <?php echo __('update_token_btn'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>