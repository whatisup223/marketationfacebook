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

// Handle Sync locally
if (isset($_GET['sync'])) {
    $acc_id = $_GET['sync'];
    // Fetch account details
    $stmt = $pdo->prepare("SELECT * FROM fb_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$acc_id, $user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        require_once __DIR__ . '/../includes/facebook_api.php';
        $fb = new FacebookAPI();
        $response = $fb->getAccounts($account['access_token']);

        if (isset($response['data'])) {
            $ig_count = 0;
            $insertPage = $pdo->prepare("INSERT INTO fb_pages (account_id, page_name, page_id, page_access_token, category, picture_url, ig_business_id, ig_username, ig_profile_picture) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE 
                                            account_id = VALUES(account_id),
                                            page_name = VALUES(page_name), 
                                            page_access_token = VALUES(page_access_token), 
                                            category = VALUES(category),
                                            picture_url = VALUES(picture_url),
                                            ig_business_id = VALUES(ig_business_id),
                                            ig_username = VALUES(ig_username),
                                            ig_profile_picture = VALUES(ig_profile_picture)");

            foreach ($response['data'] as $page) {
                $ig_info = $page['instagram_business_account'] ?? null;
                $ig_id = $ig_info['id'] ?? null;

                if ($ig_id)
                    $ig_count++;

                $insertPage->execute([
                    $acc_id,
                    $page['name'] ?? 'Unknown Page',
                    trim($page['id']),
                    $page['access_token'],
                    $page['category'] ?? 'General',
                    $page['picture']['data']['url'] ?? '',
                    $ig_id,
                    $ig_info['username'] ?? null,
                    $ig_info['profile_picture_url'] ?? null
                ]);
            }
            $message = sprintf(__('msg_ig_sync_success'), $ig_count);
        } else {
            $error = __('fb_api_error');
        }
    }
}

// Fetch only pages that HAVE an Instagram Business Account
$stmt = $pdo->prepare("
    SELECT p.*, a.fb_name as account_owner 
    FROM fb_pages p 
    JOIN fb_accounts a ON p.account_id = a.id 
    WHERE a.user_id = ? AND p.ig_business_id IS NOT NULL 
    ORDER BY p.ig_username ASC
");
$stmt->execute([$user_id]);
$ig_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all linked FB accounts to allow syncing from them
$stmt_fb = $pdo->prepare("SELECT id, fb_name, fb_id FROM fb_accounts WHERE user_id = ?");
$stmt_fb->execute([$user_id]);
$fb_accounts = $stmt_fb->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div id="main-user-container" class="main-user-container flex min-h-screen pt-4"
    style="font-family: <?php echo $font; ?>;">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">
                    <?php echo __('ig_accounts'); ?>
                </h1>
                <p class="text-gray-500 text-sm mt-1">
                    <?php echo __('ig_accounts_desc'); ?>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <?php if (!empty($fb_accounts)): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-2 px-6 py-3 bg-white/5 hover:bg-white/10 text-white rounded-2xl transition-all border border-white/5 font-bold text-sm">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                </path>
                            </svg>
                            <?php echo __('sync_accounts'); ?>
                        </button>
                        <div x-show="open" @click.away="open = false"
                            class="absolute right-0 mt-2 w-64 bg-slate-900 border border-white/10 rounded-2xl shadow-2xl z-50 overflow-hidden">
                            <div class="p-4 border-b border-white/5 bg-white/5">
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                                    <?php echo __('select_account_to_sync'); ?>
                                </h4>
                            </div>
                            <?php foreach ($fb_accounts as $fb_acc): ?>
                                <a href="?sync=<?php echo $fb_acc['id']; ?>"
                                    class="flex items-center gap-3 p-4 hover:bg-white/5 transition-colors border-b border-white/5 last:border-0">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs">
                                        <?php echo substr($fb_acc['fb_name'], 0, 1); ?>
                                    </div>
                                    <span class="text-sm text-gray-300 font-medium truncate">
                                        <?php echo htmlspecialchars($fb_acc['fb_name']); ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="settings.php"
                    class="flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl transition-all shadow-lg shadow-indigo-600/20 font-bold text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <?php echo __('link_new_account'); ?>
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-500/10 border border-green-500/20 p-4 rounded-2xl flex items-center gap-3 mb-8 animate-in fade-in slide-in-from-top-4">
                <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center text-green-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <p class="text-green-400 font-medium">
                    <?php echo $message; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-2xl flex items-center gap-3 mb-8 animate-in fade-in slide-in-from-top-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center text-red-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p class="text-red-400 font-medium">
                    <?php echo $error; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (empty($ig_accounts)): ?>
            <div class="glass-card p-12 text-center rounded-3xl border border-white/5">
                <div class="w-20 h-20 bg-indigo-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-indigo-400" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">
                    <?php echo __('no_ig_accounts_found'); ?>
                </h3>
                <p class="text-gray-500 max-w-sm mx-auto mb-8">
                    <?php echo __('no_ig_accounts_desc'); ?>
                </p>
                <?php if (!empty($fb_accounts)): ?>
                    <p class="text-sm text-indigo-400 font-bold mb-4"><?php echo __('sync_from_linked_fb'); ?></p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <?php foreach ($fb_accounts as $fb_acc): ?>
                            <a href="?sync=<?php echo $fb_acc['id']; ?>"
                                class="px-4 py-2 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-400 rounded-xl border border-indigo-500/20 transition-all text-sm font-bold">
                                <?php echo htmlspecialchars($fb_acc['fb_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="settings.php"
                        class="inline-flex items-center gap-2 text-indigo-400 font-bold hover:text-indigo-300 transition-colors">
                        <?php echo __('link_new_account'); ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3">
                            </path>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($ig_accounts as $account): ?>
                    <div
                        class="glass-card group p-6 rounded-3xl border border-white/5 hover:border-indigo-500/30 transition-all hover:bg-white/[0.02]">
                        <div class="flex items-start justify-between mb-6">
                            <div class="relative">
                                <div
                                    class="w-16 h-16 rounded-2xl overflow-hidden border-2 border-indigo-500/20 group-hover:border-indigo-500/50 transition-all">
                                    <img src="<?php echo $account['ig_profile_picture'] ?: '../assets/img/default-avatar.png'; ?>"
                                        class="w-full h-full object-cover" alt="Profile">
                                </div>
                                <div
                                    class="absolute -bottom-2 -right-2 w-8 h-8 rounded-lg bg-gradient-to-tr from-pink-500 to-orange-500 flex items-center justify-center border-2 border-gray-900">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <span
                                    class="px-3 py-1 bg-indigo-500/10 text-indigo-400 text-[10px] font-bold rounded-full border border-indigo-500/20 uppercase tracking-wider">
                                    <?php echo __('ig_business_account'); ?>
                                </span>
                                <span class="text-[10px] text-gray-500 mt-2">ID:
                                    <?php echo $account['ig_business_id']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors">@
                                <?php echo htmlspecialchars($account['ig_username']); ?>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo htmlspecialchars($account['page_name']); ?>
                            </p>
                        </div>

                        <div class="pt-6 border-t border-white/5 flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                                    <?php echo __('account_owner'); ?>
                                </span>
                                <span class="text-xs text-white/70 font-medium">
                                    <?php echo htmlspecialchars($account['account_owner']); ?>
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <a href="ig_auto_reply.php?ig_id=<?php echo $account['ig_business_id']; ?>"
                                    class="p-2.5 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-xl transition-all border border-white/5"
                                    title="<?php echo __('auto_reply'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                        </path>
                                    </svg>
                                </a>
                                <a href="ig_moderator.php?ig_id=<?php echo $account['ig_business_id']; ?>"
                                    class="p-2.5 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-xl transition-all border border-white/5"
                                    title="<?php echo __('auto_moderator'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                        </path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>