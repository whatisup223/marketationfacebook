<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/facebook_api.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$action = $_POST['action'] ?? '';

if ($action === 'check_token') {
    $campaign_id = $_POST['campaign_id'] ?? 0;

    // Get page token from campaign linked page
    $stmt = $pdo->prepare("
        SELECT p.page_access_token, p.page_id 
        FROM campaigns c
        JOIN fb_pages p ON c.page_id = p.id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$campaign_id, $user_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        echo json_encode(['status' => 'error', 'message' => 'Campaign or Page not found']);
        exit;
    }

    $fb = new FacebookAPI();

    // Improved Validation: Check 'me' instead of 'me/accounts'
    // 'me/accounts' fails for Page Tokens, causing a false negative loop.
    $res = $fb->getObjectMetadata('me', $page['page_access_token']);

    if (isset($res['error'])) {
        echo json_encode(['status' => 'invalid', 'message' => $res['error']]);
    } else if (isset($res['id'])) {
        echo json_encode(['status' => 'valid']);
    } else {
        // Fallback if structure is unexpected
        echo json_encode(['status' => 'invalid', 'message' => 'Unknown API Response']);
    }
    exit;
}

if ($action === 'update_token') {
    $campaign_id = $_POST['campaign_id'] ?? 0;
    $new_token = $_POST['new_token'] ?? '';

    if (empty($new_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Token cannot be empty']);
        exit;
    }

    // Get page and account info from campaign
    $stmt = $pdo->prepare("
        SELECT p.id as db_page_id, p.page_id as fb_page_id, p.account_id, a.fb_id as account_fb_id 
        FROM campaigns c 
        JOIN fb_pages p ON c.page_id = p.id 
        JOIN fb_accounts a ON p.account_id = a.id 
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$campaign_id, $user_id]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$camp) {
        echo json_encode(['status' => 'error', 'message' => 'Campaign not found']);
        exit;
    }

    $fb = new FacebookAPI();
    $page_db_id = $camp['db_page_id'];
    $target_fb_id = $camp['fb_page_id'];
    $account_id = $camp['account_id'];
    $account_fb_id = $camp['account_fb_id'];

    // 1. Identify Token Type
    $meta = $fb->getObjectMetadata('me', $new_token);

    if (isset($meta['id'])) {
        $token_fbid = $meta['id'];

        // CASE A: It is the Page Token itself
        if ($token_fbid == $target_fb_id) {
            $update = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
            $update->execute([$new_token, $page_db_id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        // CASE B: It is a User Token (Main Account Token)
        // We check if it can access the target page.
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
            $upd_acc = $pdo->prepare("UPDATE fb_accounts SET access_token = ?, fb_id = ?, fb_name = ? WHERE id = ?");
            $upd_acc->execute([$new_token, $token_fbid, $meta['name'], $account_id]);

            // 2. Update Target Page (Priority)
            $upd_page = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
            $upd_page->execute([$found_token, $page_db_id]);

            // 3. Update ALL other pages linked to this account
            // We fetch all DB pages for this account
            $db_pages_stmt = $pdo->prepare("SELECT id, page_id FROM fb_pages WHERE account_id = ?");
            $db_pages_stmt->execute([$account_id]);
            $db_pages = $db_pages_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($db_pages as $dbp) {
                $pid = $dbp['page_id'];
                if (isset($pages_to_update[$pid])) {
                    // Update this page's token too
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
        echo json_encode(['status' => 'error', 'message' => 'Invalid Token: ' . ($meta['error']['message'] ?? 'Unknown')]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
