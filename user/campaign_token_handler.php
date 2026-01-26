<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

// Disable error display in JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Unlock session for concurrent requests
session_write_close();
$pdo = getDB();

$action = $_POST['action'] ?? '';

try {
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
        $res = $fb->getObjectMetadata('me', $page['page_access_token']);

        if (isset($res['error'])) {
            echo json_encode(['status' => 'invalid', 'message' => $res['error']]);
        } else if (isset($res['id'])) {
            echo json_encode(['status' => 'valid']);
        } else {
            echo json_encode(['status' => 'invalid', 'message' => 'Unknown API Response']);
        }
        exit;
    }

    if ($action === 'update_token') {
        $campaign_id = $_POST['campaign_id'] ?? 0;
        $new_token = trim($_POST['new_token'] ?? '');

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

        // 1. Validate New Token
        $meta = $fb->getObjectMetadata('me', $new_token);

        if (isset($meta['id'])) {
            $token_fbid = $meta['id'];
            $token_name = $meta['name'] ?? 'Unknown';

            // Check if it's the Page Token directly
            if ($token_fbid == $target_fb_id) {
                $update = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
                $update->execute([$new_token, $page_db_id]);
                echo json_encode(['status' => 'success', 'type' => 'page_token']);
                exit;
            }

            // Check if it's the User Token (Main Account)
            $accounts = $fb->getAccounts($new_token);
            $found_token = null;
            $pages_to_update = [];

            if (isset($accounts['data'])) {
                foreach ($accounts['data'] as $acc) {
                    if ($acc['id'] == $target_fb_id) {
                        $found_token = $acc['access_token'];
                    }
                    $pages_to_update[$acc['id']] = $acc['access_token'];
                }
            }

            if ($found_token) {
                // It is a valid User Token containing the page scope

                // 1. Update/Verify Account Info
                // We update the account record corresponding to $account_id
                $upd_acc = $pdo->prepare("UPDATE fb_accounts SET access_token = ?, fb_id = ?, fb_name = ?, is_active = 1 WHERE id = ?");
                $upd_acc->execute([$new_token, $token_fbid, $token_name, $account_id]);

                // 2. Update The Target Page
                $upd_page = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE id = ?");
                $upd_page->execute([$found_token, $page_db_id]);

                // 3. Update All Other Pages Linked to Account
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

                echo json_encode(['status' => 'success', 'type' => 'global_token']);
                exit;

            } else {
                echo json_encode(['status' => 'error', 'message' => 'Token is valid but does not have permission for this page.']);
                exit;
            }

        } else {
            $errMsg = $meta['error']['message'] ?? 'Unknown Error';
            echo json_encode(['status' => 'error', 'message' => 'Invalid Token: ' . $errMsg]);
            exit;
        }
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
