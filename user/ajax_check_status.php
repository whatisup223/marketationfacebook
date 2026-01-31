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

        // Fetch existing details to use as fallback
        $stmt = $pdo->prepare("SELECT access_token, token_type, expires_at FROM fb_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$acc_id, $user_id]);
        $account_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account_data || empty($account_data['access_token'])) {
            echo json_encode(['status' => 'error', 'message' => 'Account not found']);
            exit;
        }

        $token = $account_data['access_token'];
        $existing_token_type = $account_data['token_type'];
        $existing_expires_at = $account_data['expires_at'];

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
                // If expires_at is 0, it is Long-lived/Indefinite
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
            $fb_id = $response['id'];
            $fb_name = $response['name'] ?? '';

            // Update DB with latest name/ID if they differ or were empty
            // Keep existing token_type if present using SQL Logic
            // Note: We use the existing logic for DB update
            $stmt = $pdo->prepare("UPDATE fb_accounts SET is_active = 1, fb_id = COALESCE(NULLIF(fb_id, ''), ?), fb_name = COALESCE(NULLIF(fb_name, ''), ?), token_type = COALESCE(NULLIF(token_type, ''), 'Active') WHERE id = ?");
            $stmt->execute([$fb_id, $fb_name, $acc_id]);

            // Determine what to show in UI from FALLBACK (Existing DB Data)
            // Use fetched DB data for response so UI doesn't flicker specific details to 'Unknown'

            // Type Logic
            $display_type_raw = !empty($existing_token_type) ? $existing_token_type : 'Active';
            $display_type = $display_type_raw;
            if ($display_type_raw === 'Long-lived')
                $display_type = __('token_long_lived');
            elseif ($display_type_raw === 'Short-lived')
                $display_type = __('token_short_lived');
            elseif ($display_type_raw === 'Long-lived / Indefinite')
                $display_type = __('token_static');
            elseif ($display_type_raw === 'Active')
                $display_type = __('active');

            // Expiry Logic
            $display_expiry = __('token_never');
            if (!empty($existing_expires_at)) {
                $ts = strtotime($existing_expires_at);
                if ($ts > 0) {
                    $display_expiry = date('Y-m-d H:i', $ts);
                }
            }

            echo json_encode([
                'status' => 'active',
                'message' => __('valid_token'),
                'token_type' => $display_type,
                'expires_at' => $display_expiry,
                'expiry_prefix' => __('token_expiry'),
                'is_long_lived' => ($display_type_raw !== 'Short-lived')
            ]);
        } else {
            // Invalid
            $pdo->prepare("UPDATE fb_accounts SET is_active = 0 WHERE id = ?")->execute([$acc_id]);
            echo json_encode(['status' => 'expired', 'message' => __('invalid_token')]);
        }

    } elseif (isset($_POST['page_db_id'])) {
        // --- CHECK PAGE TOKEN ---
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
