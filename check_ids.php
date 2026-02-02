<?php
require_once 'includes/db_config.php';
$res = $pdo->query("SELECT DISTINCT page_id FROM fb_leads");
print_r($res->fetchAll(PDO::FETCH_ASSOC));
