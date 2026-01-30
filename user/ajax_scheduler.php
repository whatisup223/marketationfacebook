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

if ($action_get === 'sync') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $post = $stmt->fetch();

    if (!$post || !$post['fb_post_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Post or FB ID not found']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
    $stmt->execute([$post['page_id']]);
    $token = $stmt->fetchColumn();

    if (!$token) {
        echo json_encode(['status' => 'error', 'message' => 'Token not found']);
        exit;
    }

    // Check FB Status
    $fb_res = $fb->getObject($post['fb_post_id'], $token);

    if (isset($fb_res['error'])) {
        $err_code = $fb_res['error']['code'] ?? 0;
        if ($err_code == 100 || $err_code == 10 || $err_code == 21) {
            // Post probably deleted from FB
            $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'failed', error_message = 'Deleted from Facebook' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'new_state' => 'deleted_from_fb']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $fb_res['error']['message']]);
        }
    } else {
        // Post is alive
        $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'success' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'new_state' => 'alive']);
    }
    exit;
}

if ($action_get === 'delete') {
    $id = $_GET['id'] ?? 0;
    $delete_from_fb = ($_GET['from_fb'] ?? '0') === '1';

    $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['status' => 'error', 'message' => 'Post not found']);
        exit;
    }

    if ($delete_from_fb && $post['fb_post_id']) {
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
        $stmt->execute([$post['page_id']]);
        $token = $stmt->fetchColumn();
        if ($token) {
            $fb->deleteScheduledPost($post['fb_post_id'], $token);
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

        // Handle File Upload (Support Multiple)
        $local_paths = [];
        $images_to_fb = [];

        // If simple URL provided
        if ($media_url) {
            $local_paths[] = $media_url;
            $images_to_fb[] = $media_url;
        }

        // Handle uploaded files
        if (isset($_FILES['media_file'])) {
            $upload_dir = __DIR__ . '/../uploads/scheduler/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            // Check if multiple files
            if (is_array($_FILES['media_file']['name'])) {
                $count = count($_FILES['media_file']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['media_file']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['media_file']['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'post_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                        $target = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['media_file']['tmp_name'][$i], $target)) {
                            $images_to_fb[] = new CURLFile($target);
                            $local_paths[] = '../uploads/scheduler/' . $filename;
                        }
                    }
                }
            } else {
                // Single file
                if ($_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
                    $filename = 'post_' . time() . '_' . uniqid() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $target)) {
                        $images_to_fb[] = new CURLFile($target);
                        $local_paths[] = '../uploads/scheduler/' . $filename;
                    }
                }
            }
        }

        // Determine what to pass to FB
        // If > 1 image, we probably need an Album logic, but current FB API class expects single $image.
        // For PROPER support, we'd need to update FB Class.
        // For now, if > 1, pass the array and let FB Class deal with it (or assume first one if not supported yet).

        $image_to_pass = (count($images_to_fb) > 1) ? $images_to_fb : ($images_to_fb[0] ?? null);
        $final_local_path = (count($local_paths) > 1) ? json_encode($local_paths) : ($local_paths[0] ?? '');

        if ($action_post === 'edit' && $id) {
            // Check if media changed or post_type changed
            $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $old_post = $stmt->fetch();

            if (!$old_post)
                throw new Exception("Original post not found.");

            // Determine if new media was uploaded
            $new_media_uploaded = (!empty($images_to_fb));

            // If no new media uploaded, keep the old media
            if (!$new_media_uploaded && !$media_url) {
                $final_local_path = $old_post['media_url'];
                $image_to_pass = $old_post['media_url'];

                // If old valid JSON, decode it? For now assume handled by FB API or is single string
                // Ideally we should decode here if we want to work with array, but FB API takes URL string too.
            }

            // Check if media actually changed
            $media_changed = ($old_post['media_url'] !== $final_local_path);

            if ($media_changed || $old_post['post_type'] !== $post_type) {
                // DELETE OLD FROM FB
                if ($old_post['fb_post_id']) {
                    $fb->deleteScheduledPost($old_post['fb_post_id'], $token);
                }
                // CREATE NEW ON FB
                $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);
                if (isset($fb_res['id'])) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET post_type = ?, content = ?, media_url = ?, scheduled_at = ?, fb_post_id = ? WHERE id = ?");
                    $stmt->execute([$post_type, $content, $final_local_path, date('Y-m-d H:i:s', $timestamp), $fb_res['id'], $id]);
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
                    $err_msg = $fb_res['error']['message'] ?? 'Failed to update on Facebook';
                    $err_code = $fb_res['error']['code'] ?? 0;

                    // Check if it's a phantom post
                    $is_phantom = ($err_code == 100 || $err_code == 10 || $err_code == 21 ||
                        strpos($err_msg, 'does not exist') !== false);

                    if ($is_phantom) {
                        // Delete locally since it's gone from FB
                        $stmt = $pdo->prepare("DELETE FROM fb_scheduled_posts WHERE id = ?");
                        $stmt->execute([$id]);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'هذا المنشور تم حذفه بالفعل من فيسبوك، لذا قمنا بإزالته من القائمة لديك.',
                            'is_phantom' => true
                        ]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => $err_msg, 'debug' => $fb_res]);
                    }
                }
            }
        } else {
            // NEW POST
            $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);
            if (isset($fb_res['id'])) {
                $stmt = $pdo->prepare("INSERT INTO fb_scheduled_posts (user_id, page_id, post_type, content, media_url, scheduled_at, fb_post_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $page_id, $post_type, $content, $final_local_path, date('Y-m-d H:i:s', $timestamp), $fb_res['id']]);
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
