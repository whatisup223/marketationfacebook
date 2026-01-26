<?php
// webhook.php - GitHub Webhook Handler
// Add this URL to GitHub: https://marketation.online/webhook.php

$secret = 'YOUR_WEBHOOK_SECRET'; // Change this!

// Verify GitHub signature
$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? '';
$payload = file_get_contents('php://input');
$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

// Parse payload
$data = json_decode($payload, true);

// Only on push to main branch
if ($data['ref'] === 'refs/heads/main') {
    // Pull latest code
    exec('cd ' . __DIR__ . ' && git pull origin main 2>&1', $output, $returnCode);

    // Run migrations
    if ($returnCode === 0) {
        $migrations = glob(__DIR__ . '/migrations/*.php');
        sort($migrations);
        foreach ($migrations as $migration) {
            include $migration;
        }
    }

    echo json_encode(['status' => 'success', 'output' => $output]);
} else {
    echo json_encode(['status' => 'ignored', 'ref' => $data['ref']]);
}
