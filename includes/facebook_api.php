<?php

class FacebookAPI
{
    private $api_version = 'v18.0'; // Updated to v18.0
    private $base_url = 'https://graph.facebook.com/';

    public function __construct()
    {
    }

    public function makeRequest($endpoint, $params = [], $access_token = '', $method = 'GET')
    {
        $url = $this->base_url . $this->api_version . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        $headers = ['Accept: application/json'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

            // Handle multipart/form-data for files
            $hasFile = false;
            foreach ($params as $key => $val) {
                if ($val instanceof CURLFile) {
                    $hasFile = true;
                    break;
                }
            }

            if ($hasFile) {
                // For files, we MUST NOT use json_encode, and we MUST include the access token in fields
                $params['access_token'] = $access_token;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                // Standard JSON POST
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($access_token);
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        } else {
            // GET, DELETE, etc.
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($access_token);
            if ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if (!empty($params)) {
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                }
            } else {
                if (!empty($params))
                    $url .= '&' . http_build_query($params);
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Debugging Production (SSL Bypass)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true);
    }

    // New Simplified sendMessage
    public function sendMessage($page_id, $access_token, $recipient_id, $message_text, $image_url = null)
    {
        // 1. Text Message (Primary)
        if (!empty($message_text)) {
            // Priority 1: Try as RESPONSE (Safest for < 24h)
            $payload = [
                'recipient' => ['id' => $recipient_id],
                'message' => ['text' => $message_text],
                'messaging_type' => 'RESPONSE'
            ];

            // Explicit Page ID Endpoint
            $endpoint = $page_id . '/messages';

            $res = $this->makeRequest($endpoint, $payload, $access_token, 'POST');

            // Fallback: If failed with generic error, try MESSAGE_TAG
            if (isset($res['error'])) {
                $payload['messaging_type'] = 'MESSAGE_TAG';
                $payload['tag'] = 'POST_PURCHASE_UPDATE';
                $res = $this->makeRequest($endpoint, $payload, $access_token, 'POST');
            }

            // Final Fallback: Try Minimal (No Type)
            if (isset($res['error'])) {
                unset($payload['messaging_type']);
                unset($payload['tag']);
                $res = $this->makeRequest($endpoint, $payload, $access_token, 'POST');
            }

            // Return if failed or no image to send
            if (isset($res['error']) || empty($image_url)) {
                return $res;
            }
        }

        // 2. Image Message (If exists)
        if (!empty($image_url)) {
            $img_payload = [
                'recipient' => ['id' => $recipient_id],
                'message' => [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => [
                            'url' => $image_url,
                            'is_reusable' => true
                        ]
                    ]
                ],
                'messaging_type' => 'RESPONSE'
            ];
            $endpoint = $page_id . '/messages';
            return $this->makeRequest($endpoint, $img_payload, $access_token, 'POST');
        }

        return $res ?? ['error' => 'No message content'];
    }

    /**
     * Sends multiple messages in parallel using curl_multi
     * This is 10-20x faster than sequential Loop
     * 
     * @param array $items Array of [ 'page_id', 'access_token', 'recipient_id', 'message_text', 'image_url' ]
     * @return array Array of results indexed by original array key
     */
    public function sendBatchMessages($items)
    {
        if (empty($items))
            return [];

        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        // 1. Prepare all requests
        // 1. Prepare all requests
        foreach ($items as $key => $item) {
            $page_id = $item['page_id'];
            $access_token = $item['access_token'];
            $recipient_id = $item['recipient_id'];
            $message_text = $item['message_text'];
            $image_url = $item['image_url'] ?? null;

            $endpoint = $this->base_url . $this->api_version . '/' . $page_id . '/messages';
            $endpoint .= '?access_token=' . urlencode($access_token);

            $requests_to_make = [];

            // Case A: Image + Text (Send Image FIRST, then Text)
            // Note: In parallel they might arrive slightly mixed, but usually network latency for image makes text arrive first if simultaneous.
            // To ensure order we usually need sequential, but for speed we do parallel.
            // Let's send both.
            if (!empty($image_url) && !empty($message_text)) {
                // Image Req
                $requests_to_make[$key . '_img'] = [
                    'recipient' => ['id' => $recipient_id],
                    'message' => [
                        'attachment' => [
                            'type' => 'image',
                            'payload' => ['url' => $image_url, 'is_reusable' => true]
                        ]
                    ],
                    'messaging_type' => 'RESPONSE'
                ];
                // Text Req
                $requests_to_make[$key . '_txt'] = [
                    'recipient' => ['id' => $recipient_id],
                    'message' => ['text' => $message_text],
                    'messaging_type' => 'RESPONSE'
                ];

            } elseif (!empty($image_url)) {
                // Image Only
                $requests_to_make[$key] = [
                    'recipient' => ['id' => $recipient_id],
                    'message' => [
                        'attachment' => [
                            'type' => 'image',
                            'payload' => ['url' => $image_url, 'is_reusable' => true]
                        ]
                    ],
                    'messaging_type' => 'RESPONSE'
                ];
            } elseif (!empty($message_text)) {
                // Text Only
                $requests_to_make[$key] = [
                    'recipient' => ['id' => $recipient_id],
                    'message' => ['text' => $message_text],
                    'messaging_type' => 'RESPONSE'
                ];
            }

            foreach ($requests_to_make as $reqKey => $payload) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                curl_multi_add_handle($mh, $ch);
                $handles[$reqKey] = $ch;
            }
        }

        // 2. Execute Parallel
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // 3. Collect Results
        foreach ($handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($response, true);

            // Determine separate keys
            $originalKey = $key;
            if (strpos($key, '_img') !== false) {
                $originalKey = str_replace('_img', '', $key);
            } elseif (strpos($key, '_txt') !== false) {
                $originalKey = str_replace('_txt', '', $key);
            }

            // Determine Success/Error
            $isError = ($http_code >= 400 || isset($json['error']));
            $resultData = $isError ? ($json ?? ['error' => 'HTTP ' . $http_code]) : $json;

            // Merge logic:
            // If we already have a result for this key (e.g. from the other part), we need to be careful.
            // Priority: If ANY part failed, we strictly might want to show error?
            // BETTER: If Text succeeded but Image failed, we still sent something.
            // Let's store failures.

            if (!isset($results[$originalKey])) {
                $results[$originalKey] = $resultData;
            } else {
                // We have a previous result.
                // If current is error, overwrite previous success (so we know something went wrong)
                // OR: If current is success and previous was error, we can maybe say "partial success"?
                // Let's keep the last one unless it's an error overwriting a success, that's tricky.
                // Simple logic: If we have an error now, save it.
                if ($isError) {
                    $results[$originalKey] = $resultData;
                }
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
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

    /**
     * Reply to a specific comment
     */
    public function replyToComment($comment_id, $message, $access_token)
    {
        $endpoint = $comment_id . '/comments';
        $params = [
            'message' => $message
        ];
        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }

    /**
     * Hide or Unhide a comment
     */
    public function hideComment($comment_id, $access_token, $status = true)
    {
        $endpoint = $comment_id;
        $params = [
            'is_hidden' => $status
        ];
        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }

    /**
     * Send Private Reply to Comment
     */
    public function replyPrivateToComment($comment_id, $message, $access_token)
    {
        $endpoint = 'me/messages';
        $params = [
            'recipient' => ['comment_id' => $comment_id],
            'message' => ['text' => $message]
        ];
        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }

    /**
     * Publish a post (immediate or scheduled)
     */
    public function publishPost($page_id, $access_token, $message, $image = null, $scheduled_at = null, $post_type = 'feed')
    {
        $params = [];
        $endpoint = "$page_id/feed";

        // Check if array of media (Album or Single via Array)
        // Fix: Allow count >= 1 because scheduler sends array even for single file
        if (is_array($image) && count($image) >= 1) {
            // Upload each photo independently to get ID
            $media_ids = [];
            foreach ($image as $img_file) {
                // Determine if it's CURLFile or path
                $img_param = [];
                if ($img_file instanceof CURLFile) {
                    $img_param['source'] = $img_file;
                } elseif (is_string($img_file)) {
                    if (filter_var($img_file, FILTER_VALIDATE_URL)) {
                        $img_param['url'] = $img_file;
                    } else {
                        $abs_path = realpath($img_file);
                        if ($abs_path) {
                            $img_param['source'] = new CURLFile($abs_path);
                        }
                    }
                }

                if (!empty($img_param)) {
                    $img_param['published'] = 'false';
                    // Upload to /photos
                    $res = $this->makeRequest("$page_id/photos", $img_param, $access_token, 'POST');

                    // DEBUG 
                    $debug_log = __DIR__ . '/../debug_log.txt';
                    file_put_contents($debug_log, "   - Photo Upload Internal Res: " . print_r($res, true) . "\n", FILE_APPEND);

                    if (isset($res['id'])) {
                        $media_ids[] = $res['id'];
                    }
                }
            }

            // DEBUG 
            $debug_log = __DIR__ . '/../debug_log.txt';
            file_put_contents($debug_log, "   - All Photo IDs: " . implode(', ', $media_ids) . "\n", FILE_APPEND);

            if (!empty($media_ids)) {
                // Publish Feed Post with attached_media
                $feed_params = ['message' => $message];
                $feed_params['attached_media'] = [];
                foreach ($media_ids as $fbid) {
                    $feed_params['attached_media'][] = ['media_fbid' => $fbid];
                }

                if ($scheduled_at) {
                    $feed_params['published'] = 'false';
                    $feed_params['scheduled_publish_time'] = (string) $scheduled_at;
                }

                return $this->makeRequest("$page_id/feed", $feed_params, $access_token, 'POST');
            } else {
                return ['error' => ['message' => 'Failed to upload any media files to Facebook. Check debug_log.txt for internal errors.']];
            }
        }

        // Determine File Type if $image is a file
        $is_video = false;
        if ($image && !is_array($image) && !is_string($image)) {
            // Check CURLFile mime or filename
            $filename = $image->getFilename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                $is_video = true;
            }
        } elseif (is_string($image) && !filter_var($image, FILTER_VALIDATE_URL)) {
            $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                $is_video = true;
            }
        }

        if ($post_type === 'story') {
            return $this->publishStory($page_id, $access_token, $image, $scheduled_at);
        }

        if ($post_type === 'reel') {
            return $this->publishReel($page_id, $access_token, $image, $message, $scheduled_at);
        }

        if ($is_video) {
            $endpoint = "$page_id/videos";
            $params['description'] = $message;
            if ($image instanceof CURLFile) {
                $params['source'] = $image;
            } else {
                $abs_path = realpath($image);
                $params['source'] = new CURLFile($abs_path);
            }
        } elseif ($image) {
            if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                $endpoint = "$page_id/photos";
                $params['caption'] = $message;
                $params['url'] = $image;
            } else {
                $endpoint = "$page_id/photos";
                $params['caption'] = $message;
                if ($image instanceof CURLFile) {
                    $params['source'] = $image;
                } else {
                    $abs_path = realpath($image);
                    if ($abs_path) {
                        $params['source'] = new CURLFile($abs_path);
                    }
                }
            }
        } else {
            $params['message'] = $message;
        }

        if ($scheduled_at) {
            $params['published'] = 'false';
            $params['scheduled_publish_time'] = (string) $scheduled_at;
        }

        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }

    /**
     * Publish a Story
     */
    public function publishStory($page_id, $access_token, $media, $scheduled_at = null)
    {
        // Standard FB Pages API usually expects stories through specific endpoints or as a photo/video post with target set.
        // For broad compatibility, we use the photos/videos endpoint but tagged for stories where possible.
        // Currently, most apps use current photo_stories/video_stories.

        $params = [];
        $is_video = false;

        if ($media instanceof CURLFile) {
            $media_item = $media;
            $ext = strtolower(pathinfo($media->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'mov', 'avi']))
                $is_video = true;
        } elseif (!empty($media)) {
            $abs_file = realpath($media);
            if ($abs_file && file_exists($abs_file)) {
                $media_item = new CURLFile($abs_file);
                $ext = strtolower(pathinfo($abs_file, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'mov', 'avi']))
                    $is_video = true;
            } else {
                return ['error' => ['message' => 'Media file not found for Story']];
            }
        }

        if ($is_video) {
            $endpoint = "$page_id/video_stories";
            $params['video'] = $media_item;
        } else {
            $endpoint = "$page_id/photo_stories";
            $params['photo'] = $media_item;
        }

        if ($scheduled_at) {
            $params['scheduled_publish_time'] = (string) $scheduled_at;
        }

        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }

    /**
     * Publish a Reel (simplified for now via videos endpoint with vertical orientation)
     */
    public function publishReel($page_id, $access_token, $media, $caption = '', $scheduled_at = null)
    {
        $endpoint = "$page_id/videos";
        $params = [
            'description' => $caption,
            'title' => substr($caption, 0, 50)
        ];

        if ($media instanceof CURLFile) {
            $params['source'] = $media;
        } elseif (!empty($media)) {
            $abs_path = realpath($media);
            if ($abs_path) {
                $params['source'] = new CURLFile($abs_path);
            } else {
                return ['error' => ['message' => 'Video file not found for Reel']];
            }
        }

        if ($scheduled_at) {
            $params['published'] = 'false';
            $params['scheduled_publish_time'] = (string) $scheduled_at;
        }

        return $this->makeRequest($endpoint, $params, $access_token, 'POST');
    }
    public function deleteScheduledPost($post_id, $access_token)
    {
        return $this->makeRequest($post_id, [], $access_token, 'DELETE');
    }

    public function updatePost($post_id, $access_token, $message)
    {
        return $this->makeRequest($post_id, ['message' => $message], $access_token, 'POST');
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



    public function subscribeApp($page_id, $access_token)
    {
        return $this->makeRequest("$page_id/subscribed_apps", [
            'subscribed_fields' => ['feed', 'messages', 'messaging_postbacks']
        ], $access_token, 'POST');
    }
}