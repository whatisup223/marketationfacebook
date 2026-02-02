<?php
require_once 'includes/db_config.php';
require_once 'includes/facebook_api.php';

$stmt = $pdo->prepare("SELECT a.access_token FROM fb_accounts a LIMIT 1");
$stmt->execute();
$token = $stmt->fetchColumn();

$fb = new FacebookAPI();
$res = $fb->makeRequest('me/permissions', [], $token);
print_r($res);
