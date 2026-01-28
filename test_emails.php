<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/MailService.php';
require_once __DIR__ . '/includes/email_templates.php';

$test_email = 'whatisup223@gmail.com';
$site_url = getSetting('site_url');

echo "Sending MULTI-LANGUAGE test emails to: $test_email ... <br><hr>";

$languages = ['ar', 'en'];

foreach ($languages as $lang) {
    echo "<h3>Testing Language: " . strtoupper($lang) . "</h3>";

    // 1. Welcome User
    $data_welcome = ['name' => "Test User ($lang)", 'login_url' => $site_url . '/login.php'];
    $tpl = getEmailTemplate('welcome_user', $data_welcome, $lang);
    if (sendEmail($test_email, $tpl['subject'], $tpl['body'])) {
        echo "✅ Sent Welcome User ($lang)<br>";
    } else {
        echo "❌ Failed Welcome User ($lang)<br>";
    }

    // 2. Ticket Status Update
    $data_status = [
        'name' => "Test User ($lang)",
        'ticket_id' => ($lang == 'ar' ? '1001' : '1002'),
        'ticket_subject' => 'Login Issue',
        'status' => 'solved',
        'view_url' => $site_url . '/user/view_ticket.php?id=1234'
    ];
    $tpl = getEmailTemplate('ticket_status_update', $data_status, $lang);
    if (sendEmail($test_email, $tpl['subject'], $tpl['body'])) {
        echo "✅ Sent Ticket Update ($lang)<br>";
    } else {
        echo "❌ Failed Ticket Update ($lang)<br>";
    }

    // 3. Test Email (Generic)
    $tpl = getEmailTemplate('test_email', [], $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent Generic Test ($lang)<br>";

    echo "<hr>";
}

echo "Done testing.";
