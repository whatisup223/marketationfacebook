<?php
/**
 * WhatsApp Campaign Execution API
 * Handles campaign start, pause, resume, and status updates
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wa_sender_engine.php';

// Disable error display to prevent invalid JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$action = $_POST['action'] ?? '';
$campaign_id = $_POST['campaign_id'] ?? 0;

// Verify campaign ownership
$stmt = $pdo->prepare("SELECT * FROM wa_campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    echo json_encode(['status' => 'error', 'message' => 'Campaign not found']);
    exit;
}

try {
    switch ($action) {
        case 'start':
            // Update status to running
            $stmt = $pdo->prepare("UPDATE wa_campaigns SET status = 'running', started_at = NOW() WHERE id = ?");
            $stmt->execute([$campaign_id]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Campaign started',
                'campaign_status' => 'running'
            ]);
            break;

        case 'pause':
            $stmt = $pdo->prepare("UPDATE wa_campaigns SET status = 'paused', paused_at = NOW() WHERE id = ?");
            $stmt->execute([$campaign_id]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Campaign paused',
                'campaign_status' => 'paused'
            ]);
            break;

        case 'resume':
            $stmt = $pdo->prepare("UPDATE wa_campaigns SET status = 'running' WHERE id = ?");
            $stmt->execute([$campaign_id]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Campaign resumed',
                'campaign_status' => 'running'
            ]);
            break;

        case 'cancel':
            $stmt = $pdo->prepare("UPDATE wa_campaigns SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$campaign_id]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Campaign cancelled',
                'campaign_status' => 'cancelled'
            ]);
            break;

        case 'get_status':
            // Return current campaign stats
            $stmt->execute([$campaign_id, $user_id]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'campaign_status' => $campaign['status'],
                'sent_count' => (int) $campaign['sent_count'],
                'failed_count' => (int) $campaign['failed_count'],
                'total_count' => (int) $campaign['total_count'],
                'current_index' => (int) $campaign['current_number_index']
            ]);
            break;

        case 'send_next_batch':
            // Send next batch of messages
            if ($campaign['status'] !== 'running') {
                echo json_encode(['status' => 'error', 'message' => 'Campaign not running']);
                exit;
            }

            $sender = new WAUniversalSender($pdo, $campaign_id);

            // Get numbers
            $numbers = json_decode($campaign['numbers'], true);
            $current_index = (int) $campaign['current_number_index'];

            if ($current_index >= count($numbers)) {
                // Campaign completed
                $stmt = $pdo->prepare("UPDATE wa_campaigns SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$campaign_id]);

                echo json_encode([
                    'status' => 'completed',
                    'message' => 'Campaign completed',
                    'sent_count' => (int) $campaign['sent_count'],
                    'failed_count' => (int) $campaign['failed_count']
                ]);
                exit;
            }

            // Send to current number
            $number = $numbers[$current_index];
            $message = $campaign['message'];

            // Process message with variables
            $processed_message = str_replace('{{name}}', $number, $message);

            // Process spin syntax
            $processed_message = preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
                $options = explode('|', $matches[1]);
                return $options[array_rand($options)];
            }, $processed_message);

            // Send message based on gateway mode
            $result = ['success' => false, 'error' => 'Unknown error'];

            if ($campaign['gateway_mode'] === 'qr') {
                // Evolution API (QR Mode)
                $user_stmt = $pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
                $user_stmt->execute([$user_id]);
                $user_settings = $user_stmt->fetch(PDO::FETCH_ASSOC);

                $selected_accounts = json_decode($campaign['selected_accounts'] ?: '[]', true);

                // Fallback: If no accounts selected in campaign, use ALL connected accounts for this user
                if (empty($selected_accounts)) {
                    $fallback_stmt = $pdo->prepare("SELECT id FROM wa_accounts WHERE user_id = ? AND status = 'connected'");
                    $fallback_stmt->execute([$user_id]);
                    $selected_accounts = $fallback_stmt->fetchAll(PDO::FETCH_COLUMN);
                }

                if (!empty($selected_accounts)) {
                    $account_id = $selected_accounts[$campaign['current_account_index'] % count($selected_accounts)];

                    $acc_stmt = $pdo->prepare("SELECT * FROM wa_accounts WHERE id = ?");
                    $acc_stmt->execute([$account_id]);
                    $account = $acc_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($account && $user_settings) {
                        $evolution_url = $user_settings['evolution_url'];

                        // Fallback: Use Global Settings if User Settings is empty
                        if (empty($evolution_url)) {
                            $global_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_evolution_url'");
                            $evolution_url = $global_stmt->fetchColumn();
                        }

                        $api_key = $user_settings['evolution_api_key'];
                        $instance_name = $account['instance_name'];

                        $endpoint = "$evolution_url/message/sendText/$instance_name";
                        $data = [
                            'number' => $number,
                            'text' => $processed_message
                        ];

                        $ch = curl_init($endpoint);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'apikey: ' . $api_key
                        ]);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);

                        error_log("Evolution API Response - HTTP $http_code: $response");
                        if ($curl_error) {
                            error_log("cURL Error: $curl_error");
                        }

                        if ($http_code >= 200 && $http_code < 300) {
                            $result = ['success' => true];
                        } else {
                            $result = ['success' => false, 'error' => "HTTP $http_code: $response" . ($curl_error ? " | cURL: $curl_error" : "")];
                        }
                    } else {
                        $result = ['success' => false, 'error' => 'Account or settings not found'];
                    }
                } else {
                    $result = ['success' => false, 'error' => 'No accounts selected'];
                }
            } elseif ($campaign['gateway_mode'] === 'twilio') {
                // Twilio API
                $user_stmt = $pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
                $user_stmt->execute([$user_id]);
                $user_settings = $user_stmt->fetch(PDO::FETCH_ASSOC);

                if ($user_settings) {
                    // Extract from external_config JSON
                    $config = json_decode($user_settings['external_config'] ?? '{}', true);

                    $account_sid = $config['sid'] ?? ($user_settings['twilio_account_sid'] ?? '');
                    $auth_token = $config['token'] ?? ($user_settings['twilio_auth_token'] ?? '');
                    $from_number = $config['phone'] ?? ($user_settings['twilio_from_number'] ?? '');

                    // Debug Log
                    error_log("Twilio Check userID: $user_id | SID: " . substr($account_sid, 0, 5) . "... | Token: " . ($auth_token ? 'SET' : 'EMPTY') . " | From: $from_number");

                    if ($account_sid && $auth_token && $from_number) {
                        $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";

                        // Format number for Twilio (must include country code)
                        $to_number = $number;
                        if (!str_starts_with($to_number, '+')) {
                            $to_number = '+' . $to_number;
                        }

                        $data = [
                            'From' => "whatsapp:$from_number",
                            'To' => "whatsapp:$to_number",
                            'Body' => $processed_message
                        ];

                        // Add Media if present
                        if (!empty($campaign['media_url']) && $campaign['media_type'] !== 'text') {
                            // Ensure URL is absolute (if stored relatively, prepend full site URL)
                            // Note: Twilio requires a PUBLICLY accessible URL. Localhost URLs won't work.
                            $media_url = $campaign['media_url'];

                            // Basic check if it's a full URL
                            if (!filter_var($media_url, FILTER_VALIDATE_URL)) {
                                // If relative path, try to construct full URL based on server (might still fail on localhost)
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $media_url = "$protocol://$host/" . ltrim($media_url, '/');
                            }

                            $data['MediaUrl'] = [$media_url]; // Twilio expects array or single string? Usually list for MMS. MediaUrl parameter can be used multiple times.
                            // In PHP array for http_build_query with same key:
                            // We use 'MediaUrl' => $media_url for single file.
                            $data['MediaUrl'] = $media_url;
                        }

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                        curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);

                        error_log("Twilio API Response - HTTP $http_code: $response");
                        if ($curl_error) {
                            error_log("cURL Error: $curl_error");
                        }

                        if ($http_code >= 200 && $http_code < 300) {
                            $result = ['success' => true];
                        } else {
                            $result = ['success' => false, 'error' => "HTTP $http_code: $response" . ($curl_error ? " | cURL: $curl_error" : "")];
                        }
                    } else {
                        $result = ['success' => false, 'error' => 'Twilio credentials not configured'];
                    }
                } else {
                    $result = ['success' => false, 'error' => 'User settings not found'];
                }
            } elseif ($campaign['gateway_mode'] === 'meta') {
                // Meta Business API
                $user_stmt = $pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
                $user_stmt->execute([$user_id]);
                $user_settings = $user_stmt->fetch(PDO::FETCH_ASSOC);

                if ($user_settings) {
                    $access_token = $user_settings['meta_access_token'];
                    $phone_number_id = $user_settings['meta_phone_number_id'];

                    if ($access_token && $phone_number_id) {
                        $url = "https://graph.facebook.com/v18.0/$phone_number_id/messages";

                        $data = [
                            'messaging_product' => 'whatsapp',
                            'to' => $number,
                            'type' => 'text',
                            'text' => ['body' => $processed_message]
                        ];

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $access_token
                        ]);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);

                        error_log("Meta API Response - HTTP $http_code: $response");
                        if ($curl_error) {
                            error_log("cURL Error: $curl_error");
                        }

                        if ($http_code >= 200 && $http_code < 300) {
                            $result = ['success' => true];
                        } else {
                            $result = ['success' => false, 'error' => "HTTP $http_code: $response" . ($curl_error ? " | cURL: $curl_error" : "")];
                        }
                    } else {
                        $result = ['success' => false, 'error' => 'Meta credentials not configured'];
                    }
                } else {
                    $result = ['success' => false, 'error' => 'User settings not found'];
                }
            }

            if ($result['success']) {
                $campaign['sent_count']++;
            } else {
                $campaign['failed_count']++;
            }

            // Update progress
            $stmt = $pdo->prepare("
                UPDATE wa_campaigns 
                SET current_number_index = ?, 
                    sent_count = ?, 
                    failed_count = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $current_index + 1,
                $campaign['sent_count'],
                $campaign['failed_count'],
                $campaign_id
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => $result['success'] ? 'Message sent' : 'Message failed',
                'number' => $number,
                'result' => $result,
                'sent_count' => (int) $campaign['sent_count'],
                'failed_count' => (int) $campaign['failed_count'],
                'current_index' => $current_index + 1,
                'total_count' => count($numbers)
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
