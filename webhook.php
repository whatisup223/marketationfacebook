<?php
/**
 * Global Webhook Handler for Evolution API
 * This file receives all events (messages, status updates) from Evolution API instances.
 */

// Basic security checks (Optional: verify secret header if configured)
// header('Content-Type: application/json');

// Log incoming request (For debugging - Disable in production after testing)
$logFile = __DIR__ . '/webhook_log.txt';
$input = file_get_contents('php://input');

// Append logs
// file_put_contents($logFile, date('Y-m-d H:i:s') . " - Received: " . $input . "\n\n", FILE_APPEND);

$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Extract Instance ID/Name to identify the user
$instanceName = $data['instance'] ?? '';
$eventType = $data['event'] ?? ''; // e.g. messages.upsert, connection.update

// Required: Database connection to match instance with user and logic
require_once __DIR__ . '/includes/db.php';

// Route events
switch ($eventType) {
    case 'connection.update':
        handleConnectionUpdate($data, $pdo);
        break;

    case 'messages.upsert':
        handleMessagesUpsert($data, $pdo);
        break;

    // Add other cases like messages.update, etc.
}

echo json_encode(['status' => 'success']);

// ----------------------------------------------------------------------
// Handlers
// ----------------------------------------------------------------------

function handleConnectionUpdate($data, $pdo)
{
    $instance = $data['instance'];
    $state = $data['data']['state'] ?? ''; // open, close, connecting
    $status = 'disconnected';

    if ($state === 'open') {
        $status = 'connected';
    } elseif ($state === 'connecting') {
        $status = 'pairing';
    }

    // Update Database Status
    if ($instance && $state) {
        $stmt = $pdo->prepare("UPDATE wa_accounts SET status = ? WHERE instance_name = ?");
        $stmt->execute([$status, $instance]);
    }
}

function handleMessagesUpsert($data, $pdo)
{
    // Logic to handle incoming messages (e.g. Chatbot, Auto-reply)
    // $messages = $data['data']['messages'] ?? [];
    // ...
}
