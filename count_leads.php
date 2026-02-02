<?php
require_once 'includes/db_config.php';
$res = $pdo->query("SELECT post_id, COUNT(*) as count FROM fb_leads WHERE lead_source = 'comment' GROUP BY post_id");
print_r($res->fetchAll(PDO::FETCH_ASSOC));
