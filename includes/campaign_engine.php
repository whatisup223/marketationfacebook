<?php
// includes/campaign_engine.php
// This script is designed to run in the background (CLI mode).
// It checks for RUNNING campaigns and processes their queue respecting the interval.

// Ensure CLI or protected access
if (php_sapi_name() !== 'cli' && !isset($_GET['secret_key'])) {
    die('Access Denied');
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/facebook_api.php';

// Disable time limit
set_time_limit(0);

// Set Timezone to Cairo (User's Local Time)
date_default_timezone_set('Africa/Cairo');

// Ensure only one instance runs using MySQL Lock
// This is much more reliable than flock on Windows/Hosting
require_once __DIR__ . '/db_config.php';
$pdo = $GLOBALS['pdo'];
$pdo->exec("SET time_zone = '+02:00'"); // Sync DB time with User Request

try {
    $stmt = $pdo->query("SELECT GET_LOCK('marketation_engine', 0)");
    if ($stmt->fetchColumn() != 1) {
        die("Another instance is holding the lock. Exiting.\n");
    }
} catch (PDOException $e) {
    die("DB Connection failed for lock: " . $e->getMessage());
}

function logEngine($msg)
{
    $txt = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    echo $txt;
    file_put_contents(__DIR__ . '/engine.log', $txt, FILE_APPEND);
    // Also log to fb_debug for consolidated view
    // file_put_contents(__DIR__ . '/fb_debug.log', $txt, FILE_APPEND); 
}

logEngine("Engine Started (Lock Acquired).");

while (true) {
    try {
        // 0. Auto-Launch Scheduled Campaigns
        // Use PHP time (Cairo) to be 100% sure of the comparison reference
        $currentCairoTime = date('Y-m-d H:i:s');

        // Find campaigns that are scheduled and ready
        // We select them first to log them, then update
        $toLaunch = $pdo->prepare("SELECT id, name, scheduled_at FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= ?");
        $toLaunch->execute([$currentCairoTime]);
        $launchList = $toLaunch->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($launchList)) {
            foreach ($launchList as $lc) {
                logEngine("Auto-launching Scheduled Campaign #{$lc['id']} ('{$lc['name']}'). Scheduled: {$lc['scheduled_at']} <= Current: $currentCairoTime");
                $pdo->prepare("UPDATE campaigns SET status = 'running' WHERE id = ?")->execute([$lc['id']]);
            }
        }

        // 1. Find RUNNING campaigns
        $stmt = $pdo->query("SELECT * FROM campaigns WHERE status = 'running'");
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($campaigns)) {
            // No running campaigns, sleep a bit longer
            sleep(5);
            continue;
        }

        foreach ($campaigns as $campaign) {
            $campaign_id = $campaign['id'];
            $interval = (int) ($campaign['waiting_interval'] ?? 30);

            // Sync Time with DB to avoid Timezone issues
            $now = $pdo->query("SELECT UNIX_TIMESTAMP(NOW())")->fetchColumn();

            // Check timing (Throttle)
            // Fetch the last SENT item's timestamp from DB
            $lastSentStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(sent_at) FROM campaign_queue WHERE campaign_id = ? AND status = 'sent' ORDER BY sent_at DESC LIMIT 1");
            $lastSentStmt->execute([$campaign_id]);
            $lastSentTs = $lastSentStmt->fetchColumn();

            if ($lastSentTs) {
                $secondsSinceLast = $now - $lastSentTs;
                $waitTime = $interval - $secondsSinceLast;

                if ($waitTime > 0) {
                    // Not enough time passed
                    // logEngine("Campaign #$campaign_id waiting... (${waitTime}s left)");
                    continue;
                }
            }

            // Get next pending item
            $qStmt = $pdo->prepare("SELECT * FROM campaign_queue WHERE campaign_id = ? AND status = 'pending' ORDER BY id ASC LIMIT 1");
            $qStmt->execute([$campaign_id]);
            $item = $qStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                // No more pending items -> Campaign Finished?
                // Double check if any actual pending exist? (Maybe all failed/sent)
                // If really empty, mark completed.

                $checkPending = $pdo->prepare("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ? AND status = 'pending'");
                $checkPending->execute([$campaign_id]);
                if ($checkPending->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
                    logEngine("Campaign #$campaign_id Completed.");
                }
                continue;
            }

            // ATOMIC LOCK: Try to claim this item
            // Update status to 'processing' and set sent_at to NOW() to mark it as claimed and start of processing
            $claimStmt = $pdo->prepare("UPDATE campaign_queue SET status = 'processing', sent_at = NOW() WHERE id = ? AND status = 'pending'");
            $claimStmt->execute([$item['id']]);

            if ($claimStmt->rowCount() === 0) {
                // Another worker took it or it's not pending anymore
                // Skip this item and move to the next campaign or loop iteration
                continue;
            }

            logEngine("Processing Queue Item #{$item['id']} for Campaign #$campaign_id");

            // --- SENDING LOGIC (Copied/Refactored from send_process.php) ---
            // We need lead info and page info.
            // We can do a JOIN query or just fetch now.
            $infoStmt = $pdo->prepare("
                SELECT l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id, c.message_text, c.image_url 
                FROM campaign_queue q
                JOIN campaigns c ON q.campaign_id = c.id
                JOIN fb_leads l ON q.lead_id = l.id
                JOIN fb_pages p ON c.page_id = p.id
                WHERE q.id = ?
            ");
            $infoStmt->execute([$item['id']]);
            $fullInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

            if ($fullInfo) {
                $message = $fullInfo['message_text'];
                $message = str_replace('{{name}}', $fullInfo['fb_user_name'], $message);

                $fb = new FacebookAPI();
                $response = $fb->sendMessage($fullInfo['fb_page_id'], $fullInfo['page_access_token'], $fullInfo['fb_user_id'], $message, $fullInfo['image_url']);

                if (isset($response['error'])) {
                    $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = ? WHERE id = ?")->execute([$response['error'], $item['id']]);
                    $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");
                    logEngine("Failed to send Item #{$item['id']}: " . $response['error']);
                } else {
                    // Update to sent, and update sent_at again to reflect actual completion time
                    $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$item['id']]);
                    $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");
                    logEngine("Successfully sent Item #{$item['id']}");
                }
            } else {
                // Data missing for the item, mark as failed
                $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = 'Missing lead, campaign, or page data' WHERE id = ?")->execute([$item['id']]);
                $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");
                logEngine("Failed to send Item #{$item['id']}: Missing data.");
            }
            // -----------------------------------------------------------------
        }

        // Sleep to prevent CPU hogging, but short enough to be responsive
        sleep(2);

    } catch (Exception $e) {
        logEngine("CRITICAL ERROR: " . $e->getMessage());
        sleep(10);
    }
}
