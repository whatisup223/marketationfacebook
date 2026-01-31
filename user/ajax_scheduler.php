<?php
// user/ajax_scheduler.php
error_reporting(0);
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

if ($action_get === 'list') {
    $stmt = $pdo->prepare("SELECT s.*, p.page_name 
    FROM fb_scheduled_posts s 
    LEFT JOIN (SELECT page_id, page_name FROM fb_pages GROUP BY page_id) p ON s.page_id = p.page_id 
    WHERE s.user_id = ? 
    ORDER BY s.scheduled_at DESC");
    $stmt->execute([$user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'posts' => $posts]);
    exit;
}

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
    $fb_res = $fb->getObject($post['fb_post_id'], $token, ['message', 'attachments']);

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
        // Post is alive - FETCH CURRENT CONTENT
        $new_content = $fb_res['message'] ?? ($fb_res['description'] ?? ($fb_res['caption'] ?? $post['content']));

        // --- MEDIA SYNC ---
        $new_media_url = $post['media_url']; // Default to current
        if (isset($fb_res['attachments']['data'][0])) {
            $attachment = $fb_res['attachments']['data'][0];
            $media_items = [];

            // If it's an album (multiple photos)
            if ($attachment['type'] === 'album' && isset($attachment['subattachments']['data'])) {
                foreach ($attachment['subattachments']['data'] as $sub) {
                    if (isset($sub['media']['image']['src'])) {
                        $media_items[] = $sub['media']['image']['src'];
                    }
                }
            }
            // If it's a single image/video that has a source
            elseif (isset($attachment['media']['image']['src'])) {
                $media_items[] = $attachment['media']['image']['src'];
            }

            if (!empty($media_items)) {
                if (count($media_items) === 1) {
                    $new_media_url = $media_items[0];
                } else {
                    $new_media_url = json_encode($media_items);
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'success', content = ?, media_url = ? WHERE id = ?");
        $stmt->execute([$new_content, $new_media_url, $id]);

        echo json_encode([
            'status' => 'success',
            'new_state' => 'alive',
            'content_updated' => ($new_content !== $post['content']),
            'media_updated' => ($new_media_url !== $post['media_url'])
        ]);
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

    echo json_encode(['status' => 'success']);
    exit;
}

if ($action_get === 'delete_bulk') {
    $ids_str = $_GET['ids'] ?? '';
    $from_fb = ($_GET['from_fb'] ?? '0') === '1';

    if (empty($ids_str)) {
        echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
        exit;
    }

    $ids = explode(',', $ids_str);
    $deleted_count = 0;

    foreach ($ids as $id) {
        $id = (int) $id;
        $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $post = $stmt->fetch();

        if ($post) {
            if ($from_fb && $post['fb_post_id']) {
                $stmt_token = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
                $stmt_token->execute([$post['page_id']]);
                $token = $stmt_token->fetchColumn();
                if ($token) {
                    $fb->deleteScheduledPost($post['fb_post_id'], $token);
                }
            }
            $stmt_del = $pdo->prepare("DELETE FROM fb_scheduled_posts WHERE id = ?");
            $stmt_del->execute([$id]);
            $deleted_count++;
        }
    }

    echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
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
        // Helper to normalize file input
        $uploaded_files = null;
        if (isset($_FILES['media_files'])) {
            $uploaded_files = $_FILES['media_files'];
        } elseif (isset($_FILES['media_file'])) {
            $uploaded_files = $_FILES['media_file'];
        }

        if ($uploaded_files) {
            $upload_dir = __DIR__ . '/../uploads/scheduler/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            // Check if standard array structure (from multiple file input)
            if (is_array($uploaded_files['name'])) {
                $count = count($uploaded_files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $err = $uploaded_files['error'][$i];
                    if ($err === UPLOAD_ERR_OK) {
                        $ext = pathinfo($uploaded_files['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'post_' . time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                        $target = $upload_dir . $filename;
                        if (move_uploaded_file($uploaded_files['tmp_name'][$i], $target)) {
                            $images_to_fb[] = new CURLFile(realpath($target));
                            $local_paths[] = '../uploads/scheduler/' . $filename;
                        } else {
                            // move_uploaded_file failed
                            echo json_encode(['status' => 'error', 'message' => "Failed to save file on server."]);
                            exit;
                        }
                    } elseif ($err !== UPLOAD_ERR_NO_FILE) {
                        // Real upload error (e.g. size)
                        echo json_encode(['status' => 'error', 'message' => "Upload Error Code: $err. Check post_max_size."]);
                        exit;
                    }
                }
            } else {
                // Single file structure
                $err = $uploaded_files['error'];
                if ($err === UPLOAD_ERR_OK) {
                    $ext = pathinfo($uploaded_files['name'], PATHINFO_EXTENSION);
                    $filename = 'post_' . time() . '_' . uniqid() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($uploaded_files['tmp_name'], $target)) {
                        $images_to_fb[] = new CURLFile(realpath($target));
                        $local_paths[] = '../uploads/scheduler/' . $filename;
                    } else {
                        echo json_encode(['status' => 'error', 'message' => "Failed to save file on server."]);
                        exit;
                    }
                } elseif ($err !== UPLOAD_ERR_NO_FILE) {
                    echo json_encode(['status' => 'error', 'message' => "Upload Error Code: $err"]);
                    exit;
                }
            }

            if (empty($images_to_fb)) {
                echo json_encode(['status' => 'error', 'message' => "No valid images could be processed."]);
                exit;
            }
        }

        // Verify if we expected media but got none
        $media_expected = $_POST['media_expected'] ?? 'false';
        if ($media_expected === 'true' && empty($images_to_fb)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'فشل رفع الوسائط. الخادم لم يستقبل أي صور. يرجى التحقق من حجم الملفات أو الاتصال بالإنترنت.'
            ]);
            exit;
        }

        // Determine what to pass to FB
        // If > 1 image, we probably need an Album logic, but current FB API class expects single $image.
        // For PROPER support, we'd need to update FB Class.
        // For now, if > 1, pass the array and let FB Class deal with it (or assume first one if not supported yet).

        $image_to_pass = $images_to_fb; // Always pass as array for consistent FB API handling
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

                // Handle old multi-image JSON
                if (is_string($image_to_pass) && strpos($image_to_pass, '[') === 0) {
                    $decoded = json_decode($image_to_pass, true);
                    if (is_array($decoded)) {
                        $image_to_pass = $decoded;
                    }
                }
            }

            // Check if media actually changed
            $media_changed = ($old_post['media_url'] !== $final_local_path);

            if ($media_changed || $old_post['post_type'] !== $post_type) {
                // DELETE OLD FROM FB
                if ($old_post['fb_post_id']) {
                    $fb->deleteScheduledPost($old_post['fb_post_id'], $token);
                }
                // CREATE NEW ON FB
                // DEBUGGING LOG
                $debug_log = __DIR__ . '/../debug_log.txt';
                $log_msg = date('Y-m-d H:i:s') . " - Scheduling Post. PageID: $page_id. Media Count: " . count($images_to_fb) . "\n";
                // Capture image types
                foreach ($images_to_fb as $k => $v) {
                    $type = is_object($v) ? get_class($v) : gettype($v);
                    $log_msg .= "Image $k: $type\n";
                }
                file_put_contents($debug_log, $log_msg, FILE_APPEND);

                $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);

                // Log Response
                file_put_contents($debug_log, "FB Response: " . print_r($fb_res, true) . "\n\n", FILE_APPEND);

                if (isset($fb_res['id'])) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET post_type = ?, content = ?, media_url = ?, scheduled_at = ?, fb_post_id = ? WHERE id = ?");
                    $stmt->execute([$post_type, $content, $final_local_path, date('Y-m-d H:i:s', $timestamp), $fb_res['id'], $id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    $error_msg = $fb_res['error']['message'] ?? 'Facebook API Error';
                    // Return the FULL debug to the user for now
                    echo json_encode(['status' => 'error', 'message' => $error_msg, 'debug' => $fb_res]);
                }
            } else {
                // NOT CHANGED MEDIA, just update fields
                $is_published = (strtotime($old_post['scheduled_at']) <= time()) || ($old_post['status'] === 'success');

                $params = [
                    'message' => $content,
                    'caption' => $content,
                    'description' => $content
                ];

                // Only send scheduling if it's still in the future across both indicators
                if (!$is_published && $timestamp > time()) {
                    $params['scheduled_publish_time'] = (string) $timestamp;
                }

                $fb_res = $fb->makeRequest($old_post['fb_post_id'], $params, $token, 'POST');

                if (isset($fb_res['success']) && ($fb_res['success'] === true || $fb_res['success'] === 'true' || $fb_res['success'] == 1) || isset($fb_res['id'])) {
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
            // DEBUGGING LOG
            $debug_log = __DIR__ . '/../debug_log.txt';
            $log_msg = date('Y-m-d H:i:s') . " - NEW Post. PageID: $page_id. Media Count: " . count($images_to_fb) . "\n";
            foreach ($images_to_fb as $k => $v) {
                $type = is_object($v) ? get_class($v) : gettype($v);
                $log_msg .= "Image $k: $type\n";
            }
            file_put_contents($debug_log, $log_msg, FILE_APPEND);

            $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);

            file_put_contents($debug_log, "NEW FB Response: " . print_r($fb_res, true) . "\n\n", FILE_APPEND);

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
