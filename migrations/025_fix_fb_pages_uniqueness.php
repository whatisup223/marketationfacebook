<?php
// Migration: 025_fix_fb_pages_uniqueness.php
// Description: Fix fb_pages uniqueness to allow same page for different accounts/users.

$pdo = getDB();

try {
    echo "Starting Migration 025: Fixing fb_pages uniqueness...<br>";

    // 1. Check current columns
    $stmt = $pdo->query("SHOW COLUMNS FROM fb_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Add 'account_id' if missing (it should be there based on code, but let's be safe)
    if (!in_array('account_id', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN account_id INT NOT NULL AFTER id");
        echo " - Added 'account_id' column.<br>";
    }

    // 3. Drop existing UNIQUE key on 'page_id' if it exists
    // We need to find the name of the index. In 000 it was named 'page_id'.
    try {
        $pdo->exec("ALTER TABLE fb_pages DROP INDEX page_id");
        echo " - Dropped old unique index 'page_id'.<br>";
    } catch (Exception $e) {
        echo " - Note: Could not drop index 'page_id' (maybe already dropped or named differently).<br>";
    }

    // 4. Also check for 'unique_page_id' which was mentioned in comments
    try {
        $pdo->exec("ALTER TABLE fb_pages DROP INDEX unique_page_id");
        echo " - Dropped old unique index 'unique_page_id'.<br>";
    } catch (Exception $e) {
        // Ignore
    }

    // 5. Add new composite UNIQUE key (account_id, page_id)
    // This allows the same page to exist once per account.
    $pdo->exec("ALTER TABLE fb_pages ADD UNIQUE KEY `unique_acc_page` (account_id, page_id)");
    echo " - Added new composite unique index (account_id, page_id).<br>";

    echo "<b>Migration 025 completed successfully!</b><br>";

} catch (PDOException $e) {
    echo "<span style='color:red'>Error in migration 025: " . $e->getMessage() . "</span><br>";
}
?>