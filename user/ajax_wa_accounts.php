<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// Get Admin Settings for Evolution
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('wa_evolution_url', 'wa_evolution_apikey')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$evo_url = rtrim($settings['wa_evolution_url'] ?? '', '/');
$evo_key = $settings['wa_evolution_apikey'] ?? '';

if (empty($evo_url) || empty($evo_key)) {
    echo json_encode(['status' => 'error', 'message' => 'WhatsApp Gateway not configured by Admin.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'init_instance':
        $instance_name = 'user_' . $user_id . '_' . time();

        // 1. Create Instance
        $ch = curl_init($evo_url . '/instance/create');
        $payload = json_encode([
            'instanceName' => $instance_name,
            'token' => bin2hex(random_bytes(16)),
            'qrcode' => true
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $evo_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($http_code == 201 || (isset($data['instance']) && isset($data['qrcode']))) {
            // Success creating or getting QR
            $qr_base64 = $data['qrcode']['base64'] ?? '';

            // Log in DB (pairing state)
            $stmt = $pdo->prepare("INSERT INTO wa_accounts (user_id, instance_name, status) VALUES (?, ?, 'pairing')");
            $stmt->execute([$user_id, $instance_name]);

            echo json_encode([
                'status' => 'success',
                'qr' => $qr_base64,
                'instance_name' => $instance_name
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create instance: ' . ($data['message'] ?? 'Unknown error')]);
        }
        break;

    case 'get_qr':
        $instance_name = $_POST['instance_name'] ?? '';
        if (empty($instance_name))
            exit;

        $ch = curl_init($evo_url . '/instance/connect/' . $instance_name);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['base64'])) {
            echo json_encode(['status' => 'success', 'qr' => $data['base64']]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        break;

    case 'check_status':
        $instance_name = $_POST['instance_name'] ?? '';
        if (empty($instance_name))
            exit;

        $ch = curl_init($evo_url . '/instance/connectionState/' . $instance_name);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $state = $data['instance']['state'] ?? 'close';

        if ($state === 'open') {
            // Update DB
            // Get phone number if possible
            $ch = curl_init($evo_url . '/instance/fetchInstances?instanceName=' . $instance_name);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
            $res = curl_exec($ch);
            curl_close($ch);
            $inst_data = json_decode($res, true);

            $phone = '';
            if (isset($inst_data[0]['owner'])) {
                $phone = explode('@', $inst_data[0]['owner'])[0];
            }

            $stmt = $pdo->prepare("UPDATE wa_accounts SET status = 'connected', phone = ? WHERE instance_name = ? AND user_id = ?");
            $stmt->execute([$phone, $instance_name, $user_id]);

            echo json_encode(['connected' => true]);
        } else {
            echo json_encode(['connected' => false]);
        }
        break;

    case 'delete_account':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $acc = $stmt->fetch();

        if ($acc) {
            // 1. Delete from Evolution
            $ch = curl_init($evo_url . '/instance/delete/' . $acc['instance_name']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
            curl_exec($ch);
            curl_close($ch);

            // 2. Delete from DB
            $pdo->prepare("DELETE FROM wa_accounts WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
