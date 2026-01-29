<?php
// user/ajax_scheduler.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$fb = new FacebookAPI();

// 1. Handle GET Actions
$action_get = $_GET['action'] ?? '';
if ($action_get === 'delete') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['status' => 'error', 'message' => 'Post not found']);
        exit;
    }

    if ($post['fb_post_id']) {
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
        $stmt->execute([$post['page_id']]);
        $token = $stmt->fetchColumn();
        if ($token) {
            $fb_res = $fb->deleteScheduledPost($post['fb_post_id'], $token);
            if (isset($fb_res['error']) && strpos($fb_res['error']['message'], 'Object at this ID does not exist') === false) {
                echo json_encode(['status' => 'error', 'message' => 'Facebook error: ' . $fb_res['error']['message']]);
                exit;
            }
        }
    }

    $stmt = $pdo->prepare("DELETE FROM fb_scheduled_posts WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success']);
    exit;
}

// 2. Handle POST Actions (Save/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action_post = $_POST['action'] ?? 'save';
    $post_type = $_POST['post_type'] ?? 'feed';
    $page_id = $_POST['page_id'] ?? '';
    $content = $_POST['content'] ?? '';
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    $media_url = $_POST['media_url'] ?? '';
    $media_file = $_FILES['media_file'] ?? null;

    // Validation based on post_type
    $is_feed = ($post_type === 'feed');
    $is_story = ($post_type === 'story');
    $is_reel = ($post_type === 'reel');

    $has_page = !empty($page_id);
    $has_time = !empty($scheduled_at);
    $has_content = !empty($content);

    $error = false;
    if (!$has_page || !$has_time) {
        $error = "Missing required fields (Page or Time)";
    } elseif ($is_feed && !$has_content) {
        $error = "Post content is required for feed posts";
    } elseif ($is_reel && !$has_content) {
        $error = "Caption is required for Reels";
    }

    if ($error) {
        echo json_encode(['status' => 'error', 'message' => $error]);
        exit;
    }

    $timestamp = strtotime($scheduled_at);
    if ($timestamp < time() + 540) {
        echo json_encode(['status' => 'error', 'message' => 'Scheduled time must be at least 10 minutes in the future']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
        $stmt->execute([$page_id]);
        $token = $stmt->fetchColumn();

        if (!$token)
            throw new Exception("Page access token not found.");

        // Handle File Upload
        $local_path = $media_url;
        $image_to_fb = $media_url;
        if ($media_file && $media_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/scheduler/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $ext = pathinfo($media_file['name'], PATHINFO_EXTENSION);
            $filename = 'post_' . time() . '_' . uniqid() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($media_file['tmp_name'], $target)) {
                $image_to_fb = new CURLFile($target);
                $local_path = '../uploads/scheduler/' . $filename;
            }
        }

        if ($action_post === 'edit' && $id) {
            // Check if media changed or post_type changed
            $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $old_post = $stmt->fetch();

            if (!$old_post)
                throw new Exception("Original post not found.");

            // IF media changed, we MUST delete and re-create because FB doesn't allow media swap on existing post
            $media_changed = ($old_post['media_url'] !== $local_path);

            if ($media_changed || $old_post['post_type'] !== $post_type) {
                // DELETE OLD FROM FB
                if ($old_post['fb_post_id']) {
                    $fb->deleteScheduledPost($old_post['fb_post_id'], $token);
                }
                // CREATE NEW ON FB
                $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_fb, $timestamp, $post_type);
                if (isset($fb_res['id'])) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET post_type = ?, content = ?, media_url = ?, scheduled_at = ?, fb_post_id = ? WHERE id = ?");
                    $stmt->execute([$post_type, $content, $local_path, date('Y-m-d H:i:s', $timestamp), $fb_res['id'], $id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    $error_msg = $fb_res['error']['message'] ?? 'Facebook API Error';
                    echo json_encode(['status' => 'error', 'message' => $error_msg, 'debug' => $fb_res]);
                }
            } else {
                // NOT CHANGED MEDIA, just update fields
                $params = [
                    'message' => $content,
                    'caption' => $content,
                    'description' => $content,
                    'scheduled_publish_time' => (string) $timestamp
                ];
                $fb_res = $fb->makeRequest($old_post['fb_post_id'], $params, $token, 'POST');

                if (isset($fb_res['success']) && ($fb_res['success'] === true || $fb_res['success'] === 'true') || isset($fb_res['id'])) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET content = ?, scheduled_at = ? WHERE id = ?");
                    $stmt->execute([$content, date('Y-m-d H:i:s', $timestamp), $id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    $error = $fb_res['error']['message'] ?? 'Failed to update on Facebook';
                    echo json_encode(['status' => 'error', 'message' => $error, 'debug' => $fb_res]);
                }
            }
        } else {
            // NEW POST
            $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_fb, $timestamp, $post_type);
            if (isset($fb_res['id'])) {
                $stmt = $pdo->prepare("INSERT INTO fb_scheduled_posts (user_id, page_id, post_type, content, media_url, scheduled_at, fb_post_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $page_id, $post_type, $content, $local_path, date('Y-m-d H:i:s', $timestamp), $fb_res['id']]);
                echo json_encode(['status' => 'success', 'fb_id' => $fb_res['id']]);
            } else {
                $error_msg = $fb_res['error']['message'] ?? 'Facebook API Error';
                echo json_encode(['status' => 'error', 'message' => $error_msg, 'debug' => $fb_res]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
