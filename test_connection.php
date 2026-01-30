<?php
// test_connection.php
// Diagnostic tool to check connectivity to Evolution API

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration - Load directly or hardcode for testing
require_once __DIR__ . '/includes/db.php';
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_evolution_url'");
$evo_url = $stmt->fetchColumn();

// If not in DB, use hardcoded (fallback)
if (!$evo_url) {
    $evo_url = 'https://api.n8nmarketation.online';
}
$evo_url = rtrim($evo_url, '/');

echo "<h1>Connectivity Test</h1>";
echo "<p><strong>Target URL:</strong> $evo_url</p>";
echo "<p><strong>Server IP:</strong> " . $_SERVER['SERVER_ADDR'] . "</p>";
echo "<p><strong>Checking DNS Resolution...</strong></p>";

$host = parse_url($evo_url, PHP_URL_HOST);
$ip = gethostbyname($host);
echo "Resolved IP for $host: <strong>$ip</strong><br><br>";

if ($ip == $host) {
    echo "<span style='color:red'>DNS Resolution Failed! Server cannot resolve the hostname.</span><br>";
}

echo "<hr>";
echo "<h3>cURL Verbose Output:</h3>";

$ch = curl_init($evo_url); // Try root endpoint which usually returns a welcome message
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Enable Verbose output to see handshake details
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Common Fixes applied
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4
curl_setopt($ch, CURLOPT_USERAGENT, 'Marketation-Test-Agent/1.0');

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);

curl_close($ch);

// Output Verbose Log
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc'>" . htmlspecialchars($verboseLog) . "</pre>";

echo "<hr>";
if ($errno) {
    echo "<h2 style='color:red'>Connection Failed!</h2>";
    echo "<strong>Error No:</strong> $errno<br>";
    echo "<strong>Error Message:</strong> $error<br>";
} else {
    echo "<h2 style='color:green'>Connection Successful!</h2>";
    echo "<strong>HTTP Code:</strong> $http_code<br>";
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}
