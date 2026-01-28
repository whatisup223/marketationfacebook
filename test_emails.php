<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/MailService.php';
require_once __DIR__ . '/includes/email_templates.php';

$test_email = 'whatisup223@gmail.com';
$site_url = getSetting('site_url');

echo "Sending FULL PREVIEW test emails to: $test_email ... <br><hr>";

$languages = ['ar', 'en'];

foreach ($languages as $lang) {
    echo "<h3>Testing Language: " . strtoupper($lang) . "</h3>";

    // 1. Welcome User
    $data_welcome = ['name' => "Ahmed ($lang)", 'login_url' => $site_url . '/login.php'];
    $tpl = getEmailTemplate('welcome_user', $data_welcome, $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent Welcome User ($lang)<br>";

    // 2. New User Admin
    $data_admin = [
        'name' => "Sara ($lang)",
        'username' => "sara_2026",
        'email' => "sara@example.com",
        'admin_url' => $site_url . '/admin/users.php'
    ];
    $tpl = getEmailTemplate('new_user_admin', $data_admin, $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent New User Admin ($lang)<br>";

    // 3. Forgot Password
    $data_forgot = ['reset_url' => $site_url . "/reset_password.php?token=xyz123"];
    $tpl = getEmailTemplate('forgot_password', $data_forgot, $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent Forgot Password ($lang)<br>";

    // 4. New Ticket Admin
    $data_ticket = [
        'ticket_id' => rand(5000, 9999),
        'user_name' => "Khaled ($lang)",
        'subject' => ($lang == 'ar' ? 'مشكلة في الدفع' : 'Payment Issue'),
        'priority' => 'High',
        'message' => ($lang == 'ar' ? 'لدي مشكلة في إتمام عملية الدفع عبر البطاقة' : 'I cannot complete the payment via card.'),
        'admin_url' => $site_url . '/admin/view_ticket.php'
    ];
    $tpl = getEmailTemplate('new_ticket_admin', $data_ticket, $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent New Ticket Admin ($lang)<br>";

    // 5. Ticket Status Update
    $data_status = [
        'name' => "Ahmed ($lang)",
        'ticket_id' => rand(1000, 4999),
        'ticket_subject' => ($lang == 'ar' ? 'استفسار عام' : 'General Inquiry'),
        'status' => 'answered',
        'view_url' => $site_url . '/user/view_ticket.php?id=1234'
    ];
    $tpl = getEmailTemplate('ticket_status_update', $data_status, $lang);
    sendEmail($test_email, $tpl['subject'], $tpl['body']);
    echo "✅ Sent Ticket Update ($lang)<br>";

    echo "<hr>";
}

echo "Done testing all templates.";
