<?php
// test_send_msg.php - Debug Message Sending
require_once 'includes/db.php';

// 1. Fetch Settings
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_evolution_url'");
$evo_url = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_evolution_api_key'");
$api_key = $stmt->fetchColumn();

echo "<h1>Evolution API Sender Debug</h1>";
echo "<p><strong>URL:</strong> $evo_url</p>";
echo "<p><strong>API Key:</strong> $api_key</p>";

// 2. Fetch First Connected Account
$acc_stmt = $pdo->query("SELECT * FROM wa_accounts WHERE status = 'connected' LIMIT 1");
$account = $acc_stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("<h2 style='color:red'>No Connected Accounts Found!</h2>");
}

$instance_name = $account['instance_name'];
echo "<p><strong>Instance Name:</strong> $instance_name (ID: {$account['id']})</p>";

// 3. Prepare Test Message
$endpoint = "$evo_url/message/sendText/$instance_name";
echo "<p><strong>Full Endpoint:</strong> $endpoint</p>";

$data = [
    'number' => '201022035190', // Your number from logs
    'text' => 'Test Message from Debug Script 🚀'
];

echo "<h3>Sending Request...</h3>";

// 4. Send with Verbose Output
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Try Standard Header
$headers = [
    'Content-Type: application/json',
    'apikey: ' . trim($api_key)
];

echo "<pre>Headers being sent:\n";
print_r($headers);
echo "</pre>";

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "<h3>Result:</h3>";
echo "HTTP Code: <strong>$http_code</strong><br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
echo "<h3>Verbose Log:</h3>";
echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";

if ($http_code == 401) {
    echo "<h2 style='color:red'>Still 401 Unauthorized?</h2>";
    echo "<ul>";
    echo "<li>Check if 'apikey' header is correct for this version of Evolution.</li>";
    echo "<li>Check if Global Key matches what is in Dokploy absolutely.</li>";
    echo "<li>Try creating a new API Key in Evolution global settings if possible.</li>";
    echo "</ul>";
}
