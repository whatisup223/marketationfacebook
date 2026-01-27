<?php
ob_start();
// Prevent script timeout for long scanning operations
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$page_id = $_GET['page_id'] ?? 0;
$showSelector = false;
$page = null;

if ($page_id) {
    // Verify Page Ownership
    $stmt = $pdo->prepare("SELECT p.*, a.access_token as user_token, a.is_active FROM fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           WHERE p.id = ? AND a.user_id = ?");
    $stmt->execute([$page_id, $user_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$page) {
    $showSelector = true;
    // Fetch all available pages for selection
    $stmt = $pdo->prepare("SELECT p.* FROM fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           WHERE a.user_id = ? ORDER BY p.page_name ASC");
    $stmt->execute([$user_id]);
    $user_pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$last_scan = null;
if ($page) {
    $stmt = $pdo->prepare("SELECT MAX(last_interaction) FROM fb_leads WHERE page_id = ?");
    $stmt->execute([$page['id']]);
    $last_scan = $stmt->fetchColumn();
}

// --- AJAX API HANDLER ---
if (isset($_POST['ajax_scan'])) {
    // Increase limits for heavy scanning
    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', 300); // 5 minutes

    header('Content-Type: application/json');

    try {
        $fb = new FacebookAPI();

        // Validation
        if (empty($page['page_access_token'])) {
            throw new Exception("Page Access Token is missing. Please relink the account.");
        }

        $page_access_token = $page['page_access_token'];
        $limit = intval($_POST['limit'] ?? 50);
        $after = $_POST['after_cursor'] ?? null;

        // Log start for debug
        // error_log("Scanning inbox for page {$page['page_id']} - Limit: $limit - After: $after");

        // Fetch one batch
        $conversations = $fb->getConversations($page['page_id'], $page_access_token, $limit, $after);

        if (isset($conversations['error'])) {
            $errMsg = is_string($conversations['error']) ? $conversations['error'] : json_encode($conversations['error']);
            // Log the specific Facebook API error
            error_log("FB API Error: " . $errMsg);
            echo json_encode(['status' => 'error', 'message' => "Facebook API Error: " . $errMsg]);
            exit;
        }

        $count = 0;
        $html_rows = '';
        $insertLead = $pdo->prepare("INSERT INTO fb_leads (page_id, fb_user_id, fb_user_name, last_interaction) 
                                     VALUES (?, ?, ?, NOW()) 
                                     ON DUPLICATE KEY UPDATE last_interaction = NOW(), fb_user_name = VALUES(fb_user_name)");

        if (isset($conversations['data'])) {
            foreach ($conversations['data'] as $convo) {
                $lead_data = null;
                $participants = $convo['participants']['data'] ?? [];

                // Find the user (not the page)
                foreach ($participants as $part) {
                    if ($part['id'] != $page['page_id']) {
                        $lead_data = $part;
                        break;
                    }
                }

                if ($lead_data) {
                    try {
                        $insertLead->execute([$page['id'], $lead_data['id'], $lead_data['name']]);
                        $count++;

                        // Generate HTML row for live append
                        $name = htmlspecialchars($lead_data['name']);
                        $interaction_date = date('M d, H:i');
                        $user_role = __('fb_user_role');

                        $html_rows .= "
                        <tr class='hover:bg-indigo-600/5 transition-all duration-200 group cursor-pointer animate-fade-in-up' onclick='toggleRow(this)'>
                            <td class='px-6 py-4 text-center'>
                                <div class='flex items-center justify-center'>
                                    <input type='checkbox' name='leads[]' value='{$lead_data['id']}' class='lead-checkbox w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-800 transition-all cursor-pointer' onclick='event.stopPropagation()'>
                                </div>
                            </td>
                            <td class='px-6 py-4 text-start'>
                                <div class='flex items-center gap-4'>
                                    <div class='w-10 h-10 rounded-full bg-[#1877F2] flex items-center justify-center text-white shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform ring-2 ring-white/10 shrink-0'>
                                         <svg class='w-6 h-6' fill='currentColor' viewBox='0 0 24 24'><path d='M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z'/></svg>
                                    </div>
                                    <div>
                                        <div class='font-bold text-white group-hover:text-indigo-400 transition-colors text-sm'>$name</div>
                                        <div class='text-[10px] text-gray-500'>$user_role</div>
                                    </div>
                                </div>
                            </td>
                            <td class='px-6 py-4 text-start'>
                                 <span class='font-mono text-[11px] text-gray-400 bg-black/30 px-2 py-1 rounded border border-white/5 select-all'>{$lead_data['id']}</span>
                            </td>
                            <td class='px-6 py-4 text-start'>
                                 <span class='text-xs text-gray-400 flex items-center gap-1.5'><div class='w-1.5 h-1.5 rounded-full bg-green-500'></div>$interaction_date</span>
                            </td>
                        </tr>";
                    } catch (Exception $e) {
                        // Continue if one lead fails
                        error_log("Failed to insert lead: " . $e->getMessage());
                    }
                }
            }
        }

        $next_cursor = $conversations['paging']['cursors']['after'] ?? null;

        echo json_encode([
            'status' => 'ok',
            'count' => $count,
            'next_cursor' => $next_cursor,
            'html' => $html_rows
        ]);

    } catch (Exception $e) {
        $realError = $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
        error_log("Scan Inbox Critical Error: " . $realError);
        echo json_encode(['status' => 'error', 'message' => "System Error: " . $e->getMessage()]);
    }
    exit;
}

// Handle Clear Leads
if (isset($_POST['clear_leads'])) {
    $del = $pdo->prepare("DELETE FROM fb_leads WHERE page_id = ?");
    $del->execute([$page['id']]);
    header("Location: page_inbox.php?page_id={$page['id']}&msg=cleared");
    exit;
}

// Handle Update Token (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'update_token_page') {
    header('Content-Type: application/json');
    $p_id = $_POST['page_id'];
    $new_token = trim($_POST['new_token']);

    if (!$p_id || !$new_token) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data']);
        exit;
    }

    // Verify User Owns Page & Get Account Info
    $stmt = $pdo->prepare("SELECT p.id as db_page_id, p.page_id as fb_page_id, p.account_id, a.fb_id as account_fb_id FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE p.id = ? AND a.user_id = ?");
    $stmt->execute([$p_id, $user_id]);
    $page_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page_info) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $fb = new FacebookAPI();
    $page_db_id = $page_info['db_page_id'];
    $target_fb_id = $page_info['fb_page_id'];
    $account_id = $page_info['account_id'];
    $account_fb_id = $page_info['account_fb_id'];

    // 1. Identify Token Type
    $meta = $fb->getObjectMetadata('me', $new_token);

    if (isset($meta['id'])) {
        $token_fbid = $meta['id'];

        // CASE A: It is the Page Token itself
        if ($token_fbid == $target_fb_id) {
            $update = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
            $update->execute([$new_token, $page_db_id]);

            // Allow Reset Account Active Status
            $pdo->prepare("UPDATE fb_accounts SET is_active = 1 WHERE id = ?")->execute([$account_id]);

            echo json_encode(['status' => 'success']);
            exit;
        }

        // CASE B: It is a User Token (Main Account Token)
        $accounts = $fb->getAccounts($new_token);
        $found_token = null;
        $pages_to_update = [];

        if (isset($accounts['data'])) {
            // Check if target page is in the list
            foreach ($accounts['data'] as $acc) {
                if ($acc['id'] == $target_fb_id) {
                    $found_token = $acc['access_token'];
                }
                // Prepare list of all pages this token controls
                $pages_to_update[$acc['id']] = $acc['access_token'];
            }
        }

        if ($found_token) {
            // User Token is VALID and controls the target page. 
            // ACTION: Update Main Account + Sync ALL Pages.

            // 1. Update Main Account
            $upd_acc = $pdo->prepare("UPDATE fb_accounts SET access_token = ?, fb_id = ?, fb_name = ?, is_active = 1 WHERE id = ?");
            $upd_acc->execute([$new_token, $token_fbid, $meta['name'], $account_id]);

            // 2. Update Target Page (Priority)
            $upd_page = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
            $upd_page->execute([$found_token, $page_db_id]);

            // 3. Update ALL other pages linked to this account
            $db_pages_stmt = $pdo->prepare("SELECT id, page_id FROM fb_pages WHERE account_id = ?");
            $db_pages_stmt->execute([$account_id]);
            $db_pages = $db_pages_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($db_pages as $dbp) {
                $pid = $dbp['page_id'];
                if (isset($pages_to_update[$pid])) {
                    $t = $pages_to_update[$pid];
                    $u = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
                    $u->execute([$t, $dbp['id']]);
                }
            }

            echo json_encode(['status' => 'success']);
            exit;

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Token valid but does not have access to this page.']);
            exit;
        }

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Token: ' . ($meta['error']['message'] ?? 'Unknown Error')]);
        exit;
    }
}

// Handle GET Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cleared') {
        $message = __('leads_cleared');
    }
}

$leads_count = 0;
if ($page) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fb_leads WHERE page_id = ?");
    $stmt->execute([$page['id']]);
    $leads_count = $stmt->fetchColumn();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <!-- Back to Accounts -->
        <div class="mb-6">
            <a href="fb_accounts.php"
                class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors group">
                <div
                    class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center border border-white/5 group-hover:border-indigo-500/30 group-hover:bg-indigo-500/10 transition-all">
                    <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                        </path>
                    </svg>
                </div>
                <span class="text-sm font-bold"><?php echo __('fb_accounts'); ?></span>
            </a>
        </div>

        <!-- Breadcrumb & UI Logic -->
        <?php if (!$showSelector): ?>
            <div class="flex items-center text-sm text-gray-400 mb-6">
                <a href="page_inbox.php" class="hover:text-white transition-colors">
                    <?php echo __('manage_messages'); ?>
                </a>
                <span class="mx-2 text-gray-600">/</span>
                <span class="text-white font-bold tracking-wide"><?php echo htmlspecialchars($page['page_name']); ?></span>
            </div>
        <?php else: ?>
            <div class="flex items-center text-sm text-gray-400 mb-6">
                <span class="text-white font-bold tracking-wide"><?php echo __('manage_messages'); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($showSelector): ?>
            <!-- PAGE SELECTOR UI -->
            <div class="glass-card p-8 rounded-3xl mb-8 relative overflow-hidden border border-white/5 shadow-2xl">
                <div class="relative z-10 text-center md:text-start">
                    <h1 class="text-3xl font-bold text-white tracking-tight mb-2">
                        <?php echo __('synced_pages_title'); ?>
                    </h1>
                    <p class="text-gray-400 text-sm mb-8">
                        <?php echo __('synced_pages_subtitle'); ?>
                    </p>

                    <?php if (count($user_pages) > 0): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <?php foreach ($user_pages as $p): ?>
                                <a href="page_inbox.php?page_id=<?php echo $p['id']; ?>"
                                    class="glass-card bg-white/5 border border-white/5 p-4 rounded-2xl hover:bg-indigo-600/10 hover:border-indigo-500/30 transition-all group flex flex-col items-center text-center">
                                    <div class="relative mb-4">
                                        <?php if ($p['picture_url']): ?>
                                            <img src="<?php echo htmlspecialchars($p['picture_url']); ?>"
                                                class="w-16 h-16 rounded-xl border border-white/10 shadow-lg group-hover:scale-110 transition-transform">
                                        <?php else: ?>
                                            <div
                                                class="w-16 h-16 rounded-xl bg-gray-800 flex items-center justify-center text-gray-500 border border-white/5 group-hover:scale-110 transition-transform">
                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3
                                        class="text-white font-bold text-sm mb-1 group-hover:text-indigo-400 transition-colors line-clamp-1">
                                        <?php echo htmlspecialchars($p['page_name']); ?>
                                    </h3>
                                    <span class="text-[10px] text-gray-500 font-mono mb-4">ID: <?php echo $p['page_id']; ?></span>
                                    <div
                                        class="w-full py-2 bg-white/5 border border-white/10 rounded-lg text-xs font-bold text-gray-300 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                        <?php echo __('open_inbox'); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div
                                class="w-20 h-20 rounded-full bg-gray-800/50 flex items-center justify-center mb-6 border border-white/5">
                                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo __('no_pages_synced'); ?></h3>
                            <p class="text-gray-400 text-sm max-w-sm mx-auto mb-8"><?php echo __('no_pages_synced_desc'); ?></p>
                            <a href="fb_accounts.php"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-indigo-600/20 transition-all active:scale-95 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <?php echo __('fb_accounts'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>

            <!-- Header Card: Info & Controls -->
            <div
                class="glass-card p-6 md:p-8 rounded-3xl mb-8 relative overflow-hidden border border-white/5 group shadow-2xl">
                <!-- Background Glow -->
                <div
                    class="absolute top-0 right-0 w-96 h-96 bg-indigo-600/10 blur-[120px] rounded-full -mr-20 -mt-20 pointer-events-none transition-all duration-1000 group-hover:bg-indigo-600/15">
                </div>

                <div class="relative z-10">
                    <!-- Row 1: Page Identity -->
                    <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
                        <div class="relative group-avatar shrink-0">
                            <?php if ($page['picture_url']): ?>
                                <img src="<?php echo htmlspecialchars($page['picture_url']); ?>"
                                    class="w-20 h-20 rounded-2xl border-2 border-white/10 shadow-2xl transition-transform group-hover:scale-105">
                            <?php endif; ?>
                            <div
                                class="absolute -bottom-2 -right-2 bg-green-500 w-6 h-6 rounded-full border-4 border-[#0f172a] flex items-center justify-center shadow-lg">
                                <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        <div class="flex-1 text-center md:text-start">
                            <h1 class="text-3xl font-bold text-white tracking-tight mb-2">
                                <?php echo htmlspecialchars($page['page_name']); ?>

                                <!-- Connection Status -->
                                <?php if ($page['is_active']): ?>
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/20 text-xs font-bold ml-3 align-middle">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
                                        <?php echo __('connected'); ?>
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/10 text-red-500 border border-red-500/20 text-xs font-bold ml-3 align-middle">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                            </path>
                                        </svg>
                                        <?php echo __('token_expired'); ?>
                                    </span>
                                <?php endif; ?>
                            </h1>
                            <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 text-sm">
                                <a href="https://www.facebook.com/<?php echo $page['page_id']; ?>" target="_blank"
                                    class="px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center gap-2 hover:bg-indigo-500/20 transition-all">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                        </path>
                                    </svg>
                                    <span>ID: <?php echo $page['page_id']; ?></span>
                                    <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                        </path>
                                    </svg>
                                </a>
                                <span
                                    class="px-3 py-1 rounded-full bg-gray-800/50 text-gray-400 border border-white/5 text-xs flex items-center gap-2">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo __('last_updated'); ?>
                                    <?php echo $last_scan ? date('M d, H:i', strtotime($last_scan)) : __('never_updated'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="h-px bg-white/5 mb-6"></div>

                    <!-- Row 2: Control Hub (Ultra-Responsive) -->
                    <div
                        class="glass-card bg-[#0f172a]/60 border border-white/10 rounded-2xl p-3 md:p-2 shadow-xl relative mb-4 w-full">
                        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-4 md:gap-6">

                            <!-- 1. Extraction Mode -->
                            <div
                                class="bg-black/40 rounded-xl p-1 flex items-center shrink-0 border border-white/5 w-full lg:w-auto">
                                <button type="button" onclick="setMode('limit')" id="btn-mode-limit"
                                    class="flex-1 lg:flex-none px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 bg-indigo-600 text-white shadow-lg">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z">
                                        </path>
                                    </svg>
                                    <span class="whitespace-nowrap"><?php echo __('extract_limit_option'); ?></span>
                                </button>
                                <button type="button" onclick="setMode('all')" id="btn-mode-all"
                                    class="flex-1 lg:flex-none px-4 py-2 rounded-lg text-xs font-bold text-gray-400 hover:text-white transition-all flex items-center justify-center gap-2">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11v-2a2 2 0 00-2-2H7a2 2 0 00-2 2v2m14 0v2a2 2 0 01-2 2h-1m-4 0h-4m9 0H7a2 2 0 01-2-2v-2m14 0V9a2 2 0 00-2-2M5 11V9a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 9v1">
                                        </path>
                                    </svg>
                                    <span class="whitespace-nowrap"><?php echo __('extract_all_option'); ?></span>
                                </button>
                            </div>

                            <!-- 2. Inputs Group -->
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-3 flex-1 lg:flex-initial">
                                <!-- Batch Size -->
                                <div class="relative min-w-0">
                                    <label
                                        class="absolute -top-3 left-2 text-[10px] font-bold text-indigo-400 uppercase bg-[#0f172a] px-1 rounded shadow-sm border border-white/5"><?php echo __('lbl_batch_size'); ?></label>
                                    <input type="number" id="scan_limit" value="50" min="1" max="500"
                                        class="w-full bg-black/20 border border-white/10 rounded-xl px-2 py-2.5 text-white text-sm font-mono focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all text-center">
                                </div>

                                <!-- Total Goal -->
                                <div class="relative min-w-0 transition-all duration-300" id="goal-wrapper">
                                    <label
                                        class="absolute -top-3 left-2 text-[10px] font-bold text-purple-400 uppercase bg-[#0f172a] px-1 rounded shadow-sm border border-white/5"><?php echo __('lbl_total_goal'); ?></label>
                                    <input type="number" id="scan_goal" value="100" min="1"
                                        class="w-full bg-black/20 border border-white/10 rounded-xl px-2 py-2.5 text-white text-sm font-mono focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition-all text-center">
                                </div>

                                <!-- Delay -->
                                <div class="relative min-w-0">
                                    <label
                                        class="absolute -top-3 left-2 text-[10px] font-bold text-green-400 uppercase bg-[#0f172a] px-1 rounded shadow-sm border border-white/5"><?php echo __('lbl_delay'); ?></label>
                                    <input type="number" id="scan_delay" value="1" min="0"
                                        class="w-full bg-black/20 border border-white/10 rounded-xl px-2 py-2.5 text-white text-sm font-mono focus:border-green-500 focus:ring-1 focus:ring-green-500 outline-none transition-all text-center">
                                </div>
                            </div>

                            <!-- 3. Actions -->
                            <div class="flex items-center gap-2 w-full lg:w-auto lg:ml-auto">
                                <button onclick="stopScan()" id="btn-stop" disabled
                                    class="w-10 h-10 rounded-xl bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white disabled:opacity-30 disabled:hover:bg-red-500/10 transition-all flex items-center justify-center shadow-lg border border-red-500/20 shrink-0">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 6h12v12H6z" />
                                    </svg>
                                </button>
                                <div class="relative flex items-center h-10 flex-1 lg:flex-none">
                                    <button onclick="pauseScan()" id="btn-pause"
                                        class="hidden h-10 px-4 rounded-xl bg-yellow-500/10 text-yellow-500 hover:bg-yellow-500 hover:text-white transition-all flex items-center justify-center gap-2 shadow-lg border border-yellow-500/20 w-full">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                                        </svg>
                                        <span
                                            class="font-bold text-xs md:text-sm uppercase whitespace-nowrap"><?php echo __('btn_pause'); ?></span>
                                    </button>
                                    <button onclick="startScan()" id="btn-play"
                                        class="h-10 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2 transition-all transform active:scale-95 group border border-white/10 w-full">
                                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                        <span class="font-bold text-xs md:text-sm uppercase whitespace-nowrap"
                                            id="text-play"><?php echo __('btn_start'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Status & Progress Row (Full Width) -->
                    <div class="glass-card bg-black/20 border border-white/5 rounded-2xl p-4 shadow-inner mb-4">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <div class="flex items-center gap-3">
                                <div id="status-dot"
                                    class="w-2.5 h-2.5 rounded-full bg-gray-600 shadow-[0_0_10px_rgba(75,85,99,0.5)]"></div>
                                <span id="status-text"
                                    class="text-sm font-medium text-gray-300 tracking-wide"><?php echo __('status_ready'); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="progress-count"
                                    class="bg-indigo-500/15 text-indigo-400 border border-indigo-500/20 px-3 py-1 rounded-lg text-xs font-bold hidden">0</span>
                            </div>
                        </div>
                        <!-- Deluxe Progress Bar -->
                        <div
                            class="relative w-full h-3 bg-black/40 rounded-full overflow-hidden border border-white/5 p-0.5">
                            <div id="progress-bar"
                                class="h-full bg-gradient-to-r from-indigo-600 via-blue-500 to-green-400 w-0 transition-all duration-500 rounded-full shadow-[0_0_15px_rgba(79,70,229,0.4)] relative">
                                <!-- Shine Effect -->
                                <div class="absolute inset-0 bg-gradient-to-b from-white/20 to-transparent"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Clear Data Action -->
                    <div class="flex justify-end items-center gap-3 mb-6">
                        <!-- Clear Selection Button (Visible only when selection active) -->
                        <button type="button" onclick="clearSelection()" id="btn-clear-selection" style="display:none;"
                            class="h-9 px-4 bg-orange-500/10 hover:bg-orange-500 text-orange-500 hover:text-white border border-orange-500/20 rounded-xl transition-all flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest shadow-lg shadow-orange-500/5 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <?php echo __('clear'); ?>
                        </button>

                        <!-- Clear Database Button -->
                        <form method="POST" onsubmit="return confirm('<?php echo __('clear_confirm'); ?>');">
                            <button type="submit" name="clear_leads"
                                class="h-9 px-4 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white border border-red-500/20 rounded-xl transition-all flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest shadow-lg shadow-red-500/5 active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                                <?php echo __('clear_database'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($message) && $message): ?>
                    <div
                        class="glass-card bg-green-500/5 border-green-500/20 text-green-400 p-4 rounded-2xl mb-6 flex items-center gap-3 animate-fade-in-up shadow-lg shadow-green-900/10">
                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="font-medium"><?php echo $message; ?></div>
                        <button onclick="this.parentElement.remove()"
                            class="ml-auto text-green-400/50 hover:text-green-400 transition-colors"><svg class="w-4 h-4"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                                </path>
                            </svg></button>
                    </div>
                <?php endif; ?>

                <!-- Inbox Table -->
                <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">

                <div
                    class="glass-card rounded-3xl overflow-hidden border border-white/5 flex flex-col shadow-2xl shadow-black/50">
                    <!-- Table Header Toolbad -->
                    <div
                        class="p-6 border-b border-white/5 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 bg-white/5 backdrop-blur-xl">

                        <!-- Title & Global Action Wrapper -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 w-full lg:w-auto">
                            <h2 class="text-xl font-bold flex items-center gap-3 text-white">
                                <span
                                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                        </path>
                                    </svg>
                                </span>
                                <span class="whitespace-nowrap"><?php echo __('inbox_leads'); ?></span>
                            </h2>

                            <!-- Global Select Action -->
                            <button type="button" onclick="selectAllGlobal(<?php echo $page['id']; ?>)"
                                class="inline-flex items-center justify-center gap-1 px-4 py-2 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-bold hover:bg-indigo-500 hover:text-white transition-all w-full sm:w-auto mt-2 sm:mt-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                                    </path>
                                </svg>
                                <?php echo __('select_all_global'); ?>
                            </button>
                        </div>

                        <!-- Search & Actions Wrapper -->
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                            <!-- Search Box -->
                            <div class="relative w-full sm:w-64 group">
                                <input type="text" id="lead-search"
                                    class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-2.5 pl-10 text-xs text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                                    placeholder="<?php echo __('search_placeholder'); ?>">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 group-focus-within:text-indigo-400 transition-colors"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            <form action="create_campaign.php" method="POST" id="campaign-form" class="w-full sm:w-auto">
                                <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
                                <button type="submit"
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-indigo-600/20 transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 h-full min-h-[42px]"
                                    id="create-btn" disabled>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4">
                                        </path>
                                    </svg>
                                    <span class="whitespace-nowrap"><?php echo __('create_campaign'); ?></span>
                                    <span id="selected-count"
                                        class="bg-black/30 text-white text-[10px] px-2 py-0.5 rounded-full font-mono min-w-[20px] text-center">0</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Table Content -->
                    <div class="overflow-y-auto max-h-[600px] custom-scrollbar bg-[#0f172a]/50">
                        <table class="w-full text-start border-collapse" id="leads-table">
                            <thead
                                class="bg-[#1e293b] text-gray-400 text-[10px] uppercase font-bold sticky top-0 z-10 shadow-lg shadow-black/20 tracking-wider">
                                <tr>
                                    <th class="px-6 py-4 w-16 bg-[#1e293b] text-center">
                                        <div class="flex items-center justify-center relative group">
                                            <input type="checkbox" id="select-all"
                                                class="w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-800 transition-all cursor-pointer">
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 bg-[#1e293b] text-start"><?php echo __('user_identity'); ?></th>
                                    <th class="px-6 py-4 bg-[#1e293b] text-start"><?php echo __('psid_label'); ?></th>
                                    <th class="px-6 py-4 bg-[#1e293b] text-start"><?php echo __('interaction_date'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50" id="leads-tbody">
                                <?php
                                // Pagination Logic
                                $leads_per_page = 50;
                                $page_num = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                                $offset = ($page_num - 1) * $leads_per_page;

                                // Get Total Count
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM fb_leads WHERE page_id = ?");
                                $stmt->execute([$page['id']]);
                                $total_leads = $stmt->fetchColumn();
                                $total_pages = ceil($total_leads / $leads_per_page);

                                // Get Paginated Results
                                $stmt = $pdo->prepare("SELECT * FROM fb_leads WHERE page_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
                                // Bind parameters explicitly for LIMIT/OFFSET (PDO needs int)
                                $stmt->bindValue(1, $page['id'], PDO::PARAM_INT);
                                $stmt->bindValue(2, $leads_per_page, PDO::PARAM_INT);
                                $stmt->bindValue(3, $offset, PDO::PARAM_INT);
                                $stmt->execute();
                                $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($leads) > 0):
                                    foreach ($leads as $lead):
                                        ?>
                                        <tr class="hover:bg-indigo-600/5 transition-all duration-200 group cursor-pointer"
                                            onclick="toggleRow(this)">
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex items-center justify-center">
                                                    <input type="checkbox" name="leads[]" value="<?php echo $lead['id']; ?>"
                                                        class="lead-checkbox w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-800 transition-all cursor-pointer"
                                                        onclick="event.stopPropagation()">
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-start">
                                                <div class="flex items-center gap-4">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-[#1877F2] flex items-center justify-center text-white shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform ring-2 ring-white/10 shrink-0">
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                                            <path
                                                                d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <div
                                                            class="font-bold text-white group-hover:text-indigo-400 transition-colors text-sm">
                                                            <?php echo htmlspecialchars($lead['fb_user_name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-gray-500"><?php echo __('fb_user_role'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-start">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="font-mono text-[11px] text-gray-400 bg-black/30 px-2 py-1 rounded border border-white/5 select-all"><?php echo $lead['fb_user_id']; ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-start">
                                                <span class="text-xs text-gray-400 flex items-center gap-1.5">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                                    <?php echo date('M d, H:i', strtotime($lead['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr id="no-leads-row">
                                        <td colspan="4">
                                            <div class="flex flex-col items-center justify-center py-24 text-gray-500">
                                                <!-- Empty state icon -->
                                                <p class="text-lg font-medium text-gray-400"><?php echo __('no_leads_found'); ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION CONTROLS -->
                    <?php if ($total_pages > 1): ?>
                        <div class="p-4 border-t border-white/5 bg-white/5 flex items-center justify-between">
                            <div class="text-xs text-gray-400">
                                <?php echo __('showing_page'); ?> <span
                                    class="text-white font-bold"><?php echo $page_num; ?></span> <?php echo __('of'); ?> <span
                                    class="text-white font-bold"><?php echo $total_pages; ?></span>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($page_num > 1): ?>
                                    <a href="?page_id=<?php echo $page['id']; ?>&page=<?php echo $page_num - 1; ?>"
                                        class="px-3 py-1.5 rounded-lg bg-black/20 border border-white/10 text-xs text-white hover:bg-indigo-600 hover:border-indigo-500 transition-all font-bold"><?php echo __('prev'); ?></a>
                                <?php endif; ?>

                                <?php if ($page_num < $total_pages): ?>
                                    <a href="?page_id=<?php echo $page['id']; ?>&page=<?php echo $page_num + 1; ?>"
                                        class="px-3 py-1.5 rounded-lg bg-black/20 border border-white/10 text-xs text-white hover:bg-indigo-600 hover:border-indigo-500 transition-all font-bold"><?php echo __('next'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; // End check for showSelector ?>
            </div>
        </div>

        <!-- Token Update Modal -->
        <div id="token-modal"
            class="fixed inset-0 z-[100] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-gray-900 border border-white/10 w-full max-w-md rounded-3xl p-8 shadow-2xl animate-in fade-in zoom-in duration-300">
                <div
                    class="w-20 h-20 rounded-full bg-red-500/20 flex items-center justify-center text-red-500 mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white text-center mb-3 text-red-500">
                    <?php echo __('token_expired_title'); ?>
                </h3>
                <p class="text-gray-400 text-center text-sm mb-8 leading-relaxed">
                    <?php echo __('token_expired_msg'); ?>
                </p>

                <div class="space-y-4">
                    <div class="relative">
                        <input type="text" id="new-token-input" placeholder="<?php echo __('new_token_placeholder'); ?>"
                            class="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all font-mono">
                    </div>

                    <button onclick="updateToken()" id="btn-update-token"
                        class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-500/20 transition-all flex items-center justify-center gap-2 group">
                        <span><?php echo __('update_token_btn'); ?></span>
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path
                                d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 111.414-1.414z" />
                        </svg>
                    </button>
                    <button onclick="document.getElementById('token-modal').classList.add('hidden')"
                        class="w-full text-gray-500 text-sm font-medium hover:text-white transition-colors">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php if (!$showSelector): ?>
            <script>
                // --- STATE MANAGEMENT ---
                let state = {
                    isRunning: false,
                    isPaused: false,
                    scanMode: 'limit', // 'limit' or 'all'
                    limit: 50,         // Batch size
                    totalGoal: 100,    // Only for limit mode
                    delay: 1,
                    processed: 0,
                    nextCursor: null
                };

                // --- UI HELPERS ---
                const ui = {
                    btnLimit: document.getElementById('btn-mode-limit'),
                    btnAll: document.getElementById('btn-mode-all'),
                    inputLimit: document.getElementById('scan_limit'),
                    inputGoal: document.getElementById('scan_goal'),
                    goalWrapper: document.getElementById('goal-wrapper'),
                    inputDelay: document.getElementById('scan_delay'),
                    btnPlay: document.getElementById('btn-play'),
                    btnPause: document.getElementById('btn-pause'),
                    btnStop: document.getElementById('btn-stop'),
                    textPlay: document.getElementById('text-play'),

                    statusText: document.getElementById('status-text'),
                    statusDot: document.getElementById('status-dot'),
                    progressCount: document.getElementById('progress-count'),
                    progressBar: document.getElementById('progress-bar'),

                    tbody: document.getElementById('leads-tbody'),
                    noLeadsRow: document.getElementById('no-leads-row')
                };

                function setMode(mode) {
                    if (state.isRunning) return;

                    state.scanMode = mode;
                    if (mode === 'limit') {
                        ui.btnLimit.classList.replace('text-gray-400', 'text-white');
                        ui.btnLimit.classList.add('bg-indigo-600', 'shadow-lg');
                        ui.btnAll.classList.remove('bg-indigo-600', 'shadow-lg', 'text-white');
                        ui.btnAll.classList.add('text-gray-400');

                        ui.goalWrapper.classList.remove('hidden');
                    } else {
                        ui.btnAll.classList.replace('text-gray-400', 'text-white');
                        ui.btnAll.classList.add('bg-indigo-600', 'shadow-lg');
                        ui.btnLimit.classList.remove('bg-indigo-600', 'shadow-lg', 'text-white');
                        ui.btnLimit.classList.add('text-gray-400');

                        ui.goalWrapper.classList.add('hidden');
                    }
                }

                // --- SCAN LOGIC ---
                async function startScan() {
                    if (state.isRunning && !state.isPaused) return;

                    if (state.isPaused) {
                        resumeScan();
                        return;
                    }

                    // INIT
                    state.isRunning = true;
                    state.isPaused = false;
                    state.processed = 0;
                    state.nextCursor = null;
                    state.limit = parseInt(ui.inputLimit.value) || 50;
                    state.totalGoal = parseInt(ui.inputGoal.value) || 100;
                    state.delay = parseInt(ui.inputDelay.value) || 0;

                    updateUI('running');
                    scanLoop();
                }

                function pauseScan() {
                    state.isPaused = true;
                    updateUI('paused');
                }

                function resumeScan() {
                    state.isPaused = false;
                    updateUI('running');
                    scanLoop();
                }

                function stopScan() {
                    state.isRunning = false;
                    state.isPaused = false;
                    updateUI('stopped');
                }

                async function scanLoop() {
                    while (state.isRunning && !state.isPaused) {

                        // Check if we reached the goal in limit mode
                        if (state.scanMode === 'limit' && state.processed >= state.totalGoal) {
                            finishScan();
                            break;
                        }

                        // Determine Batch Size
                        let currentBatch = state.limit;
                        if (state.scanMode === 'limit') {
                            currentBatch = Math.min(state.limit, state.totalGoal - state.processed);
                        }

                        // Status: Scanning (Batch Size)
                        ui.statusText.innerText = `<?php echo __('status_scanning'); ?>`.replace('%s', currentBatch);
                        ui.progressBar.classList.add('animate-pulse');

                        try {
                            const formData = new FormData();
                            formData.append('ajax_scan', '1');
                            formData.append('limit', currentBatch);
                            if (state.nextCursor) formData.append('after_cursor', state.nextCursor);

                            const response = await fetch('page_inbox.php?page_id=<?php echo $page['id']; ?>', {
                                method: 'POST',
                                body: formData
                            });

                            const data = await response.json();

                            if (data.status === 'error') {
                                // Check for Token Errors
                                let msg = JSON.stringify(data.message).toLowerCase();
                                if (msg.includes('session has expired') || msg.includes('oauth') || msg.includes('access token') || msg.includes('code: 190')) {
                                    stopScan();
                                    showTokenModal();
                                    return; // Stop loop
                                }

                                alert('Scan Error: ' + data.message);
                                stopScan();
                                break;
                            }

                            if (data.html) {
                                if (ui.noLeadsRow) ui.noLeadsRow.remove();
                                ui.tbody.insertAdjacentHTML('afterbegin', data.html); // Add new leads to top

                                // PERFORMANCE: Keep DOM light by removing excess rows
                                // This simulates "real-time pagination" where only recent items are visible
                                const maxRows = 50;
                                const renderedRows = ui.tbody.children;
                                while (renderedRows.length > maxRows) {
                                    renderedRows[renderedRows.length - 1].remove();
                                }

                                // Apply search filter to new rows
                                if (typeof filterLeads === 'function') filterLeads();
                            }

                            state.processed += data.count;
                            ui.progressCount.innerText = state.processed;
                            ui.progressCount.classList.remove('hidden');

                            // Status: Processed X leads (Translated)
                            ui.statusText.innerText = `<?php echo __('status_processed'); ?>`.replace('%s', state.processed);

                            // Progress Bar Update
                            if (state.scanMode === 'limit') {
                                let pct = Math.min(100, (state.processed / state.totalGoal) * 100);
                                ui.progressBar.style.width = pct + '%';
                            } else {
                                ui.progressBar.style.width = '100%';
                            }

                            if (data.next_cursor && (state.scanMode === 'all' || state.processed < state.totalGoal)) {
                                state.nextCursor = data.next_cursor;
                            } else {
                                finishScan();
                                break;
                            }

                            // Delay / Anti-Ban
                            if (state.delay > 0 && state.isRunning && !state.isPaused) {
                                ui.statusText.innerText = `<?php echo __('status_sleeping'); ?>`.replace('%s', state.delay);
                                await new Promise(r => setTimeout(r, state.delay * 1000));
                            }

                        } catch (e) {
                            console.error(e);
                            alert('Network Error');
                            stopScan();
                            break;
                        }
                    }
                }

                function finishScan() {
                    state.isRunning = false;
                    state.isPaused = false;
                    updateUI('finished');
                    ui.statusText.innerText = `<?php echo __('status_finished'); ?>`;
                    // Reload to apply pagination logic
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }

                function updateUI(status) {
                    if (status === 'running') {
                        ui.btnPlay.classList.add('hidden');
                        ui.btnPause.classList.remove('hidden');
                        ui.btnStop.disabled = false;
                        ui.btnPause.disabled = false;

                        ui.statusDot.classList.replace('bg-gray-600', 'bg-green-500');
                        ui.statusDot.classList.add('animate-ping');

                        ui.inputLimit.disabled = true;
                        ui.inputGoal.disabled = true;
                        ui.inputDelay.disabled = true;
                    }
                    else if (status === 'paused') {
                        ui.btnPlay.classList.remove('hidden');
                        ui.btnPause.classList.add('hidden');
                        ui.textPlay.innerText = `<?php echo __('btn_resume'); ?>`;

                        ui.statusDot.classList.replace('bg-green-500', 'bg-yellow-500');
                        ui.statusDot.classList.remove('animate-ping');
                        ui.statusText.innerText = `<?php echo __('status_paused'); ?>`;
                    }
                    else if (status === 'stopped' || status === 'finished') {
                        ui.btnPlay.classList.remove('hidden');
                        ui.btnPause.classList.add('hidden');
                        ui.textPlay.innerText = `<?php echo __('btn_start'); ?>`;
                        ui.btnStop.disabled = true;

                        ui.statusDot.className = 'w-2 h-2 rounded-full bg-gray-600';
                        ui.progressBar.classList.remove('animate-pulse');
                        if (status === 'stopped') ui.progressBar.style.width = '0';

                        ui.inputLimit.disabled = false;
                        ui.inputGoal.disabled = false;
                        ui.inputDelay.disabled = false;
                    }
                }

                // --- CHECKBOX LOGIC ---
                // --- PERSISTENT SELECTION LOGIC ---
                // Uses sessionStorage to keep track of selected IDs across pagination
                const PAGE_KEY = 'selected_leads_<?php echo $page['id']; ?>';
                const selectAll = document.getElementById('select-all');

                // Helper to get/set Storage
                function getStoredSelection() {
                    const stored = sessionStorage.getItem(PAGE_KEY);
                    return stored ? JSON.parse(stored) : [];
                }

                function saveSelection(ids) {
                    sessionStorage.setItem(PAGE_KEY, JSON.stringify(ids));
                    updateUIStats(ids.length);
                }

                function updateUIStats(count) {
                    const countSpan = document.getElementById('selected-count');
                    const createBtn = document.getElementById('create-btn');

                    if (countSpan) countSpan.innerText = count;
                    if (createBtn) createBtn.disabled = count === 0;

                    // Check "Select All" checkbox state if all currently visible are selected
                    if (selectAll) {
                        const allVisible = Array.from(document.querySelectorAll('.lead-checkbox'));
                        if (allVisible.length > 0) {
                            const allChecked = allVisible.every(cb => cb.checked);
                            selectAll.checked = allChecked;
                        }
                    }
                }

                // Initialize: Restore Selection
                function initSelection() {
                    const selectedIds = getStoredSelection();

                    // Apply to current checkboxes
                    document.querySelectorAll('.lead-checkbox').forEach(cb => {
                        if (selectedIds.includes(cb.value)) {
                            cb.checked = true;
                        }
                    });

                    updateUIStats(selectedIds.length);
                }

                // Toggle Single Row
                function toggleRow(row) {
                    const checkbox = row.querySelector('.lead-checkbox');
                    if (checkbox) {
                        // Toggle Check
                        checkbox.checked = !checkbox.checked;

                        // Update Storage
                        let selectedIds = getStoredSelection();
                        if (checkbox.checked) {
                            if (!selectedIds.includes(checkbox.value)) selectedIds.push(checkbox.value);
                        } else {
                            selectedIds = selectedIds.filter(id => id !== checkbox.value);
                        }
                        saveSelection(selectedIds);
                    }
                }

                // Handle Direct Checkbox Click (prevent bubbling double toggle)
                document.querySelectorAll('.lead-checkbox').forEach(cb => {
                    cb.addEventListener('click', function (e) {
                        e.stopPropagation(); // Stop row click

                        let selectedIds = getStoredSelection();
                        if (this.checked) {
                            if (!selectedIds.includes(this.value)) selectedIds.push(this.value);
                        } else {
                            selectedIds = selectedIds.filter(id => id !== this.value);
                        }
                        saveSelection(selectedIds);
                    });
                });

                // Select All (Visible Only)
                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        const isChecked = this.checked;
                        let selectedIds = getStoredSelection();
                        const visibleCheckboxes = document.querySelectorAll('.lead-checkbox');

                        visibleCheckboxes.forEach(cb => {
                            cb.checked = isChecked;
                            if (isChecked) {
                                if (!selectedIds.includes(cb.value)) selectedIds.push(cb.value);
                            } else {
                                selectedIds = selectedIds.filter(id => id !== cb.value);
                            }
                        });

                        saveSelection(selectedIds);
                    });
                }

                // Handle Form Submit
                document.getElementById('campaign-form').addEventListener('submit', function (e) {
                    // Remove any existing hidden inputs first
                    this.querySelectorAll('input[type="hidden"][name="leads[]"]').forEach(el => el.remove());

                    // Add ALL selected IDs from storage via SINGLE JSON input
                    // This prevents max_input_vars limit issues with large selections (e.g. 10k leads)
                    const selectedIds = getStoredSelection();

                    // Remove old inputs if exist
                    if (this.querySelector('input[name="ids_json"]')) {
                        this.querySelector('input[name="ids_json"]').remove();
                    }

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids_json';
                    input.value = JSON.stringify(selectedIds);
                    this.appendChild(input);

                    // Optional: Clear selection after submit? 
                    // Usually valid to keep until explicit clear or success
                    // sessionStorage.removeItem(PAGE_KEY); 
                });


                // Run Init
                initSelection();

                // --- GLOBAL SELECT LOGIC ---
                async function selectAllGlobal(pageId) {
                    const btn = document.querySelector('[onclick^="selectAllGlobal"]');
                    const originalText = btn ? btn.innerHTML : '';
                    if (btn) btn.innerHTML = '<?php echo __('please_wait'); ?>';

                    try {
                        const formData = new FormData();
                        formData.append('page_id', pageId);

                        const response = await fetch('ajax_get_all_ids.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.status === 'success') {
                            // Map IDs to strings to match checkbox values
                            const stringIds = data.ids.map(String);
                            saveSelection(stringIds);
                            initSelection();
                            alert('<?php echo __('all_leads_selected_success'); ?>'.replace('%s', data.count));
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Failed to fetch all IDs');
                    } finally {
                        if (btn) btn.innerHTML = originalText || '<?php echo __('select_all_global'); ?>';
                    }
                }

                // --- SERVER-SIDE SEARCH LOGIC ---
                const leadSearch = document.getElementById('lead-search');
                let searchTimeout = null;

                window.filterLeads = function () {
                    if (!leadSearch) return;
                    const query = leadSearch.value.trim();
                    const tbody = document.getElementById('leads-tbody');

                    // Clear prev timer
                    if (searchTimeout) clearTimeout(searchTimeout);

                    // If empty, reload to show default view or handle gracefully
                    if (query.length === 0) {
                        window.location.reload(); // Simple reset
                        return;
                    }

                    // Debounce search input (500ms)
                    searchTimeout = setTimeout(async () => {
                        if (query.length < 2) return; // Min 2 chars

                        // Visual loading indicator
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-white"><span class="animate-pulse">Searching global database...</span></td></tr>';

                        try {
                            const formData = new FormData();
                            formData.append('q', query);
                            formData.append('current_page_id', <?php echo $page ? $page['id'] : 0; ?>);
                            // ^ Note: current_page_id is to ensure we can keep current context if needed, 
                            // but User wants to search globally across pages, or just extensive search on this page?
                            // User said: "Search by user name or ID ... should search in ALL pages existing in leads, not just one page"

                            const res = await fetch('page_inbox_search.php', { method: 'POST', body: formData });
                            const html = await res.text();

                            if (html.trim()) {
                                tbody.innerHTML = html;
                                // Re-initialize checkboxes state
                                initSelection();
                            } else {
                                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-gray-400">No results found in any page.</td></tr>';
                            }

                        } catch (err) {
                            console.error(err);
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-10 text-red-400">Search Error</td></tr>';
                        }

                    }, 500);
                };

                if (leadSearch) {
                    leadSearch.addEventListener('input', filterLeads);
                }

                setMode('limit');

                // Check for cleared message to reset selection
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('msg') === 'cleared') {
                    sessionStorage.removeItem(PAGE_KEY);
                    initSelection(); // This will clear UI
                }

                if (window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('msg');
                    window.history.replaceState(null, '', url);
                }
                // --- TOKEN MODAL LOGIC ---
                function showTokenModal() {
                    document.getElementById('token-modal').classList.remove('hidden');
                }

                async function updateToken() {
                    const newToken = document.getElementById('new-token-input').value.trim();
                    if (!newToken) {
                        alert('<?php echo __('enter_valid_token'); ?>');
                        return;
                    }

                    const btn = document.getElementById('btn-update-token');
                    btn.disabled = true;
                    btn.innerHTML = '<?php echo __('updating_btn'); ?>';

                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_token_page');
                        formData.append('page_id', <?php echo $page['id']; ?>);
                        formData.append('new_token', newToken);

                        const response = await fetch('page_inbox.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.status === 'success') {
                            document.getElementById('token-modal').classList.add('hidden');
                            alert('<?php echo __('token_updated_success'); ?>');
                            // Force a clean reload (GET) to avoid resubmitting old POST data
                            window.location.href = window.location.href;
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        console.error(e);
                        alert('<?php echo __('update_token_error'); ?>');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<?php echo __('update_token_btn'); ?>';
                    }
                }
                // Clear Selection Helper
                function clearSelection() {
                    if (confirm('<?php echo __('clear_selection_confirm'); ?>')) {
                        sessionStorage.removeItem(PAGE_KEY);
                        initSelection();
                        // Untick all visual
                        document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
                        if (selectAll) selectAll.checked = false;

                        // Hide clear button
                        document.getElementById('btn-clear-selection').style.display = 'none';
                    }
                }

                // Show clear button if selection > 0
                const statCheck = setInterval(() => {
                    const cnt = getStoredSelection().length;
                    const btn = document.getElementById('btn-clear-selection');
                    if (btn) btn.style.display = cnt > 0 ? 'inline-block' : 'none';
                }, 1000);

            </script>
        <?php endif; ?>

        <script>
            // Connection Status Auto-Check
            document.addEventListener('DOMContentLoaded', function () {
                const badgeContainer = document.getElementById('connection-status-badge');
                if (badgeContainer) {
                    checkStatus();
                }

                async function checkStatus() {
                    try {
                        const formData = new FormData();
                        formData.append('page_db_id', <?php echo $page['id'] ?? 0; ?>);

                        const response = await fetch('ajax_check_status.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.status === 'active') {
                            badgeContainer.innerHTML = `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/20 text-xs font-bold ml-3 align-middle">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
                            <?php echo __('connected'); ?>
                        </span>`;
                        } else if (data.status === 'expired') {
                            badgeContainer.innerHTML = `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/10 text-red-500 border border-red-500/20 text-xs font-bold ml-3 align-middle">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <?php echo __('token_expired'); ?>
                        </span>`;

                            // Show modal if appropriate (optional, acts as auto-prompt)
                            // if (typeof showTokenModal === 'function') showTokenModal();
                        }
                    } catch (e) {
                        console.error("Status check failed", e);
                    }
                }
            });
        </script>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>