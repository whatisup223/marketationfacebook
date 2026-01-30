<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Release session lock immediately to prevent blocking other page loads
session_write_close();

$pdo = getDB();

try {
    // Get Admin Settings for Evolution
    $stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('wa_evolution_url', 'wa_evolution_apikey', 'wa_webhook_url')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $evo_url = rtrim($settings['wa_evolution_url'] ?? '', '/');
    $evo_key = $settings['wa_evolution_apikey'] ?? '';

    // Auto-generate Webhook URL based on current server
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $webhook_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/webhook.php";

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

            // Build payload
            $payload_data = [
                'instanceName' => $instance_name,
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS'
            ];

            // Temporarily disable Webhook to isolate connectivity issue
            /*
            if (!empty($webhook_url)) {
                $payload_data['webhook'] = $webhook_url;
                $payload_data['webhook_by_events'] = false;
                $payload_data['events'] = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'];
            }
            */

            $payload = json_encode($payload_data);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $evo_key
            ]);

            // Connectivity Fixes
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Fail fast if no route
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Max wait time
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
                // Evolution API v2 needs time to generate QR code
                error_log("Instance created: $instance_name, waiting for QR generation...");

                // Wait 3 seconds for Evolution to generate QR
                sleep(3);

                // Now fetch QR code using dedicated endpoint
                $ch_qr = curl_init($evo_url . '/instance/qrcode/' . $instance_name);
                curl_setopt($ch_qr, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_qr, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                curl_setopt($ch_qr, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch_qr, CURLOPT_SSL_VERIFYHOST, false);
                $qr_response = curl_exec($ch_qr);
                $qr_http_code = curl_getinfo($ch_qr, CURLINFO_HTTP_CODE);
                curl_close($ch_qr);

                error_log("QR endpoint response (HTTP $qr_http_code): $qr_response");

                $qr_data = json_decode($qr_response, true);

                // Now try to extract QR from qrcode endpoint response
                $qr_base64 = null;

                // Try different possible response structures from QR endpoint
                // Structure 1: qr_data.base64
                if (isset($qr_data['base64'])) {
                    $qr_base64 = $qr_data['base64'];
                }
                // Structure 2: qr_data.qrcode.base64
                elseif (isset($qr_data['qrcode']['base64'])) {
                    $qr_base64 = $qr_data['qrcode']['base64'];
                }
                // Structure 3: qr_data.qrcode.code
                elseif (isset($qr_data['qrcode']['code'])) {
                    $qr_base64 = $qr_data['qrcode']['code'];
                }
                // Structure 4: qr_data.qr
                elseif (isset($qr_data['qr'])) {
                    $qr_base64 = $qr_data['qr'];
                }
                // Structure 5: qr_data.code
                elseif (isset($qr_data['code'])) {
                    $qr_base64 = $qr_data['code'];
                }
                // Structure 6: qr_data.qrcode (direct string)
                elseif (isset($qr_data['qrcode']) && is_string($qr_data['qrcode'])) {
                    $qr_base64 = $qr_data['qrcode'];
                }
                // Structure 7: qr_data.pairingCode
                elseif (isset($qr_data['pairingCode'])) {
                    $qr_base64 = $qr_data['pairingCode'];
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

        case 'poll_qr':
            // New action for actively polling QR code
            $instance_name = $_POST['instance_name'] ?? '';
            if (empty($instance_name)) {
                echo json_encode(['status' => 'error', 'message' => 'Instance name required']);
                exit;
            }

            // Evolution API v2 has a dedicated QR endpoint
            // Try /instance/qrcode/{instanceName} endpoint
            $ch = curl_init($evo_url . '/instance/qrcode/' . $instance_name);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("QR endpoint response (HTTP $http_code): $response");

            $data = json_decode($response, true);

            // Try all possible QR structures
            $qr_base64 = null;

            if ($http_code >= 200 && $http_code < 300) {
                $qr_base64 = $data['base64']
                    ?? $data['qrcode']['base64']
                    ?? $data['qrcode']['code']
                    ?? $data['qr']
                    ?? $data['qrcode']
                    ?? $data['pairingCode']
                    ?? $data['code']
                    ?? null;
            }

            if ($qr_base64) {
                echo json_encode(['status' => 'success', 'qr' => $qr_base64]);
            } else {
                // Fallback: Try POST to /instance/connect to trigger QR generation
                $ch = curl_init($evo_url . '/instance/connect/' . $instance_name);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $evo_key
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $connect_response = curl_exec($ch);
                curl_close($ch);

                $connect_data = json_decode($connect_response, true);

                // Check connect response for QR
                $qr_base64 = $connect_data['base64']
                    ?? $connect_data['qrcode']['base64']
                    ?? $connect_data['qrcode']['code']
                    ?? $connect_data['qr']
                    ?? $connect_data['qrcode']
                    ?? null;

                if ($qr_base64) {
                    echo json_encode(['status' => 'success', 'qr' => $qr_base64]);
                } else {
                    echo json_encode(['status' => 'waiting', 'message' => 'QR not ready yet']);
                }
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



        case 'logout':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $acc = $stmt->fetch();

            if ($acc) {
                // Call Evolution Logout
                $ch = curl_init($evo_url . '/instance/logout/' . $acc['instance_name']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                curl_exec($ch);
                curl_close($ch);

                // Update DB status
                $pdo->prepare("UPDATE wa_accounts SET status = 'disconnected' WHERE id = ?")->execute([$id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Account not found']);
            }
            break;

        case 'restart':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $acc = $stmt->fetch();

            if ($acc) {
                // Call Evolution Restart
                $ch = curl_init($evo_url . '/instance/restart/' . $acc['instance_name']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Evolution usually uses PUT or POST for restart
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                $res = curl_exec($ch);
                curl_close($ch);

                // We don't change DB status on restart, just return success
                echo json_encode(['status' => 'success', 'debug' => $res]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Account not found']);
            }
            break;

        case 'delete_account':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $acc = $stmt->fetch();

            if ($acc) {
                $inst = trim($acc['instance_name']); // Trim whitespace

                error_log("Attempting to delete instance: " . $inst);

                // 1. Force Logout first (best practice)
                $ch = curl_init($evo_url . '/instance/logout/' . $inst);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                $logout_res = curl_exec($ch);
                curl_close($ch);

                error_log("Logout result for $inst: $logout_res");

                // Small delay to allow Evolution to process logout
                usleep(500000); // 0.5s

                // 2. Delete from Evolution
                $ch = curl_init($evo_url . '/instance/delete/' . $inst);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                $del_res = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                error_log("Delete result for $inst (HTTP $http_code): $del_res");

                // 3. Delete from DB regardless of API success (to allow clearing stuck accounts)
                $pdo->prepare("DELETE FROM wa_accounts WHERE id = ?")->execute([$id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Account not found']);
            }
            break;

        case 'check_status':
            $instance_name = $_POST['instance_name'] ?? '';
            if (empty($instance_name))
                exit;

            // Strategy 1: Check connectionState
            $ch = curl_init($evo_url . '/instance/connectionState/' . $instance_name);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            $state = $data['instance']['state'] ?? $data['state'] ?? 'close';

            // Strategy 2: If state is 'close' or 'connecting', double check with fetchInstances
            // (Sometimes connectionState lags behind)
            if ($state !== 'open') {
                $ch = curl_init($evo_url . '/instance/fetchInstances?instanceName=' . $instance_name);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                $res = curl_exec($ch);
                curl_close($ch);
                $inst_data = json_decode($res, true);

                // If we get an owner JID, it means we are definitely connected
                $owner = null;
                if (isset($inst_data['instance']['owner'])) {
                    $owner = $inst_data['instance']['owner'];
                } elseif (isset($inst_data[0]['owner'])) {
                    $owner = $inst_data[0]['owner']; // Evolution v2 array response
                } elseif (isset($inst_data['owner'])) { // Single object response
                    $owner = $inst_data['owner'];
                }

                if ($owner) {
                    $state = 'open'; // Force state to open because we have data
                }
            }

            if ($state === 'open') {
                // Connected! Get phone number if we haven't already
                $phone = '';

                // Fetch instance details again if we didn't do it in Strategy 2
                if (!isset($inst_data)) {
                    $ch = curl_init($evo_url . '/instance/fetchInstances?instanceName=' . $instance_name);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $evo_key]);
                    $res = curl_exec($ch);
                    curl_close($ch);
                    $inst_data = json_decode($res, true);
                }

                if (isset($inst_data['instance']['owner'])) {
                    $phone = explode('@', $inst_data['instance']['owner'])[0];
                } elseif (isset($inst_data[0]['owner'])) {
                    $phone = explode('@', $inst_data[0]['owner'])[0];
                } elseif (isset($inst_data['owner'])) {
                    $phone = explode('@', $inst_data['owner'])[0];
                }

                // Update DB only if valid phone found or state is confirmed open
                if ($phone || $state === 'open') {
                    $stmt = $pdo->prepare("UPDATE wa_accounts SET status = 'connected', phone = ? WHERE instance_name = ? AND user_id = ?");
                    $stmt->execute([$phone, $instance_name, $user_id]);

                    echo json_encode(['connected' => true, 'phone' => $phone]);
                } else {
                    echo json_encode(['connected' => false, 'state' => $state]);
                }
            } else {
                echo json_encode(['connected' => false, 'state' => $state]);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
