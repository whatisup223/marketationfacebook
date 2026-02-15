<?php
require_once '../functions.php';
require_once '../SmartInboxEngine.php';

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
            $stmt = $pdo->prepare("SELECT * FROM unified_conversations WHERE user_id = ? ORDER BY last_message_time DESC LIMIT 20");
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
            $engine = new SmartInboxEngine($pdo);
            $result = $engine->analyzeCreateReply($convId, $userId);

            echo json_encode(['success' => true, 'analysis' => $result]);
            break;

        case 'send_message':
            // Placeholder logic to "send" a message (save to DB)
            $input = json_decode(file_get_contents('php://input'), true);
            $convId = $input['conversation_id'];
            $text = $input['message_text'];

            // Save to DB as 'page' sender
            $stmt = $pdo->prepare("INSERT INTO unified_messages (conversation_id, sender, message_text) VALUES (?, 'page', ?)");
            $stmt->execute([$convId, $text]);

            // Update conversation last message
            $stmt = $pdo->prepare("UPDATE unified_conversations SET last_message_text = ?, last_message_time = NOW() WHERE id = ?");
            $stmt->execute(["You: " . substr($text, 0, 50), $convId]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>