<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$fb = new FacebookAPI();

try {
    if (isset($_POST['account_id'])) {
        // --- CHECK USER ACCOUNT TOKEN ---
        $acc_id = $_POST['account_id'];
        $stmt = $pdo->prepare("SELECT access_token FROM fb_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$acc_id, $user_id]);
        $token = $stmt->fetchColumn();

        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Account not found']);
            exit;
        }

        // Verify by fetching 'me'
        $response = $fb->getObject('me', $token);

        if (isset($response['id'])) {
            // Valid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 1 WHERE id = ?")->execute([$acc_id]);
            echo json_encode(['status' => 'active', 'message' => 'Token is valid']);
        } else {
            // Invalid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 0 WHERE id = ?")->execute([$acc_id]);
            echo json_encode(['status' => 'expired', 'message' => 'Token expired']);
        }

    } elseif (isset($_POST['page_db_id'])) {
        // --- CHECK PAGE TOKEN ---
        // Note: The UI indicator in page_inbox usually reflects the ACCOUNT status (is_active), 
        // but checking the page token is surprisingly more relevant for the inbox functionality.
        // However, the user asked to fix the specific "Token Expired" indicator which relies on fb_accounts.is_active.
        // So we should check the ACCOUNT token associated with this page.

        $page_db_id = $_POST['page_db_id'];

        $stmt = $pdo->prepare("SELECT a.id, a.access_token FROM fb_pages p 
                               JOIN fb_accounts a ON p.account_id = a.id 
                               WHERE p.id = ? AND a.user_id = ?");
        $stmt->execute([$page_db_id, $user_id]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$acc) {
            echo json_encode(['status' => 'error', 'message' => 'Page not found']);
            exit;
        }

        // Verify Account Token
        $response = $fb->getObject('me', $acc['access_token']);

        if (isset($response['id'])) {
            // Valid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 1 WHERE id = ?")->execute([$acc['id']]);
            echo json_encode(['status' => 'active', 'message' => 'Token is valid']);
        } else {
            // Invalid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 0 WHERE id = ?")->execute([$acc['id']]);
            echo json_encode(['status' => 'expired', 'message' => 'Token expired']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
