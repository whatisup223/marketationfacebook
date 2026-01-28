<?php
/**
 * WhatsApp Universal Sender Engine
 * Supports: Evolution API (QR), Meta Business API, Twilio, and extensible for other providers
 */

class WAUniversalSender
{
    private $pdo;
    private $campaign;
    private $user_settings;
    private $current_account_index = 0;
    private $messages_sent_with_current_account = 0;

    public function __construct($pdo, $campaign_id)
    {
        $this->pdo = $pdo;
        $this->loadCampaign($campaign_id);
        $this->loadUserSettings();
    }

    private function loadCampaign($campaign_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wa_campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $this->campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->campaign) {
            throw new Exception("Campaign not found");
        }

        // Decode JSON fields
        $this->campaign['numbers'] = json_decode($this->campaign['numbers'] ?: '[]', true);
        $this->campaign['selected_accounts'] = json_decode($this->campaign['selected_accounts'] ?: 'null', true);
        $this->campaign['error_log'] = json_decode($this->campaign['error_log'] ?: '[]', true) ?: [];

        // Resume state
        $this->current_account_index = $this->campaign['current_account_index'];
        $this->messages_sent_with_current_account = $this->campaign['messages_sent_with_current_account'];
    }

    private function loadUserSettings()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_wa_settings WHERE user_id = ?");
        $stmt->execute([$this->campaign['user_id']]);
        $this->user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Start sending campaign
     */
    public function start()
    {
        // Update campaign status
        $this->updateCampaignStatus('running', ['started_at' => date('Y-m-d H:i:s')]);

        $numbers = $this->campaign['numbers'];
        $start_index = $this->campaign['current_number_index'];

        for ($i = $start_index; $i < count($numbers); $i++) {
            $number = $numbers[$i];

            try {
                // Check if we need to switch account (QR mode only)
                if ($this->shouldSwitchAccount()) {
                    $this->switchToNextAccount();
                }

                // Process message with variables
                $processed_message = $this->processMessage($number);

                // Send based on gateway mode
                $result = $this->sendMessage($number, $processed_message);

                if ($result['success']) {
                    $this->campaign['sent_count']++;
                    $this->messages_sent_with_current_account++;
                } else {
                    $this->campaign['failed_count']++;
                    $this->logError($number, $result['error']);
                }

                // Update progress
                $this->updateProgress($i + 1);

                // Random delay between messages
                $this->randomDelay();

            } catch (Exception $e) {
                $this->campaign['failed_count']++;
                $this->logError($number, $e->getMessage());
            }
        }

        // Mark as completed
        $this->updateCampaignStatus('completed', ['completed_at' => date('Y-m-d H:i:s')]);

        return [
            'success' => true,
            'sent' => $this->campaign['sent_count'],
            'failed' => $this->campaign['failed_count']
        ];
    }

    /**
     * Process message with variables and spin syntax
     */
    private function processMessage($number)
    {
        $message = $this->campaign['message'];

        // Extract name from number (you can enhance this with actual contact data)
        $name = $this->extractNameFromNumber($number);

        // Replace {{name}} variable
        $message = str_replace('{{name}}', $name, $message);

        // Process spin syntax {option1|option2|option3}
        $message = preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
            $options = explode('|', $matches[1]);
            return $options[array_rand($options)];
        }, $message);

        return $message;
    }

    /**
     * Extract name from phone number (placeholder - enhance with actual contact lookup)
     */
    private function extractNameFromNumber($number)
    {
        // TODO: Implement actual contact lookup from database
        return $number; // For now, return number itself
    }

    /**
     * Check if we should switch to next account (QR mode only)
     */
    private function shouldSwitchAccount()
    {
        if ($this->campaign['gateway_mode'] !== 'qr') {
            return false;
        }

        if (!$this->campaign['switch_every']) {
            return false;
        }

        $accounts = $this->campaign['selected_accounts'];
        if (!$accounts || count($accounts) <= 1) {
            return false;
        }

        return $this->messages_sent_with_current_account >= $this->campaign['switch_every'];
    }

    /**
     * Switch to next account in rotation
     */
    private function switchToNextAccount()
    {
        $accounts = $this->campaign['selected_accounts'];
        $this->current_account_index = ($this->current_account_index + 1) % count($accounts);
        $this->messages_sent_with_current_account = 0;

        // Save state
        $stmt = $this->pdo->prepare("UPDATE wa_campaigns SET current_account_index = ?, messages_sent_with_current_account = 0 WHERE id = ?");
        $stmt->execute([$this->current_account_index, $this->campaign['id']]);
    }

    /**
     * Send message based on gateway mode
     */
    private function sendMessage($number, $message)
    {
        switch ($this->campaign['gateway_mode']) {
            case 'qr':
                return $this->sendViaEvolution($number, $message);
            case 'meta':
                return $this->sendViaMeta($number, $message);
            case 'twilio':
                return $this->sendViaTwilio($number, $message);
            default:
                throw new Exception("Unsupported gateway mode: " . $this->campaign['gateway_mode']);
        }
    }

    /**
     * Send via Evolution API (QR Code)
     */
    private function sendViaEvolution($number, $message)
    {
        $accounts = $this->campaign['selected_accounts'];
        $account_id = $accounts[$this->current_account_index];

        // Get account details
        $stmt = $this->pdo->prepare("SELECT * FROM wa_accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return ['success' => false, 'error' => 'Account not found'];
        }

        $evolution_url = $this->user_settings['evolution_url'];
        $api_key = $this->user_settings['evolution_api_key'];
        $instance_name = $account['instance_name'];

        // Prepare request based on media type
        $endpoint = "$evolution_url/message/sendText/$instance_name";
        $data = [
            'number' => $number,
            'text' => $message
        ];

        // Handle media
        if ($this->campaign['media_type'] !== 'text') {
            $endpoint = $this->getEvolutionMediaEndpoint($instance_name);
            $data = $this->prepareEvolutionMediaData($number, $message);
        }

        // Send request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "HTTP $http_code: $response"];
        }
    }

    /**
     * Get Evolution API endpoint for media
     */
    private function getEvolutionMediaEndpoint($instance_name)
    {
        $evolution_url = $this->user_settings['evolution_url'];
        $media_type = str_replace('_local', '', $this->campaign['media_type']);

        switch ($media_type) {
            case 'image':
                return "$evolution_url/message/sendMedia/$instance_name";
            case 'video':
                return "$evolution_url/message/sendMedia/$instance_name";
            case 'document':
                return "$evolution_url/message/sendMedia/$instance_name";
            case 'location':
                return "$evolution_url/message/sendLocation/$instance_name";
            default:
                return "$evolution_url/message/sendText/$instance_name";
        }
    }

    /**
     * Prepare Evolution API media data
     */
    private function prepareEvolutionMediaData($number, $message)
    {
        $media_type = str_replace('_local', '', $this->campaign['media_type']);

        if ($media_type === 'location') {
            // Parse Google Maps URL to extract coordinates
            $coords = $this->parseLocationUrl($this->campaign['media_url']);
            return [
                'number' => $number,
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'name' => 'Location',
                'address' => $message ?: 'Shared location'
            ];
        } else {
            // Image, video, document
            $media_url = $this->campaign['media_url'];

            // If local file, we need to upload it first or use file path
            if (strpos($this->campaign['media_type'], '_local') !== false && $this->campaign['media_file_path']) {
                $media_url = $this->getPublicMediaUrl($this->campaign['media_file_path']);
            }

            return [
                'number' => $number,
                'mediatype' => $media_type,
                'media' => $media_url,
                'caption' => $message
            ];
        }
    }

    /**
     * Parse Google Maps URL to extract coordinates
     */
    private function parseLocationUrl($url)
    {
        // Try to extract lat,lng from various Google Maps URL formats
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return ['lat' => $matches[1], 'lng' => $matches[2]];
        }
        if (preg_match('/q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return ['lat' => $matches[1], 'lng' => $matches[2]];
        }
        // Default fallback
        return ['lat' => 0, 'lng' => 0];
    }

    /**
     * Get public URL for uploaded media file
     */
    private function getPublicMediaUrl($file_path)
    {
        // Assuming uploads are in /uploads/wa_media/
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . '/' . ltrim($file_path, '/');
    }

    /**
     * Send via Meta Business API
     */
    private function sendViaMeta($number, $message)
    {
        // TODO: Implement Meta Business API integration
        $access_token = $this->user_settings['meta_access_token'];
        $phone_number_id = $this->user_settings['meta_phone_number_id'];

        $url = "https://graph.facebook.com/v18.0/$phone_number_id/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $number,
            'type' => 'text',
            'text' => ['body' => $message]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "HTTP $http_code: $response"];
        }
    }

    /**
     * Send via Twilio API
     */
    private function sendViaTwilio($number, $message)
    {
        // TODO: Implement Twilio API integration
        $account_sid = $this->user_settings['twilio_account_sid'];
        $auth_token = $this->user_settings['twilio_auth_token'];
        $from_number = $this->user_settings['twilio_from_number'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";

        $data = [
            'From' => "whatsapp:$from_number",
            'To' => "whatsapp:$number",
            'Body' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "HTTP $http_code: $response"];
        }
    }

    /**
     * Random delay between messages
     */
    private function randomDelay()
    {
        $min = $this->campaign['delay_min'];
        $max = $this->campaign['delay_max'];
        $delay = rand($min, $max);
        sleep($delay);
    }

    /**
     * Update campaign progress
     */
    private function updateProgress($current_index)
    {
        $stmt = $this->pdo->prepare("
            UPDATE wa_campaigns 
            SET current_number_index = ?, 
                sent_count = ?, 
                failed_count = ?,
                messages_sent_with_current_account = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $current_index,
            $this->campaign['sent_count'],
            $this->campaign['failed_count'],
            $this->messages_sent_with_current_account,
            $this->campaign['id']
        ]);
    }

    /**
     * Update campaign status
     */
    private function updateCampaignStatus($status, $extra_fields = [])
    {
        $fields = ['status' => $status];
        $fields = array_merge($fields, $extra_fields);

        $set_clause = [];
        $values = [];
        foreach ($fields as $key => $value) {
            $set_clause[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $this->campaign['id'];

        $sql = "UPDATE wa_campaigns SET " . implode(', ', $set_clause) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Log error
     */
    private function logError($number, $error)
    {
        $this->campaign['error_log'][] = [
            'number' => $number,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $stmt = $this->pdo->prepare("UPDATE wa_campaigns SET error_log = ? WHERE id = ?");
        $stmt->execute([json_encode($this->campaign['error_log']), $this->campaign['id']]);
    }

    /**
     * Pause campaign
     */
    public function pause()
    {
        $this->updateCampaignStatus('paused', ['paused_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Resume campaign
     */
    public function resume()
    {
        $this->updateCampaignStatus('running');
        return $this->start();
    }

    /**
     * Cancel campaign
     */
    public function cancel()
    {
        $this->updateCampaignStatus('cancelled');
    }
}
