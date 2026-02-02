<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$page_db_id = $_POST['page_id'] ?? 0;

if (!$page_db_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing page_id']);
    exit;
}

// Verify Page Ownership
$stmt = $pdo->prepare("SELECT p.*, a.access_token as user_token FROM fb_pages p 
                       JOIN fb_accounts a ON p.account_id = a.id 
                       WHERE p.id = ? AND a.user_id = ?");
$stmt->execute([$page_db_id, $user_id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    echo json_encode(['status' => 'error', 'message' => 'Page not found']);
    exit;
}

$fb = new FacebookAPI();
$page_access_token = $page['page_access_token'];

if ($action === 'fetch_posts') {
    $res = $fb->getPageFeed($page['page_id'], $page_access_token, 50);
    if (isset($res['data'])) {
        echo json_encode(['status' => 'ok', 'data' => $res['data']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $res['error']['message'] ?? 'Failed to fetch posts']);
    }
} elseif ($action === 'extract_comments') {
    $post_ids = $_POST['post_ids'] ?? [];
    if (empty($post_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No posts selected']);
        exit;
    }

    $new_leads = 0;
    $updated_leads = 0;
    $total_found = 0;
    $log_file = __DIR__ . '/extraction_debug.log';

    file_put_contents($log_file, "--- NEW EXTRACTION RUN: " . date('Y-m-d H:i:s') . " ---\n");

    // Insert or Update Lead based on Page, User, Source, and Post
    $insertLead = $pdo->prepare("INSERT INTO fb_leads (page_id, fb_user_id, fb_user_name, lead_source, post_id, last_comment, last_interaction) 
                                 VALUES (?, ?, ?, 'comment', ?, ?, NOW()) 
                                 ON DUPLICATE KEY UPDATE last_interaction = NOW(), fb_user_name = VALUES(fb_user_name), last_comment = VALUES(last_comment)");

    foreach ($post_ids as $post_id) {
        $after = '';
        $processed_in_post = 0;
        file_put_contents($log_file, "Processing Post: $post_id\n", FILE_APPEND);

        do {
            $comments = $fb->getPostComments($post_id, $page_access_token, 100, $after);

            // Normalize: if response is nested (from field expansion)
            if (isset($comments['comments']['data'])) {
                $comments = $comments['comments'];
            }

            // LOG RAW RESPONSE FOR DEBUGGING
            $count = isset($comments['data']) ? count($comments['data']) : 0;
            file_put_contents($log_file, "  [DEBUG] API Normalized Count: $count\n", FILE_APPEND);

            if (!isset($comments['data']) && isset($comments['error'])) {
                file_put_contents($log_file, "  [DEBUG] API RAW ERROR: " . json_encode($comments['error']) . "\n", FILE_APPEND);
            }

            if (isset($comments['data'])) {
                foreach ($comments['data'] as $comment) {
                    $from_id = $comment['from']['id'] ?? '';
                    $from_name = $comment['from']['name'] ?? '';
                    $comment_text = $comment['message'] ?? '';

                    // FALLBACK: If 'from' is missing in the list, try to fetch this specific comment ID directly
                    if (!$from_id && isset($comment['id'])) {
                        file_put_contents($log_file, "    [FALLBACK] Fetching comment details for ID: {$comment['id']}\n", FILE_APPEND);
                        $comment_detail = $fb->makeRequest($comment['id'], ['fields' => 'from{id,name},message'], $page_access_token);
                        $from_id = $comment_detail['from']['id'] ?? '';
                        $from_name = $comment_detail['from']['name'] ?? '';
                        $comment_text = $comment_detail['message'] ?? $comment_text;
                        if ($from_id) {
                            file_put_contents($log_file, "    [FALLBACK SUCCESS] Found user: $from_name ($from_id)\n", FILE_APPEND);
                        }
                    }

                    // Check if this is a sub-comment (nested)
                    if ($from_id && $from_id != $page['page_id']) {
                        $total_found++;
                        try {
                            // Check if THIS SPECIFIC lead (page+user+source+post) exists
                            $stmt_check = $pdo->prepare("SELECT id FROM fb_leads WHERE page_id = ? AND fb_user_id = ? AND lead_source = 'comment' AND post_id = ?");
                            $stmt_check->execute([$page['id'], $from_id, $post_id]);
                            $exists = $stmt_check->fetch();

                            $insertLead->execute([$page['id'], $from_id, $from_name, $post_id, $comment_text]);

                            if (!$exists) {
                                $new_leads++;
                                file_put_contents($log_file, "    [NEW COMMENT] $from_name ($from_id) - Msg: $comment_text\n", FILE_APPEND);
                            } else {
                                $updated_leads++;
                                file_put_contents($log_file, "    [UPDATED] $from_name ($from_id)\n", FILE_APPEND);
                            }
                        } catch (Exception $e) {
                            file_put_contents($log_file, "    [DB ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    } else {
                        $reason = (!$from_id) ? "No From ID (Even after fallback)" : "Page itself ($from_id)";
                        file_put_contents($log_file, "    [SKIPPING] $reason | Comment Data: " . json_encode($comment) . "\n", FILE_APPEND);
                    }
                }
                $after = $comments['paging']['cursors']['after'] ?? '';
            } else {
                $after = '';
            }
            $processed_in_post++;
            if ($processed_in_post > 50)
                break; // Hard limit 5000 comments
        } while ($after);
    }

    echo json_encode([
        'status' => 'ok',
        'count' => $new_leads,
        'updated' => $updated_leads,
        'total_found' => $total_found
    ]);
}
