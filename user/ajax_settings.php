<?php
header('Content-Type: application/json');
require '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$pdo = getDB();

try {
    if ($action === 'fetch_smtp') {
        $stmt = $pdo->prepare("SELECT smtp_config FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $config = json_decode($stmt->fetchColumn() ?: '{}', true);
        echo json_encode(['success' => true, 'config' => $config]);
        exit;
    }

    if ($action === 'save_smtp') {
        $config = [
            'enabled' => ($_POST['enabled'] ?? '0') == '1',
            'host' => trim($_POST['host'] ?? ''),
            'port' => trim($_POST['port'] ?? '587'),
            'encryption' => trim($_POST['encryption'] ?? 'tls'),
            'username' => trim($_POST['username'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
        ];

        // Basic validation
        if ($config['enabled'] && (empty($config['host']) || empty($config['username']))) {
            echo json_encode(['error' => __('fill_all_fields')]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET smtp_config = ? WHERE id = ?");
        $stmt->execute([json_encode($config), $user_id]);

        echo json_encode(['success' => true, 'message' => __('settings_updated')]);
        exit;
    }

    if ($action === 'test_smtp') {
        // Fetch current config from DB to ensure we test what is saved
        $stmt = $pdo->prepare("SELECT smtp_config, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $config = json_decode($user['smtp_config'] ?: '{}', true);
        $to_email = $user['email'];

        if (empty($config) || empty($config['host'])) {
            echo json_encode(['error' => 'Please save SMTP settings first.']);
            exit;
        }

        // Use the custom SMTP sender
        $subject = "SMTP Test Connection - Marketation";
        $body = "<h1>Connection Successful!</h1><p>Your SMTP settings are working correctly.</p>";

        $result = sendUserEmail($user_id, $to_email, $subject, $body, $config);

        if ($result === true) {
            echo json_encode(['success' => true, 'message' => __('smtp_test_success')]);
        } else {
            echo json_encode(['error' => 'SMTP Error: ' . $result]);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
