<?php
// test_connection.php
// Diagnostic tool to check connectivity to Evolution API (No DB Dependency)

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Hardcoded target URL
$evo_url = 'https://api.n8nmarketation.online';

echo "<h1>Connectivity Test (No DB)</h1>";
echo "<p><strong>Target URL:</strong> $evo_url</p>";
echo "<p><strong>Listening Server IP (Incoming):</strong> " . $_SERVER['SERVER_ADDR'] . "</p>";

// Check Real Outgoing IP
$ch_ip = curl_init('https://api.ipify.org');
curl_setopt($ch_ip, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_ip, CURLOPT_TIMEOUT, 5);
curl_setopt($ch_ip, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
$outgoing_ip = curl_exec($ch_ip);
curl_close($ch_ip);

if ($outgoing_ip) {
    echo "<p style='color:blue; font-size:1.2em'><strong>Real Outgoing IP (The one to whitelist):</strong> $outgoing_ip</p>";
} else {
    echo "<p style='color:orange'>Could not detect outgoing IP (Service unreachable)</p>";
}


echo "<hr><h3>System Network Tests (Requested by Support)</h3>";

// Function to safely execute command
function run_cmd($cmd)
{
    echo "<div style='background:#222; color:#0f0; padding:10px; margin-bottom:10px; border-radius:5px;'>";
    echo "<strong>$ " . htmlspecialchars($cmd) . "</strong><br><pre>";
    $output = shell_exec($cmd . " 2>&1"); // redirection to capture stderr
    echo htmlspecialchars($output ? $output : "No output (shell_exec might be disabled).");
    echo "</pre></div>";
}

// 1. Control Test: Connect to Google (to check general outbound connectivity)
run_cmd("curl -I -m 5 https://www.google.com");

// 2. Curl Verbose (System Level) - Reduced timeout
run_cmd("curl -vk -m 5 " . escapeshellarg($evo_url));

// 3. Traceroute (try standard linux path) - Reduced max hops
run_cmd("traceroute -n -m 10 -w 1 " . escapeshellarg("72.62.236.129"));

// 4. Ping - Reduced count
run_cmd("ping -c 2 -W 2 " . escapeshellarg("72.62.236.129"));

echo "<p><strong>Checking DNS Resolution...</strong></p>";

$host = parse_url($evo_url, PHP_URL_HOST);
$ip = gethostbyname($host);
echo "Resolved IP for $host: <strong>$ip</strong><br><br>";

if ($ip == $host) {
    echo "<span style='color:red'>DNS Resolution Failed! Server cannot resolve the hostname.</span><br>";
}

echo "<hr>";
echo "<h3>cURL Verbose Output:</h3>";

$ch = curl_init($evo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Fixes applied
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
