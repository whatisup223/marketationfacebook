<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    // Save exchange data to session so user can complete it after login
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $_SESSION['pending_exchange'] = [
            'currencySend' => $_POST['currencySend'] ?? null,
            'currencyReceive' => $_POST['currencyReceive'] ?? null,
            'amountSend' => $_POST['amountSend'] ?? null,
            'paymentMethodSend' => $_POST['paymentMethodSend'] ?? null,
            'paymentMethodReceive' => $_POST['paymentMethodReceive'] ?? null,
        ];
    }
    $_SESSION['redirect_after_login'] = '/user/process_exchange.php';
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" || !empty($_SESSION['pending_exchange'])) {
    $user_id = $_SESSION['user_id'];

    // Check if we're restoring from session (after login)
    if (!empty($_SESSION['pending_exchange']) && $_SERVER["REQUEST_METHOD"] != "POST") {
        $_POST = $_SESSION['pending_exchange'];
        unset($_SESSION['pending_exchange']); // Clear it after restoring
    }

    $currency_from = $_POST['currencySend'];
    $currency_to = $_POST['currencyReceive'];
    $amount_send = $_POST['amountSend'];
    // $amount_receive calculated on server side for security

    $pdo = getDB();

    // Get Rates
    $stmt = $pdo->prepare("SELECT * FROM currencies WHERE id IN (?, ?)");
    $stmt->execute([$currency_from, $currency_to]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currMap = [];
    foreach ($currencies as $c) {
        $currMap[$c['id']] = $c;
    }

    // Server Side Calculation
    $from = $currMap[$currency_from];
    $to = $currMap[$currency_to];

    // Logic: Send (Buy Rate) -> Base -> Receive (Sell Rate)
    // Rate IS: Units per Base

    $rate_buy_from = $from['exchange_rate_buy']; // Platform Buys FromUser
    $rate_sell_to = $to['exchange_rate_sell'];   // Platform Sells ToUser

    // Avoid division by zero
    if ($rate_buy_from == 0)
        $rate_buy_from = 1;

    $baseValue = $amount_send / $rate_buy_from;
    $amount_receive = $baseValue * $rate_sell_to;

    $exchange_rate = ($amount_send > 0) ? ($amount_receive / $amount_send) : 0; // Effective Rate

    // Get payment methods (optional - convert empty strings to NULL)
    $payment_method_send = !empty($_POST['paymentMethodSend']) ? $_POST['paymentMethodSend'] : null;
    $payment_method_receive = !empty($_POST['paymentMethodReceive']) ? $_POST['paymentMethodReceive'] : null;

    if (!$payment_method_send || !$payment_method_receive) {
        die("Error: Please select payment methods for both sending and receiving.");
    }

    // Check if same payment method is selected for both send and receive
    if ($payment_method_send === $payment_method_receive) {
        die("Error: You cannot use the same payment method for sending and receiving. Please select a different payment method.");
    }

    // Validate min/max amounts for payment methods
    $stmt = $pdo->prepare("SELECT min_amount, max_amount, name FROM payment_methods WHERE id = ?");

    // Check send payment method limits
    $stmt->execute([$payment_method_send]);
    $pmSend = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pmSend) {
        if ($amount_send < $pmSend['min_amount']) {
            die("Error: Send amount is below the minimum allowed (" . $pmSend['min_amount'] . " " . $from['symbol'] . ") for " . $pmSend['name']);
        }
        if ($amount_send > $pmSend['max_amount']) {
            die("Error: Send amount exceeds the maximum allowed (" . $pmSend['max_amount'] . " " . $from['symbol'] . ") for " . $pmSend['name']);
        }
    }

    // Check receive payment method limits
    $stmt->execute([$payment_method_receive]);
    $pmReceive = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pmReceive) {
        if ($amount_receive < $pmReceive['min_amount']) {
            die("Error: Receive amount is below the minimum allowed (" . $pmReceive['min_amount'] . " " . $to['symbol'] . ") for " . $pmReceive['name']);
        }
        if ($amount_receive > $pmReceive['max_amount']) {
            die("Error: Receive amount exceeds the maximum allowed (" . $pmReceive['max_amount'] . " " . $to['symbol'] . ") for " . $pmReceive['name']);
        }
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO exchanges (user_id, currency_from_id, currency_to_id, payment_method_send_id, payment_method_receive_id, amount_send, amount_receive, exchange_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    if ($stmt->execute([$user_id, $currency_from, $currency_to, $payment_method_send, $payment_method_receive, $amount_send, $amount_receive, $exchange_rate])) {
        $id = $pdo->lastInsertId();

        // --- Email Notifications ---
        require_once __DIR__ . '/../includes/MailService.php';
        require_once __DIR__ . '/../includes/email_templates.php';

        // Get User Info for Email
        $stmtUser = $pdo->prepare("SELECT name, email, preferences FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Get Currency Codes
        $currSendCode = $from['code'] ?? 'Units';
        $currReceiveCode = $to['code'] ?? 'Units';

        // Get Payment Method Names
        $paymentMethodSendName = '';
        $paymentMethodReceiveName = '';

        if ($payment_method_send) {
            $stmtPM = $pdo->prepare("SELECT name, name_ar FROM payment_methods WHERE id = ?");
            $stmtPM->execute([$payment_method_send]);
            $pmSend = $stmtPM->fetch(PDO::FETCH_ASSOC);
            $paymentMethodSendName = $pmSend ? ($pmSend['name_ar'] ?: $pmSend['name']) : '';
        }

        if ($payment_method_receive) {
            $stmtPM = $pdo->prepare("SELECT name, name_ar FROM payment_methods WHERE id = ?");
            $stmtPM->execute([$payment_method_receive]);
            $pmReceive = $stmtPM->fetch(PDO::FETCH_ASSOC);
            $paymentMethodReceiveName = $pmReceive ? ($pmReceive['name_ar'] ?: $pmReceive['name']) : '';
        }

        // 1. Notify Admin (if enabled)
        if (getSetting('notify_new_exchange_admin', '1') == '1') {
            $adminEmail = getSetting('contact_email');
            if ($adminEmail) {
                $data = [
                    'id' => $id,
                    'name' => $userInfo['name'],
                    'amount_send' => $amount_send,
                    'curr_send' => $currSendCode,
                    'amount_receive' => $amount_receive,
                    'curr_receive' => $currReceiveCode,
                    'payment_method_send' => $paymentMethodSendName,
                    'payment_method_receive' => $paymentMethodReceiveName,
                    'admin_url' => getSetting('site_url') . '/admin/exchanges.php'
                ];
                $tpl = getEmailTemplate('new_exchange_admin', $data);
                sendEmail($adminEmail, $tpl['subject'], $tpl['body']);
            }
        }

        // --- Internal Notification to Admins ---
        notifyAdmins(
            'new_exchange_title',
            json_encode(['key' => 'new_exchange_msg', 'params' => [$id]]),
            'admin/exchanges.php'
        );
        // ---------------------------------------


        // 2. Notify User (if enabled by Admin AND User)
        if (getSetting('notify_new_exchange_user', '1') == '1') {
            // Check User Preference
            $user_prefs = json_decode($userInfo['preferences'] ?? '{}', true);
            if ($user_prefs['notify_new_exchange'] ?? true) { // Default true if not set
                $data = [
                    'id' => $id,
                    'name' => $userInfo['name'],
                    'amount_send' => $amount_send,
                    'curr_send' => $currSendCode,
                    'amount_receive' => $amount_receive,
                    'curr_receive' => $currReceiveCode,
                    'payment_method_send' => $paymentMethodSendName,
                    'payment_method_receive' => $paymentMethodReceiveName,
                    'view_url' => getSetting('site_url') . "/user/view_exchange.php?id=$id"
                ];
                $tpl = getEmailTemplate('new_exchange_user', $data);
                sendEmail($userInfo['email'], $tpl['subject'], $tpl['body']);
            }
        }
        // ---------------------------

        header("Location: view_exchange.php?id=$id");
        exit;
    } else {
        die("Error creating exchange");
    }
} else {
    header("Location: ../index.php");
    exit;
}
