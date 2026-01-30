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

        // 1. Try to debug the token for expiry info
        $app_id = getSetting('fb_app_id');
        $app_secret = getSetting('fb_app_secret');
        $app_token = (!empty($app_id) && !empty($app_secret)) ? ($app_id . '|' . $app_secret) : $token;

        $debug = $fb->debugToken($token, $app_token);

        $token_type = 'Unknown';
        $expires_at = null;
        $expiry_label = '';

        if (isset($debug['data'])) {
            $data = $debug['data'];
            $is_valid = $data['is_valid'] ?? false;

            if ($is_valid) {
                $token_type = ($data['expires_at'] ?? 0) == 0 ? 'Long-lived' : 'Short-lived';
                if (isset($data['expires_at']) && $data['expires_at'] > 0) {
                    $expires_at = date('Y-m-d H:i:s', $data['expires_at']);
                    $expiry_label = date('Y-m-d H:i', $data['expires_at']);

                    // Calculate remaining time
                    $remaining = $data['expires_at'] - time();
                    if ($remaining > 0) {
                        $days = floor($remaining / 86400);
                        $hours = floor(($remaining % 86400) / 3600);
                        $remains_label = ($days > 0 ? $days . 'd ' : '') . $hours . 'h';
                        $expiry_label .= " ({$remains_label})";
                    }
                } else {
                    $token_type = 'Long-lived / Indefinite';
                    $expiry_label = 'No Expiry';
                }

                // Update DB with details
                $stmt = $pdo->prepare("UPDATE fb_accounts SET is_active = 1, token_type = ?, expires_at = ? WHERE id = ?");
                $stmt->execute([$token_type, $expires_at, $acc_id]);

                // Localized labels
                $localized_type = $token_type;
                if ($token_type === 'Long-lived')
                    $localized_type = __('token_long_lived');
                elseif ($token_type === 'Short-lived')
                    $localized_type = __('token_short_lived');
                elseif ($token_type === 'Long-lived / Indefinite')
                    $localized_type = __('token_static');

                echo json_encode([
                    'status' => 'active',
                    'message' => __('valid_token'),
                    'token_type' => $localized_type,
                    'expires_at' => ($expiry_label === 'No Expiry' ? __('token_never') : $expiry_label),
                    'expiry_prefix' => __('token_expiry'),
                    'is_long_lived' => ($token_type !== 'Short-lived')
                ]);
                exit;
            }
        }

        // 2. Fallback: Simple Verify by fetching 'me'
        $response = $fb->getObject('me', $token);

        if (isset($response['id'])) {
            // Valid but maybe couldn't get debug info
            $pdo->prepare("UPDATE fb_accounts SET is_active = 1 WHERE id = ?")->execute([$acc_id]);
            echo json_encode([
                'status' => 'active',
                'message' => __('valid_token'),
                'token_type' => __('active'),
                'expires_at' => __('token_unknown'),
                'expiry_prefix' => __('token_expiry')
            ]);
        } else {
            // Invalid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 0 WHERE id = ?")->execute([$acc_id]);
            echo json_encode(['status' => 'expired', 'message' => __('invalid_token')]);
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
