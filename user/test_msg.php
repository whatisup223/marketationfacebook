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

    // FIX: Using explicit Page ID
    $url = "https://graph.facebook.com/v18.0/" . $page_id . "/messages?access_token=" . $access_token;

    $result .= "<h3>Test Results for Page ID: $page_id</h3>";

    // --- ATTEMPT 1: Minimal Payload (Text Only) ---
    $result .= "<h4>Attempt 1: Minimal Payload (No messaging_type)</h4>";
    $payload1 = [
        'recipient' => ['id' => $psid],
        'message' => ['text' => $message]
    ];
    $result .= executeCurl($url, $payload1);

    // --- ATTEMPT 2: Standard Response ---
    $result .= "<h4>Attempt 2: Standard RESPONSE</h4>";
    $payload2 = [
        'recipient' => ['id' => $psid],
        'message' => ['text' => $message],
        'messaging_type' => 'RESPONSE'
    ];
    $result .= executeCurl($url, $payload2);

    // --- ATTEMPT 3: Message Tag (Post Purchase) ---
    $result .= "<h4>Attempt 3: MESSAGE_TAG</h4>";
    $payload3 = [
        'recipient' => ['id' => $psid],
        'message' => ['text' => $message],
        'messaging_type' => 'MESSAGE_TAG',
        'tag' => 'POST_PURCHASE_UPDATE'
    ];
    $result .= executeCurl($url, $payload3);
}

function executeCurl($url, $payload)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // SSL Debugging
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $res = "<strong>HTTP:</strong> $http_code | ";
    if ($curl_error) {
        $res .= "<span style='color:red'>CURL Error: $curl_error</span><br>";
    } else {
        $res .= "<strong>Response:</strong> " . htmlspecialchars($response) . "<br>";
    }
    return $res . "<hr>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook API Debugger (Multi-Mode)</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #f0f2f5;
        }

        .container {
            max-width: 800px;
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
            white-space: pre-wrap;
        }

        h4 {
            margin-top: 5px;
            margin-bottom: 5px;
            color: #1877f2;
        }

        hr {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>🚀 API Diagnostics (3 Attempts)</h2>
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

            <button type="submit">Start Diagnostics</button>
        </form>

        <div style="margin-top: 20px;">
            <?php echo $result; ?>
        </div>
    </div>
</body>

</html>