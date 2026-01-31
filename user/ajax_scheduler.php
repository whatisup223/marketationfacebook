<?php
// user/ajax_scheduler.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error_log.txt');
ob_start(); // Buffer output to allow sending Content-Length for backgrounding
set_time_limit(0);
ignore_user_abort(true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

@set_time_limit(0);
@ini_set('max_execution_time', 0);
ignore_user_abort(true);
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];
session_write_close();
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

    // --- SMART SYNC LOGIC ---
    $fb_res = null;
    $fb_id = $post['fb_post_id'];
    $page_id = $post['page_id'];

    // Possible IDs to try
    $ids_to_try = [$fb_id];
    if (strpos($fb_id, '_') === false && !empty($page_id)) {
        $ids_to_try[] = $page_id . '_' . $fb_id;
    }

    foreach ($ids_to_try as $current_try_id) {
        if ($fb_res && !isset($fb_res['error']))
            break;

        // Tier 1: Try as Feed/Post Object (Message based)
        $fb_res = $fb->getObject($current_try_id, $token, ['id', 'message', 'full_picture', 'picture', 'attachments']);

        // Tier 2: Check for Video/Reel hint in error
        if (isset($fb_res['error'])) {
            $msg = $fb_res['error']['message'] ?? '';
            $code = $fb_res['error']['code'] ?? 0;

            // If FB says "Tried accessing nonexisting field (message) on node type (Video)" 
            // OR if it's a Reels/Video object that doesn't support 'message'
            if ($code == 100 || strpos($msg, 'Video') !== false || strpos($msg, 'Reel') !== false) {
                $video_res = $fb->getObject($current_try_id, $token, ['id', 'description', 'picture', 'full_picture', 'source']);
                if (!isset($video_res['error'])) {
                    $fb_res = $video_res;
                }
            }
        }
    }

    // Tier 3: Fetch from Page Scheduled Posts (Critical for future posts)
    if (isset($fb_res['error'])) {
        $sched_list = $fb->makeRequest($page_id . "/scheduled_posts", ['limit' => 50, 'fields' => 'id,message,full_picture,attachments,scheduled_publish_time'], $token);
        if (isset($sched_list['data']) && is_array($sched_list['data'])) {
            foreach ($sched_list['data'] as $item) {
                if ($item['id'] == $fb_id || strpos($item['id'] ?? '', $fb_id) !== false) {
                    $fb_res = $item;
                    break;
                }
            }
        }
    }

    // Tier 4: Feed-based Lookup (Fallback for recently published posts)
    if (isset($fb_res['error'])) {
        $feed = $fb->makeRequest($page_id . "/feed", ['limit' => 25, 'fields' => 'id,message,full_picture,attachments'], $token);
        if (isset($feed['data']) && is_array($feed['data'])) {
            foreach ($feed['data'] as $item) {
                if ($item['id'] == $fb_id || (isset($item['id']) && strpos($item['id'], $fb_id) !== false)) {
                    $fb_res = $item;
                    break;
                }
            }
        }
    }

    if (isset($fb_res['error'])) {
        $err_msg = $fb_res['error']['message'] ?? 'Unknown Facebook Error';
        $err_code = $fb_res['error']['code'] ?? 0;

        file_put_contents(__DIR__ . '/../debug_log.txt', date('Y-m-d H:i:s') . " - SYNC FAILED for ID $id (FB ID: $fb_id). Error: $err_msg (Code: $err_code)\n", FILE_APPEND);

        if ($err_code == 100 || $err_code == 10 || $err_code == 21 || $err_code == 33) {
            $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute(['Deleted on FB: ' . $err_msg, $id]);
            echo json_encode(['status' => 'success', 'new_state' => 'deleted_from_fb', 'fb_error' => $err_msg]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $err_msg]);
        }
    } else {
        // Post is alive - FETCH CURRENT CONTENT
        $new_content = $fb_res['message'] ?? ($fb_res['description'] ?? ($fb_res['caption'] ?? $post['content']));

        // --- MEDIA SYNC ---
        $new_media_url = $post['media_url'];
        $media_items = [];

        // High-quality thumbnail
        $best_thumb = $fb_res['full_picture'] ?? ($fb_res['picture'] ?? '');

        // Extract from attachments if present
        if (isset($fb_res['attachments']['data'][0])) {
            $attachment = $fb_res['attachments']['data'][0];
            if ($attachment['type'] === 'album' && isset($attachment['subattachments']['data'])) {
                foreach ($attachment['subattachments']['data'] as $sub) {
                    if (isset($sub['media']['image']['src']))
                        $media_items[] = $sub['media']['image']['src'];
                }
            } else {
                if (isset($attachment['media']['image']['src']))
                    $media_items[] = $attachment['media']['image']['src'];
            }
        }

        // If it's a video and no attachments, but we have a source URL (direct video link)
        if (empty($media_items) && isset($fb_res['source'])) {
            // We still prefer the thumbnail for the main 'media_url' to keep UI fast
            // but we store the source if needed.
            if ($best_thumb)
                $media_items[] = $best_thumb;
            else
                $media_items[] = $fb_res['source'];
        }

        // Fallback to best thumbnail
        if (empty($media_items) && $best_thumb) {
            $media_items[] = $best_thumb;
        }

        if (!empty($media_items)) {
            $new_media_url = (count($media_items) === 1) ? $media_items[0] : json_encode($media_items);
        }

        $new_fb_id = $fb_res['id'] ?? $post['fb_post_id'];
        $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'success', content = ?, media_url = ?, fb_post_id = ? WHERE id = ?");
        $stmt->execute([$new_content, $new_media_url, $new_fb_id, $id]);

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
    $background = ($_GET['background'] ?? '0') === '1';

    if (empty($ids_str)) {
        echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
        exit;
    }

    $ids = array_filter(array_map('intval', explode(',', $ids_str)));

    if ($background) {
        $res = json_encode(['status' => 'background', 'message' => 'Bulk deletion started in background', 'count' => count($ids)]);
        echo $res;

        $size = ob_get_length();
        header("Content-Length: $size");
        header("Connection: close");
        ob_end_flush();
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    $deleted_count = 0;
    $debug_log = __DIR__ . '/../debug_log.txt';
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - START BULK DELETE. Items: " . count($ids) . " From FB: " . ($from_fb ? 'YES' : 'NO') . "\n", FILE_APPEND);

    foreach ($ids as $id) {
        $stmt = $pdo->prepare("SELECT * FROM fb_scheduled_posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $post = $stmt->fetch();

        if ($post) {
            $log_entry = "Deleting ID: $id (FB: " . ($post['fb_post_id'] ?: 'None') . ")";
            if ($from_fb && $post['fb_post_id']) {
                $stmt_token = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
                $stmt_token->execute([$post['page_id']]);
                $token = $stmt_token->fetchColumn();
                if ($token) {
                    $fb_res = $fb->deleteScheduledPost($post['fb_post_id'], $token);
                    $log_entry .= " | FB Res: " . json_encode($fb_res);
                } else {
                    $log_entry .= " | ERROR: Token missing";
                }
                // Small sleep to avoid rate limits on very large bulks
                usleep(50000); // 0.05s
            }
            $stmt_del = $pdo->prepare("DELETE FROM fb_scheduled_posts WHERE id = ?");
            $stmt_del->execute([$id]);
            $deleted_count++;
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - $log_entry\n", FILE_APPEND);
        }
    }

    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - BULK DELETE FINISHED. Total: $deleted_count\n", FILE_APPEND);
    if (!$background) {
        echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
    }
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
            if (isset($uploaded_files['name']) && is_array($uploaded_files['name'])) {
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
            } elseif (isset($uploaded_files['name'])) {
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
                // Update local first
                $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET post_type = ?, content = ?, media_url = ?, scheduled_at = ?, status = 'pending' WHERE id = ?");
                $stmt->execute([$post_type, $content, $final_local_path, date('Y-m-d H:i:s', $timestamp), $id]);

                echo json_encode(['status' => 'success', 'message' => 'Post update started']);

                $size = ob_get_length();
                header("Content-Length: $size");
                header("Connection: close");
                ob_end_flush();
                flush();

                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                // Background work
                if ($old_post['fb_post_id']) {
                    $fb->deleteScheduledPost($old_post['fb_post_id'], $token);
                }
                $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);

                if (isset($fb_res['id'])) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET fb_post_id = ? WHERE id = ?");
                    $stmt->execute([$fb_res['id'], $id]);
                } else {
                    $error_msg = $fb_res['error']['message'] ?? 'Facebook API Error';
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'failed', error_message = ? WHERE id = ?");
                    $stmt->execute([$error_msg, $id]);
                }
            } else {
                // NOT CHANGED MEDIA, just update fields
                $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET content = ?, scheduled_at = ?, status = 'pending' WHERE id = ?");
                $stmt->execute([$content, date('Y-m-d H:i:s', $timestamp), $id]);

                echo json_encode(['status' => 'success']);

                $size = ob_get_length();
                header("Content-Length: $size");
                header("Connection: close");
                ob_end_flush();
                flush();

                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                // Background work
                $is_published = (strtotime($old_post['scheduled_at']) <= time()) || ($old_post['status'] === 'success');
                $params = ['message' => $content, 'caption' => $content, 'description' => $content];
                if (!$is_published && $timestamp > time()) {
                    $params['scheduled_publish_time'] = (string) $timestamp;
                }

                $fb_id = $old_post['fb_post_id'];
                $ids_to_try = [$fb_id];
                if (strpos($fb_id, '_') === false) {
                    $ids_to_try[] = $old_post['page_id'] . '_' . $fb_id;
                }

                $success = false;
                $last_error = 'Unknown';

                foreach ($ids_to_try as $try_id) {
                    $fb_res = $fb->makeRequest($try_id, $params, $token, 'POST');
                    if (isset($fb_res['id']) || (isset($fb_res['success']) && ($fb_res['success'] == true || $fb_res['success'] == 1))) {
                        $success = true;
                        // If we used a different ID and it worked, update local canonical ID
                        if ($try_id !== $fb_id && isset($fb_res['id'])) {
                            $stmt_upd = $pdo->prepare("UPDATE fb_scheduled_posts SET fb_post_id = ? WHERE id = ?");
                            $stmt_upd->execute([$fb_res['id'], $id]);
                        }
                        break;
                    } else {
                        $last_error = $fb_res['error']['message'] ?? 'Unknown API Error';
                    }
                }

                if (!$success) {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'failed', error_message = ? WHERE id = ?");
                    $stmt->execute([$last_error, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'success', error_message = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                }
            }
        } else {
            // NEW POST
            $stmt = $pdo->prepare("INSERT INTO fb_scheduled_posts (user_id, page_id, post_type, content, media_url, scheduled_at, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $page_id, $post_type, $content, $final_local_path, date('Y-m-d H:i:s', $timestamp)]);
            $new_id = $pdo->lastInsertId();

            echo json_encode(['status' => 'success', 'fb_id' => null, 'message' => 'Post scheduled locally']);

            $size = ob_get_length();
            header("Content-Length: $size");
            header("Connection: close");
            ob_end_flush();
            flush();

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Background work
            $fb_res = $fb->publishPost($page_id, $token, $content, $image_to_pass, $timestamp, $post_type);

            if (isset($fb_res['id'])) {
                $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET fb_post_id = ?, status = 'pending' WHERE id = ?");
                $stmt->execute([$fb_res['id'], $new_id]);
            } else {
                $error_msg = $fb_res['error']['message'] ?? 'Facebook API Error';
                $stmt = $pdo->prepare("UPDATE fb_scheduled_posts SET status = 'failed', error_message = ? WHERE id = ?");
                $stmt->execute([$error_msg, $new_id]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
