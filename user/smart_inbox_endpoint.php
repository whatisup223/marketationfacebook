<?php
require_once '../includes/functions.php';
date_default_timezone_set('UTC'); // Force UTC for API consistency
require_once '../includes/SmartInboxEngine.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list_conversations':
            $stmt = $pdo->prepare("SELECT * FROM unified_conversations WHERE user_id = ? ORDER BY last_message_time DESC LIMIT 100");
            $stmt->execute([$userId]);
            $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'conversations' => $convs]);
            break;

        case 'get_messages':
            $convId = $_GET['conversation_id'] ?? 0;
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM unified_conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$convId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception("Conversation not found");
            }

            $stmt = $pdo->prepare("SELECT * FROM unified_messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$convId]);
            $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'messages' => $msgs]);
            break;

        case 'analyze_thread':
            // Only analyze if requested via POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                throw new Exception("Method Not Allowed");

            $input = json_decode(file_get_contents('php://input'), true);
            $convId = $input['conversation_id'] ?? 0;

            // Check ownership
            $check = $pdo->prepare("SELECT id FROM unified_conversations WHERE id = ? AND user_id = ?");
            $check->execute([$convId, $userId]);
            if (!$check->fetch())
                throw new Exception("Unauthorized access to conversation.");

            // Instantiate Engine
            // Re-instantiate to be safe if file was just included
            $engine = new SmartInboxEngine($pdo);
            $result = $engine->analyzeCreateReply($convId, $userId);

            echo json_encode(['success' => true, 'analysis' => $result]);
            break;

        case 'list_pages':
            $stmt = $pdo->prepare("SELECT p.page_id, p.page_name, p.ig_business_id, a.platform FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE a.user_id = ? AND a.is_active = 1");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'pages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'sync_conversations':
            set_time_limit(120);

            // Self-Healing DB: Ensure columns exist
            try {
                $pdo->query("SELECT page_id FROM unified_conversations LIMIT 1");
            } catch (Exception $e) {
                try {
                    $pdo->exec("ALTER TABLE unified_conversations ADD COLUMN page_id VARCHAR(50) NULL AFTER platform");
                } catch (Exception $ex) {
                }
            }
            try {
                $pdo->query("SELECT meta_message_id FROM unified_messages LIMIT 1");
            } catch (Exception $e) {
                try {
                    $pdo->exec("ALTER TABLE unified_messages ADD COLUMN meta_message_id VARCHAR(255) NULL AFTER message_text");
                    $pdo->exec("ALTER TABLE unified_messages ADD UNIQUE KEY uk_meta_id (meta_message_id)");
                } catch (Exception $ex) {
                }
            }

            $specificPageId = $_GET['page_id'] ?? null;

            // 1. Get pages
            $sql = "SELECT p.page_id, p.page_access_token, p.page_name, p.ig_business_id 
                    FROM fb_pages p 
                    JOIN fb_accounts a ON p.account_id = a.id 
                    WHERE a.user_id = ? AND a.is_active = 1";

            $params = [$userId];

            if ($specificPageId) {
                $sql .= " AND p.page_id = ?";
                $params[] = $specificPageId;
            } else {
                $sql .= " LIMIT 100";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Prevent session locking during long sync
            session_write_close();

            $syncedCount = 0;
            $errors = [];
            $startTime = time();

            foreach ($pages as $page) {
                // Stop if we are running too long (over 90 seconds)
                if (time() - $startTime > 90)
                    break;

                $tasks = [];

                // Task 1: Facebook Conversations
                $tasks[] = [
                    'platform' => 'facebook',
                    'id' => $page['page_id'],
                    'token' => $page['page_access_token'],
                    'name' => $page['page_name'] . ' (FB)'
                ];

                // Task 2: Instagram Conversations (if linked)
                if (!empty($page['ig_business_id'])) {
                    $tasks[] = [
                        'platform' => 'instagram',
                        'id' => $page['ig_business_id'],
                        'token' => $page['page_access_token'], // Uses same page token usually
                        'name' => $page['page_name'] . ' (IG)'
                    ];
                } else {
                    $errors[] = "Page '{$page['page_name']}' has no linked Instagram Business Account.";
                }

                foreach ($tasks as $task) {
                    if (time() - $startTime > 90)
                        break;

                    $url = "https://graph.facebook.com/v19.0/{$task['id']}/conversations?fields=id,updated_time,participants,messages.limit(1){message,created_time,from}&limit=20&access_token={$task['token']}";
                    if ($task['platform'] === 'instagram') {
                        $url = "https://graph.facebook.com/v19.0/{$task['id']}/conversations?platform=instagram&fields=id,updated_time,participants,messages.limit(1){message,created_time,from}&limit=20&access_token={$task['token']}";
                    }

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout per request
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $data = json_decode($response, true);

                    if ($httpCode !== 200 || !isset($data['data'])) {
                        $errMsg = isset($data['error']) ? $data['error']['message'] : "HTTP $httpCode";
                        $errors[] = "{$task['name']} Sync Failed: $errMsg";
                        continue;
                    }

                    foreach ($data['data'] as $fbConv) {
                        if (time() - $startTime > 90)
                            break;

                        $clientName = "Unknown User";
                        $clientPsid = null;

                        if (isset($fbConv['participants']['data'])) {
                            foreach ($fbConv['participants']['data'] as $part) {
                                if ($part['id'] != $task['id']) {
                                    $clientName = $part['name'] ?? $part['username'] ?? 'Instagram User';
                                    if ($clientName === 'Instagram User' && isset($part['id'])) {
                                        $clientName = "User " . substr($part['id'], -4);
                                    }
                                    $clientPsid = $part['id'];
                                    break;
                                }
                            }
                        }

                        if (!$clientPsid)
                            continue;

                        $lastMsgText = $fbConv['messages']['data'][0]['message'] ?? '[Media/Other]';
                        $lastMsgTime = isset($fbConv['messages']['data'][0]['created_time'])
                            ? date('Y-m-d H:i:s', strtotime($fbConv['messages']['data'][0]['created_time']))
                            : date('Y-m-d H:i:s');

                        // Optimistic Upsert - Try Update first to avoid select overhead if possible, 
                        // but here we follow logic: Select -> Update/Insert
                        $check = $pdo->prepare("SELECT id FROM unified_conversations WHERE user_id = ? AND platform = ? AND client_psid = ?");
                        $check->execute([$userId, $task['platform'], $clientPsid]);
                        $existing = $check->fetch();

                        if ($existing) {
                            $convId = $existing['id'];
                            $upd = $pdo->prepare("UPDATE unified_conversations SET last_message_text = ?, last_message_time = ?, client_name = ?, page_id = ? WHERE id = ?");
                            $upd->execute([$lastMsgText, $lastMsgTime, $clientName, $task['id'], $convId]);
                        } else {
                            $ins = $pdo->prepare("INSERT INTO unified_conversations (user_id, platform, client_psid, client_name, last_message_text, last_message_time, page_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $ins->execute([$userId, $task['platform'], $clientPsid, $clientName, $lastMsgText, $lastMsgTime, $task['id']]);
                            $convId = $pdo->lastInsertId();
                        }

                        $syncedCount++;

                        // Messages: Fetch only if updated recently or brand new
                        // To save time, we can skip fetching messages for old convs if not needed
                        // But user asked for history. Let's limit fetching.

                        $msgUrl = "https://graph.facebook.com/v19.0/{$fbConv['id']}/messages?fields=id,message,created_time,from&limit=50&access_token={$task['token']}";
                        $chMsg = curl_init($msgUrl);
                        curl_setopt($chMsg, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMsg, CURLOPT_TIMEOUT, 10);
                        $msgResp = curl_exec($chMsg);
                        curl_close($chMsg);
                        $msgData = json_decode($msgResp, true);

                        if (isset($msgData['data'])) {
                            $messages = array_reverse($msgData['data']);

                            // Batched insert would be better, but sticking to loop for now
                            // Optimization: Check most recent message in DB first
                            foreach ($messages as $m) {
                                if (!isset($m['message']))
                                    continue;
                                $senderType = ($m['from']['id'] == $task['id']) ? 'page' : 'user';
                                $msgTime = date('Y-m-d H:i:s', strtotime($m['created_time']));
                                $metaId = $m['id'] ?? null;

                                // Quick check to avoid duplicates
                                // Use IGNORE because we added UNIQUE KEY on meta_message_id
                                $inMsg = $pdo->prepare("INSERT IGNORE INTO unified_messages (conversation_id, sender, message_text, created_at, meta_message_id) VALUES (?, ?, ?, ?, ?)");
                                $inMsg->execute([$convId, $senderType, $m['message'], $msgTime, $metaId]);
                            }
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'synced_count' => $syncedCount, 'errors' => $errors]);
            break;

        case 'send_message':
            $input = json_decode(file_get_contents('php://input'), true);
            $convId = $input['conversation_id'];
            $text = $input['message_text'];

            // 1. Get Conversation Details to find Page ID and Client PSID
            $stmt = $pdo->prepare("SELECT platform, client_psid, page_id FROM unified_conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$convId, $userId]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conv || !$conv['page_id']) {
                throw new Exception("Conversation or Page ID not found. Please sync again.");
            }

            // 2. Get Page Access Token (Search by FB Page ID or IG Business ID)
            $stmt = $pdo->prepare("SELECT p.page_access_token FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE (p.page_id = ? OR p.ig_business_id = ?) AND a.user_id = ?");
            $stmt->execute([$conv['page_id'], $conv['page_id'], $userId]);
            $token = $stmt->fetchColumn();

            if (!$token) {
                throw new Exception("Page Access Token not found.");
            }

            // 3. Send via Graph API
            $url = "https://graph.facebook.com/v19.0/me/messages?access_token={$token}";
            $body = [
                'recipient' => ['id' => $conv['client_psid']],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE'
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resData = json_decode($result, true);

            if ($httpCode !== 200 || isset($resData['error'])) {
                throw new Exception("FB API Error: " . ($resData['error']['message'] ?? $result));
            }

            $metaId = $resData['message_id'] ?? null;

            $nowUtc = date('Y-m-d H:i:s');

            // 4. Save to DB (Success) - Using standard helper logic
            $stmt = $pdo->prepare("INSERT IGNORE INTO unified_messages (conversation_id, sender, message_text, meta_message_id, created_at) VALUES (?, 'page', ?, ?, ?)");
            $stmt->execute([$convId, $text, $metaId, $nowUtc]);
            $msgId = $pdo->lastInsertId() ?: null;

            if (!$msgId && $metaId) {
                $stmt = $pdo->prepare("SELECT id FROM unified_messages WHERE meta_message_id = ?");
                $stmt->execute([$metaId]);
                $msgId = $stmt->fetchColumn();
            }

            // 5. Update Conversation
            $stmt = $pdo->prepare("UPDATE unified_conversations SET last_message_text = ?, last_message_time = ? WHERE id = ?");
            $stmt->execute(["You: " . mb_substr($text, 0, 50), $nowUtc, $convId]);

            // 6. Trigger Pusher for other tabs
            $pusherData = [
                'conversation_id' => $convId,
                'message' => [
                    'id' => $msgId,
                    'sender' => 'page',
                    'message_text' => $text,
                    'created_at' => date('Y-m-d H:i:s'),
                    'meta_message_id' => $metaId
                ],
                'conversation' => [
                    'id' => $convId,
                    'last_message_text' => "You: " . mb_substr($text, 0, 50),
                    'last_message_time' => date('Y-m-d H:i:s')
                ]
            ];
            triggerPusherEvent($userId, 'new-message', $pusherData);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    // Log detailed error for debugging
    error_log("Smart Inbox Error: " . $errorMsg . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['success' => false, 'error' => $errorMsg]);
}
?>