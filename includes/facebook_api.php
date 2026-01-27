<?php

class FacebookAPI
{
    private $api_version = 'v12.0';
    private $base_url = 'https://graph.facebook.com/';

    public function __construct()
    {
    }

    private function makeRequest($endpoint, $params = [], $access_token = '', $method = 'GET')
    {
        $url = $this->base_url . $this->api_version . '/' . ltrim($endpoint, '/');
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($access_token);

        $ch = curl_init();
        $headers = ['Accept: application/json'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

            // Check if we are sending a file (multipart)
            $hasFile = false;
            foreach ($params as $key => $val) {
                if ($val instanceof CURLFile) {
                    $hasFile = true;
                    break;
                }
            }

            if ($hasFile) {
                // Multipart/form-data (required for direct file upload)
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                // JSON (recommended for standard messaging)
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        } else {
            if (!empty($params))
                $url .= '&' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Smart SSL Configuration: Auto-detect environment
        // Development (localhost): SSL verification disabled (fixes Windows certificate issues)
        // Production: SSL verification enabled (secure)
        $is_dev = (
            (isset($_SERVER['HTTP_HOST']) && (
                strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
            )) ||
            (php_sapi_name() === 'cli-server') // PHP built-in server
        );

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$is_dev);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $is_dev ? 0 : 2);

        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for uploads
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        // Check for CURL errors (network issues, SSL problems, etc.)
        if ($curl_errno !== 0) {
            $error_log = "[" . date('Y-m-d H:i:s') . "] CURL Error #$curl_errno: $curl_error | URL: $url\n";
            @file_put_contents(__DIR__ . '/fb_debug.log', $error_log, FILE_APPEND);

            return [
                'error' => "Network Error: $curl_error",
                'code' => $curl_errno,
                'type' => 'CURL_ERROR',
                'full' => ['curl_error' => $curl_error, 'curl_errno' => $curl_errno]
            ];
        }

        $data = json_decode($response, true);
        if ($http_code >= 400 || (isset($data['error']) && !empty($data['error']))) {
            $error_msg = $data['error']['message'] ?? 'API Error';

            // Log the error for debugging
            $error_log = "[" . date('Y-m-d H:i:s') . "] FB API Error: $error_msg | HTTP: $http_code | URL: $url\n";
            @file_put_contents(__DIR__ . '/fb_debug.log', $error_log, FILE_APPEND);

            return [
                'error' => $error_msg,
                'code' => $data['error']['code'] ?? $http_code,
                'type' => $data['error']['type'] ?? '',
                'full' => $data // Keep the full error for debugging
            ];
        }

        return $data;
    }

    public function getObject($id, $access_token, $fields = [])
    {
        $params = [];
        if (!empty($fields)) {
            $params['fields'] = is_array($fields) ? implode(',', $fields) : $fields;
        }
        return $this->makeRequest($id, $params, $access_token);
    }

    // NEW: Function to get real Page Token
    public function getPageAccessToken($user_token, $page_id)
    {
        // Try to get token type first if possible or just handle error silently
        $res = $this->makeRequest("me/accounts", [], $user_token);
        if (isset($res['data'])) {
            foreach ($res['data'] as $page) {
                if ($page['id'] == $page_id)
                    return $page['access_token'];
            }
        }
        return $user_token; // If not found or error, return original
    }

    public function sendMessage($page_id, $page_access_token, $recipient_id, $message_text, $image_url = null)
    {
        $real_token = $page_access_token;
        $endpoint = "me/messages"; // Reverted to 'me/messages' as requested
        $res = null;

        // Helper to perform the actual send
        $doSend = function ($token) use ($endpoint, $recipient_id, $message_text, $image_url) {
            $last_res = null;

            // 1. Send Text
            if (!empty(trim($message_text))) {
                $payload = [
                    'recipient' => ['id' => (string) $recipient_id],
                    'message' => ['text' => (string) $message_text],
                    'messaging_type' => 'MESSAGE_TAG',
                    'tag' => 'CONFIRMED_EVENT_UPDATE'
                ];
                $last_res = $this->makeRequest($endpoint, $payload, $token, 'POST');

                if (isset($last_res['error'])) {
                    $payload['messaging_type'] = 'UPDATE';
                    unset($payload['tag']);
                    $last_res = $this->makeRequest($endpoint, $payload, $token, 'POST');
                }
            }

            // 2. Send Image (Robust Direct Upload)
            if (!empty(trim($image_url))) {
                $upload_path = null;
                $is_temp = false;

                // A. Base64
                if (preg_match('/^data:image\/(\w+);base64,/', $image_url, $type)) {
                    $img = substr($image_url, strpos($image_url, ',') + 1);
                    $img = base64_decode($img);
                    $upload_path = tempnam(sys_get_temp_dir(), 'fb_b64_') . '.' . strtolower($type[1]);
                    file_put_contents($upload_path, $img);
                    $is_temp = true;
                }
                // B. Local Path Detection (Improved)
                // Check if it's a URL resolving to this server
                elseif (
                    strpos($image_url, 'localhost') !== false ||
                    strpos($image_url, '127.0.0.1') !== false ||
                    strpos($image_url, $_SERVER['HTTP_HOST'] ?? 'somerandomhost') !== false
                ) {
                    // Extract relative path from URL
                    $path_part = parse_url($image_url, PHP_URL_PATH); // e.g., /marketation/uploads/campaigns/img.jpg

                    // We need to map this URL path to a File System path.
                    // Assuming standard structure where 'uploads' is in project root.
                    // Try to find '/uploads/' in path
                    $pos = strpos($path_part, '/uploads/');
                    if ($pos !== false) {
                        $rel_path = substr($path_part, $pos); // /uploads/campaigns/img.jpg
                        $local_path = dirname(__DIR__) . $rel_path;

                        file_put_contents(__DIR__ . '/fb_debug.log', "[" . date('H:i:s') . "] Local Image Detected. URL: $image_url | Resolved: $local_path | Exists: " . (file_exists($local_path) ? 'YES' : 'NO') . "\n", FILE_APPEND);

                        if (file_exists($local_path)) {
                            $upload_path = realpath($local_path);
                        }
                    }
                }

                // C. Send as URL (Public URL)
                if (!$upload_path && !empty($image_url)) {
                    $img_payload = [
                        'recipient' => ['id' => (string) $recipient_id],
                        'message' => [
                            'attachment' => [
                                'type' => 'image',
                                'payload' => [
                                    'url' => $image_url,
                                    'is_reusable' => true
                                ]
                            ]
                        ]
                        // Note: Some API versions prefer NOT sending messaging_type for media templates unless it's strictly needed
                    ];

                    $img_res = $this->makeRequest($endpoint, $img_payload, $token, 'POST');

                    // Log result
                    if (isset($img_res['error'])) {
                        file_put_contents(__DIR__ . '/fb_debug.log', "[" . date('H:i:s') . "] Failed URL Send: $image_url | Err: " . json_encode($img_res['error']) . "\n", FILE_APPEND);
                    } else {
                        file_put_contents(__DIR__ . '/fb_debug.log', "[" . date('H:i:s') . "] Success URL Send: $image_url\n", FILE_APPEND);
                    }

                    if (!$last_res)
                        $last_res = $img_res;
                }

                // D. Perform Upload (Only if we found a Local File)
                if ($upload_path && file_exists($upload_path)) {
                    $img_payload = [
                        'recipient' => json_encode(['id' => (string) $recipient_id]),
                        'message' => json_encode([
                            'attachment' => [
                                'type' => 'image',
                                'payload' => ['is_reusable' => true]
                            ]
                        ]),
                        'filedata' => new CURLFile($upload_path),
                        'messaging_type' => 'MESSAGE_TAG',
                        'tag' => 'CONFIRMED_EVENT_UPDATE'
                    ];

                    $img_res = $this->makeRequest($endpoint, $img_payload, $token, 'POST');

                    // Retry logic
                    if (isset($img_res['error'])) {
                        $img_payload['messaging_type'] = 'UPDATE';
                        unset($img_payload['tag']);
                        $img_res = $this->makeRequest($endpoint, $img_payload, $token, 'POST');
                    }

                    if (!$last_res)
                        $last_res = $img_res;

                    if ($is_temp)
                        @unlink($upload_path);

                    file_put_contents(__DIR__ . '/fb_debug.log', "[" . date('H:i:s') . "] Uploaded Local Image: $upload_path\n", FILE_APPEND);
                }
            }
            return $last_res;
        };

        // 1. Initial Attempt
        $res = $doSend($real_token);

        // 2. Token Swap if needed (If we get "Object me does not exist", it likely means it's a User Token)
        if (isset($res['error']) && (strpos($res['error'], "Object with ID 'me' does not exist") !== false || $res['code'] == 100)) {
            $swapped_token = $this->getPageAccessToken($page_access_token, $page_id);
            if ($swapped_token !== $page_access_token) {
                $res = $doSend($swapped_token);
            }
        }

        return $res;
    }

    public function getAccounts($access_token)
    {
        return $this->makeRequest("me/accounts", [
            'fields' => 'id,name,access_token,category,picture',
            'limit' => 100
        ], $access_token);
    }

    public function getConversations($page_id, $access_token, $limit = 100, $after = '')
    {
        $params = [
            'fields' => 'id,participants,updated_time',
            'limit' => $limit
        ];
        if ($after)
            $params['after'] = $after;
        return $this->makeRequest("$page_id/conversations", $params, $access_token);
    }

    public function debugToken($input_token, $app_access_token)
    {
        return $this->makeRequest('debug_token', ['input_token' => $input_token], $app_access_token);
    }

    public function getPostComments($post_id, $access_token, $limit = 100, $after = '')
    {
        $params = [
            'fields' => 'from{id,name},message,created_time',
            'limit' => $limit
        ];
        if ($after)
            $params['after'] = $after;
        return $this->makeRequest("$post_id/comments", $params, $access_token);
    }

    public function getPostReactions($post_id, $access_token, $limit = 100, $after = '')
    {
        $params = [
            'fields' => 'id,name,type',
            'limit' => $limit
        ];
        if ($after)
            $params['after'] = $after;
        return $this->makeRequest("$post_id/reactions", $params, $access_token);
    }

    public function getPageFeed($page_id, $access_token, $limit = 20)
    {
        $response = $this->makeRequest("$page_id/feed", [
            'fields' => 'id,message,created_time,full_picture,permalink_url',
            'limit' => $limit
        ], $access_token);

        if (isset($response['error']) && ($response['code'] == 200 || $response['code'] == 100)) {
            $scraped = $this->scrapePagePosts($page_id);
            if ($scraped)
                return ['data' => $scraped];
        }

        return $response;
    }

    public function scrapePagePosts($id_or_handle)
    {
        $url = "https://m.facebook.com/" . $id_or_handle;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html)
            return false;

        $posts = [];
        preg_match_all('/\/posts\/(\d+)/', $html, $matches);
        $ids = array_unique($matches[1] ?? []);

        foreach ($ids as $id) {
            $posts[] = [
                'id' => $id,
                'message' => "Post ID: " . $id . " (Scraped Content)",
                'permalink_url' => "https://facebook.com/" . $id,
                'created_time' => date('Y-m-d H:i:s'),
                'full_picture' => ''
            ];
        }

        return !empty($posts) ? array_slice($posts, 0, 10) : false;
    }

    public function getGroupFeed($group_id, $access_token, $limit = 20)
    {
        return $this->makeRequest("$group_id/feed", [
            'fields' => 'id,message,updated_time,permalink_url',
            'limit' => $limit
        ], $access_token);
    }

    public function getObjectMetadata($id_or_url, $access_token)
    {
        $res = $this->makeRequest($id_or_url, [
            'fields' => 'id,name'
        ], $access_token);

        if (isset($res['error']) && strpos($id_or_url, 'http') !== false) {
            $scraped_id = $this->scrapeIdFromUrl($id_or_url);
            if ($scraped_id) {
                return ['id' => $scraped_id, 'name' => 'Resolved Object'];
            }
        }

        return $res;
    }

    private function scrapeIdFromUrl($url)
    {
        $urls_to_try = [$url];
        if (strpos($url, 'www.facebook.com') !== false) {
            $urls_to_try[] = str_replace('www.', 'm.', $url);
        }

        foreach ($urls_to_try as $target_url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $target_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html)
                continue;

            $patterns = [
                '/["\']pageID["\']\s*:\s*["\'](\d+)["\']/',
                '/["\']targetID["\']\s*:\s*["\'](\d+)["\']/',
                '/["\']owner_id["\']\s*:\s*["\'](\d+)["\']/',
                '/fb:\/\/page\/\?id=(\d+)/',
                '/fb:\/\/profile\/(\d+)/',
                '/entity_id=(\d+)/',
                '/"id":"(\d+)"/'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    return $matches[1];
                }
            }
        }

        return false;
    }
}