<?php

class FacebookAPI
{
    private $api_version = 'v18.0'; // Updated to v18.0
    private $base_url = 'https://graph.facebook.com/';

    public function __construct()
    {
    }

    private function makeRequest($endpoint, $params = [], $access_token = '', $method = 'GET')
    {
        // ... (Keep existing setup, just update the logic to be clean)
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
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