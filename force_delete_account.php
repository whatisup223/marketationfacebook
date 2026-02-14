<?php
// force_delete_account.php
require_once 'includes/functions.php';
$pdo = getDB();

$account_id = 21; // The problematic account ID from your logs

try {
    // 1. Delete associated pages
    $stmt = $pdo->prepare("DELETE FROM fb_pages WHERE account_id = ?");
    $stmt->execute([$account_id]);
    $pagesDeleted = $stmt->rowCount();

    // 2. Delete the account itself
    $stmt = $pdo->prepare("DELETE FROM fb_accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    $accountsDeleted = $stmt->rowCount();

    if ($accountsDeleted > 0) {
        echo "<h1>Success!</h1>";
        echo "<p>Account ID $account_id has been forcefully deleted.</p>";
        echo "<p>Pages deleted: $pagesDeleted</p>";
        echo "<hr>";
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>Go back to your dashboard.</li>";
        echo "<li>Click 'Link New Account'.</li>";
        echo "<li>Approve all permissions again.</li>";
        echo "<li>This should generate a fresh Account ID (e.g. 22 or higher).</li>";
        echo "</ol>";
    } else {
        echo "<h1>Info</h1>";
        echo "<p>Account ID $account_id was not found in the database. It might have been deleted already.</p>";
    }

} catch (PDOException $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>