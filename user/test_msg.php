<?php
require_once '../includes/functions.php';
require_once '../includes/facebook_api.php';

// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = $_POST['page_id'];
    $access_token = $_POST['access_token'];
    $psid = $_POST['psid'];
    $message = $_POST['message'];

    // FIX: Using explicit Page ID instead of 'me'
    $url = "https://graph.facebook.com/v18.0/" . $page_id . "/messages?access_token=" . $access_token;

    $payload = [
        'recipient' => ['id' => $psid],
        'message' => ['text' => $message],
        'messaging_type' => 'RESPONSE'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // SSL Debugging
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Try disabling SSL verification temporarily
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result .= "<h3>Test Results:</h3>";
    $result .= "<strong>Target URL:</strong> $url<br>"; // Debug URL
    $result .= "<strong>HTTP Code:</strong> $http_code<br>";

    if ($curl_error) {
        $result .= "<strong style='color:red'>CURL Error:</strong> $curl_error<br>";
    } else {
        $result .= "<strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook API Debugger</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #f0f2f5;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background: #1877f2;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #166fe5;
        }

        pre {
            background: #eee;
            padding: 10px;
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>🚀 API Connection Doctor</h2>
        <form method="POST">
            <label>Page ID (Explicit):</label>
            <input type="text" name="page_id" required placeholder="100064..."
                value="<?php echo $_POST['page_id'] ?? ''; ?>">

            <label>Page Access Token:</label>
            <input type="text" name="access_token" required placeholder="EAAG..."
                value="<?php echo $_POST['access_token'] ?? ''; ?>">

            <label>Recipient PSID:</label>
            <input type="text" name="psid" required placeholder="123456789..."
                value="<?php echo $_POST['psid'] ?? ''; ?>">

            <label>Message:</label>
            <textarea name="message" required>Test Message from Debugger</textarea>

            <button type="submit">Test Send</button>
        </form>

        <div style="margin-top: 20px;">
            <?php echo $result; ?>
        </div>
    </div>
</body></html>