<?php
/**
 * Quick Fix: Add default anger keywords to all pages
 */
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// Default Arabic anger keywords
$defaultAngerKeywords = 'غبي,فاشل,سيء,وسخ,قرف,زبالة,حقير,كلب,حمار,غلط,خطأ,مش كويس,وحش,بضان';

try {
    // Update all pages that have empty anger keywords
    $stmt = $pdo->prepare("UPDATE fb_pages SET bot_anger_keywords = ? WHERE bot_anger_keywords IS NULL OR bot_anger_keywords = ''");
    $stmt->execute([$defaultAngerKeywords]);

    $affected = $stmt->rowCount();

    echo "✅ Updated $affected pages with default anger keywords!\n";
    echo "Keywords: $defaultAngerKeywords\n\n";
    echo "Now the handover system will detect anger automatically!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
