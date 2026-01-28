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

        // 1. Create Instance with Evolution API v2 structure
        $ch = curl_init($evo_url . '/instance/create');

        // Evolution API v2 expects this structure
        $payload = json_encode([
            'instanceName' => $instance_name,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS'
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $evo_key
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local/dev environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Log the raw response for debugging
        error_log("Evolution API Response: " . $response);
        error_log("HTTP Code: " . $http_code);
        if ($curl_error) {
            error_log("CURL Error: " . $curl_error);
        }

        $data = json_decode($response, true);

        // Check for CURL errors first
        if ($curl_error) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Connection error: ' . $curl_error,
                'debug' => [
                    'url' => $evo_url . '/instance/create',
                    'http_code' => $http_code
                ]
            ]);
            break;
        }

        // Check if response is valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid API response: ' . json_last_error_msg(),
                'debug' => [
                    'raw_response' => substr($response, 0, 500),
                    'http_code' => $http_code
                ]
            ]);
            break;
        }

        // Check for successful creation
        if ($http_code >= 200 && $http_code < 300) {
            // Instance created successfully
            // Now immediately call /instance/connect to trigger QR generation
            error_log("Instance created: $instance_name, now connecting...");

            // Evolution API v2 requires POST to /instance/connect to start the connection
            $ch_connect = curl_init($evo_url . '/instance/connect/' . $instance_name);
            curl_setopt($ch_connect, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_connect, CURLOPT_CUSTOMREQUEST, 'POST'); // Changed to POST
            curl_setopt($ch_connect, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $evo_key
            ]);
            curl_setopt($ch_connect, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch_connect, CURLOPT_SSL_VERIFYHOST, false);
            $connect_response = curl_exec($ch_connect);
            $connect_http_code = curl_getinfo($ch_connect, CURLINFO_HTTP_CODE);
            curl_close($ch_connect);

            error_log("Connect response (HTTP $connect_http_code): $connect_response");

            $connect_data = json_decode($connect_response, true);

            // Now try to extract QR from connect response first, then fallback to create response
            $qr_base64 = null;

            // Try different possible response structures from CONNECT endpoint
            // Structure 1: connect_data.qrcode.base64
            if (isset($connect_data['qrcode']['base64'])) {
                $qr_base64 = $connect_data['qrcode']['base64'];
            }
            // Structure 2: connect_data.qrcode.code
            elseif (isset($connect_data['qrcode']['code'])) {
                $qr_base64 = $connect_data['qrcode']['code'];
            }
            // Structure 3: connect_data.qr
            elseif (isset($connect_data['qr'])) {
                $qr_base64 = $connect_data['qr'];
            }
            // Structure 4: connect_data.base64
            elseif (isset($connect_data['base64'])) {
                $qr_base64 = $connect_data['base64'];
            }
            // Structure 5: connect_data.qrcode (direct string)
            elseif (isset($connect_data['qrcode']) && is_string($connect_data['qrcode'])) {
                $qr_base64 = $connect_data['qrcode'];
            }
            // Structure 6: connect_data.pairingCode
            elseif (isset($connect_data['pairingCode'])) {
                $qr_base64 = $connect_data['pairingCode'];
            }
            // Fallback: Try original create response
            elseif (isset($data['qrcode']['base64'])) {
                $qr_base64 = $data['qrcode']['base64'];
            } elseif (isset($data['qrcode']['code'])) {
                $qr_base64 = $data['qrcode']['code'];
            } elseif (isset($data['qr'])) {
                $qr_base64 = $data['qr'];
            } elseif (isset($data['base64'])) {
                $qr_base64 = $data['base64'];
            } elseif (isset($data['qrcode']) && is_string($data['qrcode'])) {
                $qr_base64 = $data['qrcode'];
            } elseif (isset($data['instance']['qrcode'])) {
                if (is_array($data['instance']['qrcode'])) {
                    $qr_base64 = $data['instance']['qrcode']['base64'] ?? $data['instance']['qrcode']['code'] ?? null;
                } else {
                    $qr_base64 = $data['instance']['qrcode'];
                }
            }

            if ($qr_base64) {
                // Log in DB (pairing state)
                $stmt = $pdo->prepare("INSERT INTO wa_accounts (user_id, instance_name, status) VALUES (?, ?, 'pairing')");
                $stmt->execute([$user_id, $instance_name]);

                echo json_encode([
                    'status' => 'success',
                    'qr' => $qr_base64,
                    'instance_name' => $instance_name
                ]);
            } else {
                // Instance created but no QR code in initial response
                // Try multiple methods to get QR code
                error_log("Attempting to fetch QR code via alternative methods for: $instance_name");

                // Method 1: Try /instance/connect endpoint
                sleep(2); // Give Evolution API more time

                $ch = curl_init($evo_url . '/instance/connect/' . $instance_name);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $qr_response = curl_exec($ch);
                $qr_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                error_log("Connect endpoint response (HTTP $qr_http_code): $qr_response");

                $qr_data = json_decode($qr_response, true);

                // Try all possible structures again
                $qr_base64 = $qr_data['base64']
                    ?? $qr_data['qrcode']['base64']
                    ?? $qr_data['qrcode']['code']
                    ?? $qr_data['qr']
                    ?? $qr_data['qrcode']
                    ?? $qr_data['pairingCode']
                    ?? null;

                if ($qr_base64) {
                    $stmt = $pdo->prepare("INSERT INTO wa_accounts (user_id, instance_name, status) VALUES (?, ?, 'pairing')");
                    $stmt->execute([$user_id, $instance_name]);

                    echo json_encode([
                        'status' => 'success',
                        'qr' => $qr_base64,
                        'instance_name' => $instance_name
                    ]);
                } else {
                    // Method 2: Try /instance/fetchInstances
                    $ch = curl_init($evo_url . '/instance/fetchInstances?instanceName=' . $instance_name);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $fetch_response = curl_exec($ch);
                    curl_close($ch);

                    error_log("FetchInstances response: $fetch_response");

                    $fetch_data = json_decode($fetch_response, true);

                    // Check if instance exists in array
                    if (is_array($fetch_data) && !empty($fetch_data)) {
                        $instance_info = $fetch_data[0] ?? null;
                        if ($instance_info) {
                            // Instance exists, save it and let user try to connect manually
                            $stmt = $pdo->prepare("INSERT INTO wa_accounts (user_id, instance_name, status) VALUES (?, ?, 'pairing')");
                            $stmt->execute([$user_id, $instance_name]);

                            echo json_encode([
                                'status' => 'error',
                                'message' => 'Instance created successfully, but QR code generation failed. Please try again or check Evolution API logs.',
                                'debug' => [
                                    'instance_created' => true,
                                    'instance_name' => $instance_name,
                                    'suggestion' => 'Try refreshing the page and connecting again'
                                ]
                            ]);
                        } else {
                            echo json_encode([
                                'status' => 'error',
                                'message' => 'Instance created but QR code not available. Response structure unknown.',
                                'debug' => [
                                    'response_structure' => array_keys($data),
                                    'instance_name' => $instance_name,
                                    'connect_response' => array_keys($qr_data ?? []),
                                    'fetch_response' => is_array($fetch_data) ? 'array' : gettype($fetch_data)
                                ]
                            ]);
                        }
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Instance created but QR code not available',
                            'debug' => [
                                'response_structure' => array_keys($data),
                                'instance_name' => $instance_name,
                                'all_attempts_failed' => true
                            ]
                        ]);
                    }
                }
            }
        } else {
            // HTTP error
            $error_message = $data['message'] ?? $data['error'] ?? 'Unknown error';
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create instance: ' . $error_message,
                'debug' => [
                    'http_code' => $http_code,
                    'response' => $data,
                    'url' => $evo_url . '/instance/create'
                ]
            ]);
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
