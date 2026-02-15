<?php
/**
 * Universal Webhook Handler
 * Handles both Evolution API (WhatsApp) and Facebook (Comments & Messenger)
 */

$logFile = __DIR__ . '/webhook_log.txt';
$input = file_get_contents('php://input');
// For debugging - remove in production
// file_put_contents($logFile, date('Y-m-d H:i:s') . " - Input: " . $input . "\n", FILE_APPEND);

function debugLog($msg)
{
    // Debug logging disabled for production cleanup.
}

// 1. Handle Facebook Verification (GET request)
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $uid = $_GET['uid'] ?? '';
    $verify_token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($uid) {
        require_once __DIR__ . '/includes/db_config.php';
        $stmt = $pdo->prepare("SELECT verify_token FROM users WHERE webhook_token = ?");
        $stmt->execute([$uid]);
        $user_verify_token = $stmt->fetchColumn();

        if ($user_verify_token && $verify_token === $user_verify_token) {
            echo $challenge;
            exit;
        }
    }
    http_response_code(403);
    exit;
}

// 2. Handle Data Payload (POST request)
$data = json_decode($input, true);
if (!$data) {
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/facebook_api.php';
require_once __DIR__ . '/includes/ComplianceEngine.php';

$pdo = getDB();

// --- PRODUCTION PATCH: Ensure columns exist before using them ---
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fb_pages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ig_business_id', $cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN ig_business_id VARCHAR(100) NULL");

    $rules_cols = $pdo->query("SHOW COLUMNS FROM auto_reply_rules")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('platform', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook'");
    if (!in_array('auto_like_comment', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN auto_like_comment TINYINT(1) DEFAULT 0");
    if (!in_array('private_reply_enabled', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_enabled TINYINT(1) DEFAULT 0");

    // fb_moderation_rules
    $mod_cols = $pdo->query("SHOW COLUMNS FROM fb_moderation_rules")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ig_business_id', $mod_cols))
        $pdo->exec("ALTER TABLE fb_moderation_rules ADD COLUMN ig_business_id VARCHAR(100) NULL");
    if (!in_array('platform', $mod_cols))
        $pdo->exec("ALTER TABLE fb_moderation_rules ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook'");
    // fb_moderation_logs: Force missing columns to fix SQL 1054 error
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM fb_moderation_logs")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('content', $cols))
            $pdo->exec("ALTER TABLE fb_moderation_logs ADD COLUMN content TEXT NULL");
        if (!in_array('user_name', $cols))
            $pdo->exec("ALTER TABLE fb_moderation_logs ADD COLUMN user_name VARCHAR(255) NULL");
        if (!in_array('reason', $cols))
            $pdo->exec("ALTER TABLE fb_moderation_logs ADD COLUMN reason VARCHAR(255) NULL");
        if (!in_array('platform', $cols))
            $pdo->exec("ALTER TABLE fb_moderation_logs ADD COLUMN platform VARCHAR(50) DEFAULT 'facebook'");
        if (!in_array('post_id', $cols))
            $pdo->exec("ALTER TABLE fb_moderation_logs ADD COLUMN post_id VARCHAR(100) NULL");

        $pdo->exec("ALTER TABLE `fb_moderation_logs` MODIFY COLUMN `action_taken` VARCHAR(50) DEFAULT 'hide'");
    } catch (Exception $e) {
        debugLog("Logging Migration Error: " . $e->getMessage());
    }
} catch (Exception $em) {
    debugLog("Master Migration Error: " . $em->getMessage());
}

// A. Check if it's Facebook Webhook
if (isset($data['object']) && ($data['object'] === 'page' || $data['object'] === 'instagram')) {
    file_put_contents('debug_webhook.txt', date('Y-m-d H:i:s') . " - Processing FB Event: " . json_encode($data) . "\n", FILE_APPEND);
    try {
        handleFacebookEvent($data, $pdo);
    } catch (Exception $e) {
        file_put_contents('debug_webhook.txt', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    exit;
}

// B. Check if it's Evolution API (WhatsApp)
if (isset($data['instance']) && isset($data['event'])) {
    handleEvolutionEvent($data, $pdo);
    exit;
}

// ----------------------------------------------------------------------
// Facebook Handler
// ----------------------------------------------------------------------
function handleFacebookEvent($data, $pdo)
{
    $object_type = $data['object'] ?? '';
    foreach ($data['entry'] as $entry) {
        $entry_id = $entry['id'] ?? '';
        debugLog("RECEIVING WEBHOOK ENTRY: ID=" . $entry_id . " Object=" . $object_type);

        // Load Page/IG metadata to normalize IDs
        $stmt = $pdo->prepare("SELECT page_id, ig_business_id, page_name FROM fb_pages WHERE page_id = ? OR ig_business_id = ? LIMIT 1");
        $stmt->execute([$entry_id, $entry_id]);
        $page_meta = $stmt->fetch(PDO::FETCH_ASSOC);

        // Handle Messaging (Messenger / Direct Messages)
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messaging) {
                $sender_id = $messaging['sender']['id'] ?? '';
                $recipient_id = $messaging['recipient']['id'] ?? '';
                $is_echo = isset($messaging['message']['is_echo']) && $messaging['message']['is_echo'];

                $message_text = '';
                $meta_id = $messaging['message']['mid'] ?? null;

                // Additional safety: Skip if sender is the page itself (shouldn't happen with is_echo check)
                if ($sender_id == $entry_id) {
                    debugLog("Skipping message from page itself: $sender_id");
                    continue;
                }

                $is_payload_interaction = false;
                $message_text = '';

                // 1. Quick Reply (Button Click)
                if (isset($messaging['message']['quick_reply']['payload'])) {
                    $message_text = $messaging['message']['quick_reply']['payload'];
                    $is_payload_interaction = true;
                    // debugLog("Event: Quick Reply Detected: $message_text");
                }
                // 2. Postback (Button Click) - Prioritize over text to avoid false negatives
                elseif (isset($messaging['postback']['payload'])) {
                    $message_text = $messaging['postback']['payload'];
                    $is_payload_interaction = true;
                    // debugLog("Event: Postback Detected: $message_text");
                }
                // 3. Text Message (User Typing)
                elseif (isset($messaging['message']['text'])) {
                    $message_text = $messaging['message']['text'];
                    $is_payload_interaction = false; // Strictly False
                    // debugLog("Event: Text Message Detected: $message_text");
                }

                if (!$message_text)
                    continue;

                $platform = ($object_type === 'instagram') ? 'instagram' : 'facebook';

                // --- NEW: Update Unified Inbox for Real-time ---
                try {
                    $sender_name = ''; // Will be fetched/guessed in update function
                    $sender_type = $is_echo ? 'page' : 'user';
                    // For echo messages, the 'sender' in the webhook is the Page ID, 
                    // but for our unified_conversations table, we always track by the 'User PSID'.
                    $client_psid = $is_echo ? $recipient_id : $sender_id;
                    updateUnifiedInbox($pdo, $platform, $entry_id, $client_psid, $sender_name, $message_text, $sender_type, $meta_id);
                } catch (Exception $e) {
                    debugLog("Unified Inbox Update Failed: " . $e->getMessage());
                }

                // ONLY trigger Auto-Reply if NOT an echo
                if (!$is_echo) {
                    processAutoReply($pdo, $entry_id, $sender_id, $message_text, 'message', null, '', $platform, null, null, $is_payload_interaction);
                }
            }
        }

        // Handle Feed/Comments
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'feed' || $change['field'] === 'comments') {
                    $val = $change['value'];
                    $item = $val['item'] ?? ($object_type === 'instagram' ? 'comment' : '');
                    if ($item === 'comment') {
                        $verb = $val['verb'] ?? ($object_type === 'instagram' ? 'add' : '');
                        if (empty($verb) && $object_type === 'instagram')
                            $verb = 'add';

                        if ($verb === 'add' || $verb === 'edited') {
                            $sender_id = $val['from']['id'] ?? '';
                            if ($sender_id == $entry_id)
                                continue;

                            $comment_id = $val['comment_id'] ?? $val['id'] ?? '';
                            $post_id = $val['post_id'] ?? $val['media_id'] ?? '';
                            $parent_id = $val['parent_id'] ?? null;
                            $message_text = $val['message'] ?? $val['text'] ?? '';

                            // Better name capture
                            $sender_name = $val['from']['name'] ?? $val['from']['username'] ?? '';
                            if (empty($sender_name)) {
                                $sender_name = ($object_type === 'instagram') ? "عميل إنستجرام" : "عميل فيسبوك";
                            }

                            // PLATFORM DETECTION & ID NORMALIZATION
                            $platform = 'facebook';
                            $target_rule_id = $entry_id; // Default to the ID in the entry (Page ID or IG ID)

                            // Detect if it's an Instagram comment (even in 'page' webhook)
                            // A. Check if object type is instagram
                            // B. Check if it's a page webhook but contains 'username' instead of 'name' (IG style)
                            $is_ig_comment = ($object_type === 'instagram' || isset($val['from']['username']));

                            if ($is_ig_comment) {
                                $platform = 'instagram';
                                // CRITICAL: If we are in a 'page' webhook, $entry_id is the Facebook Page ID.
                                // We MUST find the linked 'ig_business_id' for moderation rules to work.
                                if ($page_meta && !empty($page_meta['ig_business_id'])) {
                                    $target_rule_id = $page_meta['ig_business_id'];
                                }
                            }

                            debugLog("Processing Comment: ID=$comment_id, Platform=$platform, TargetRuleID=$target_rule_id");

                            // 1. Check Moderation with normalized data
                            $is_moderated = false;
                            try {
                                $is_moderated = processModeration($pdo, $target_rule_id, $comment_id, $message_text, $sender_name, $platform, $post_id);
                            } catch (Exception $modEx) {
                                debugLog("Moderation Exception: " . $modEx->getMessage());
                            }

                            // 2. Trigger Auto-Reply if not moderated
                            if ($verb === 'add' && !$is_moderated) {
                                debugLog("Triggering Auto-Reply flow for $comment_id");
                                processAutoReply($pdo, $target_rule_id, $comment_id, $message_text, 'comment', $sender_id, $sender_name, $platform, $post_id, $parent_id);
                            }
                        }
                    }
                }
            }
        }
    }
}

function processAutoReply($pdo, $page_id, $target_id, $incoming_text, $source, $actual_sender_id = null, $sender_name = '', $platform = 'facebook', $post_id = null, $parent_id = null, $is_payload_interaction = false)
{
    // 1. Fetch Page Settings (Token, Schedule, Cooldown, AI Intelligence)
    // Try primary lookup by whichever ID Meta sent. IMPORTANT: SELECT p.ig_business_id too!
    debugLog("processAutoReply: PageID=$page_id, TargetID=$target_id, Platform=$platform, Source=$source, IsPayload=" . ($is_payload_interaction ? 'YES' : 'NO'));

    // Fetch Page
    $stmt = $pdo->prepare("SELECT p.page_id as fb_page_id, p.ig_business_id, p.page_access_token, p.page_name, p.bot_cooldown_seconds, p.bot_schedule_enabled, p.bot_schedule_start, p.bot_schedule_end, p.bot_exclude_keywords, p.bot_ai_sentiment_enabled, p.bot_anger_keywords, p.bot_repetition_threshold, p.bot_handover_reply, a.user_id 
                           FROM fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           WHERE p.page_id = ? OR p.ig_business_id = ?");
    $stmt->execute([$page_id, $page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        debugLog("processAutoReply: Page NOT found in database for ID: $page_id");
        return;
    }

    if (!$page['page_access_token']) {
        debugLog("processAutoReply: Missing Access Token for page: {$page['page_name']}");
        return;
    }

    debugLog("processAutoReply: Page found: {$page['page_name']} (User ID: {$page['user_id']})");

    // 1.1 Check Moderation Rules before anything else
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE (page_id = ? OR ig_business_id = ?) AND platform = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$page_id, $page_id, $platform]);
    $mod_rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mod_rules) {
        $violation = false;
        $reason = "";
        if ($mod_rules['hide_phones']) {
            $phone_pattern = '/(\+?[\d٠-٩]{1,4}[\s-]?[\d٠-٩]{7,14})|([\d٠-٩]{8,15})/u';
            if (preg_match($phone_pattern, $incoming_text)) {
                $violation = true;
                $reason = "Phone Number Detected";
            }
        }
        if (!$violation && $mod_rules['hide_links']) {
            $link_pattern = '/(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9-]+\.(com|net|org|me|info|biz|co|app|xyz|site|online|link))/iu';
            if (preg_match($link_pattern, $incoming_text)) {
                $violation = true;
                $reason = "Link Detected";
            }
        }
        if (!$violation && !empty($mod_rules['banned_keywords'])) {
            $keywords = preg_split('/[,،]/u', $mod_rules['banned_keywords']);
            foreach ($keywords as $kw) {
                if (!empty(trim($kw)) && mb_stripos($incoming_text, trim($kw)) !== false) {
                    $violation = true;
                    $reason = "Banned Keyword: $kw";
                    break;
                }
            }
        }

        if ($violation) {
            debugLog("processAutoReply SILENCED: Incoming message violated moderation rules.");
            // Log the silencing to DB
            try {
                $stmt = $pdo->prepare("INSERT INTO fb_moderation_logs (user_id, page_id, post_id, comment_id, content, user_name, reason, action_taken, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$mod_rules['user_id'], $page_id, $post_id, null, $incoming_text, $sender_name, $reason, 'silenced', $platform]);
            } catch (Exception $logEx) {
                debugLog("processAutoReply: Failed to log silence: " . $logEx->getMessage());
            }
            return;
        }
    }


    // Match Rule ID: Dashboard usually saves by the ID provided in the dropdown
    // For Instagram it's Business ID, for Facebook it's Page ID
    $db_page_id = $page_id;

    // Use FB Page ID for API actor by default, but switch to IG ID for Instagram
    $fb_page_id = $page['fb_page_id'];
    $api_actor_id = ($platform === 'instagram' && !empty($page['ig_business_id'])) ? $page['ig_business_id'] : $fb_page_id;
    $access_token = $page['page_access_token'];
    $customer_id = ($source === 'message') ? $target_id : $actual_sender_id;

    // Fetch user name for Messenger if empty (Handle errors gracefully)
    if ($source === 'message' && empty($sender_name) && $customer_id) {
        try {
            $fb = new FacebookAPI();
            // Try name, for Instagram it might be username
            $fields = ($platform === 'instagram') ? 'username,name' : 'name';
            $res = $fb->makeRequest($customer_id, ['fields' => $fields], $access_token);
            if (isset($res['name'])) {
                $sender_name = $res['name'];
            } elseif (isset($res['username'])) {
                $sender_name = $res['username'];
            }
        } catch (Exception $e) {
            // If fetching name fails (permissions/unsupported), we use a placeholder instead of erroring out
            debugLog("Could not fetch sender name for $customer_id: " . $e->getMessage());
            $sender_name = ($platform === 'instagram') ? 'User' : 'Customer';
        }
    }

    // Ensure we have at least a default name if it's still empty
    if (empty($sender_name)) {
        $sender_name = ($platform === 'instagram') ? 'User' : 'Customer';
    }

    // 2. Fetch Conversation State & Rules
    $state = null;
    if ($customer_id) {
        $stmt = $pdo->prepare("SELECT * FROM bot_conversation_states WHERE page_id = ? AND user_id = ? AND reply_source = ? AND platform = ? LIMIT 1");
        $stmt->execute([$page_id, $customer_id, $source, $platform]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // STRICT HANDOVER CHECK: If user is in handover, DO NOT PROCESS ANYTHING.
    // The previous logic allowed keywords to break handover, which caused the bot to reactivate unintentionally.
    if ($state && $state['conversation_state'] === 'handover') {
        debugLog("processAutoReply: Bot silenced due to Handover State for user $customer_id");
        return;
    }

    // Fetch ALL active rules for this page
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE (page_id = ? OR page_id = ?) AND reply_source = ? AND platform = ? AND is_active = 1 ORDER BY trigger_type DESC, id DESC");
    $stmt->execute([$page['fb_page_id'], $page['ig_business_id'], $source, $platform]);
    $all_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $matched_rule = null;
    $reply_msg = '';
    $hide_comment = 0;
    $is_keyword_match = false;

    // 2.1 Keyword matching loop
    foreach ($all_rules as $rule) {
        if ($rule['trigger_type'] !== 'keyword')
            continue;

        $keywords = preg_split('/[,،\n]/u', $rule['keywords']); // Support commas and newlines
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw))
                continue;

            // Simplified Matching: Use mb_stripos for partial match (Better for Arabic)
            if (mb_stripos($incoming_text, $kw) !== false) {
                $matched_rule = $rule;
                $reply_msg = $rule['reply_message']; // Public Reply
                $hide_comment = (int) $rule['hide_comment'];
                $is_keyword_match = true;
                debugLog("Matched Keyword Rule: '$kw' (Rule ID: {$rule['id']})");
                break 2;
            }
        }
    }


    // 2.3 Global Anger Detection (Only if NO keyword match found)
    // If we have a direct keyword match, we assume specific intent and reply regardless of tone.
    // If we're falling back to default/AI, we check for anger first.
    if (!$reply_msg && $page['bot_ai_sentiment_enabled'] && !empty($page['bot_anger_keywords'])) {
        $anger_kws = explode(',', $page['bot_anger_keywords']);
        foreach ($anger_kws as $akw) {
            $akw = trim($akw);
            if (!empty($akw) && mb_stripos($incoming_text, $akw) !== false) {
                // Anger detected! Switch to handover
                $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, is_anger_detected, reply_source, platform) 
                                        VALUES (?, ?, ?, ?, NOW(), 'handover', 1, ?, ?) 
                                        ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), conversation_state = 'handover', is_anger_detected = 1");
                $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $source, $platform]);

                // Add Internal Notification
                $notify_title = 'handover_notification_title';
                $section_key = ($source === 'message') ? 'nav_messenger_bot' : 'nav_auto_reply';

                $notify_msg = json_encode([
                    'key' => 'handover_notification_msg',
                    'params' => [$page['page_name'], $section_key],
                    'param_keys' => [1]
                ]);

                $notify_link = ($source === 'message') ? 'user/page_messenger_bot.php' : 'user/page_auto_reply.php';
                addNotification($page['user_id'], $notify_title, $notify_msg, $notify_link);

                // --- SEND EMAIL ALERT ---
                triggerHandoverEmail($pdo, $page['user_id'], $page['page_name'], $source, $sender_name, $incoming_text);

                // Send Handover Reply if set (Anger case)
                if (!empty($page['bot_handover_reply'])) {
                    $fb = new FacebookAPI();
                    $h_msg = $page['bot_handover_reply'];

                    // 1. Spintax
                    if (strpos($h_msg, '|') !== false) {
                        $opts = explode('|', $h_msg);
                        $h_msg = trim($opts[array_rand($opts)]);
                    }

                    if ($source === 'message') {
                        // Messenger: Link Name
                        $p_msg = str_replace('{name}', $sender_name, $h_msg);
                        $fb->sendMessage($api_actor_id, $access_token, $customer_id, $p_msg);
                    } else {
                        // Comments: Smart Mention for Public, Name for Private
                        $mention = ($platform === 'instagram') ? '@' . $sender_name : '@[' . $customer_id . ']';
                        $pub_msg = str_replace('{name}', $mention, $h_msg);
                        $priv_msg = str_replace('{name}', $sender_name, $h_msg);

                        // Execute All
                        $fb->likeComment($target_id, $access_token, $platform, false);
                        $fb->replyToComment($target_id, $pub_msg, $access_token, $platform);
                        try {
                            $res_p = $fb->replyPrivateToComment($target_id, $priv_msg, $access_token, $platform);
                            debugLog("Handover Private Reply Result: " . json_encode($res_p));
                        } catch (Exception $ext) {
                            debugLog("Handover Private Reply ERROR: " . $ext->getMessage());
                        }
                    }
                }

                return; // SILENCE immediately
            }
        }
    }

    // 2.3 If no keyword match AND no anger silence, try Default Rule
    if (!$matched_rule) {
        debugLog("Looking for Default Rule for $platform ($source)...");
        foreach ($all_rules as $rule) {
            if ($rule['trigger_type'] === 'default') {
                $matched_rule = $rule;
                $reply_msg = $rule['reply_message'];
                $hide_comment = (int) $rule['hide_comment'];
                debugLog("Matched Default Rule (ID: {$rule['id']}) for $target_id");
                break;
            }
        }
    }

    if (!$matched_rule) {
        debugLog("NO RULE MATCHED (Keywords or Default) for user $customer_id on platform $platform source $source");
        return;
    }

    // Capture Private Reply Settings from the matched rule
    $private_reply_enabled = (int) ($matched_rule['private_reply_enabled'] ?? 0);
    $private_reply_text = $matched_rule['private_reply_text'] ?? '';
    // Capture Auto Like Settings (Support aliases for safety)
    $auto_like_comment = (int) ($matched_rule['auto_like_comment'] ?? $matched_rule['auto_like'] ?? 0);

    debugLog("Rule Settings for $target_id: Like=" . ($auto_like_comment ? "YES" : "NO") . ", Private=" . ($private_reply_enabled ? "YES" : "NO") . ", Platform=" . $platform);

    // --- QUICK WINS IMPLEMENTATION ---
    debugLog("STEP: Processing Spintax and Mentions...");
    if (strpos($reply_msg, '|') !== false) {
        $options = explode('|', $reply_msg);
        $reply_msg = trim($options[array_rand($options)]);
    }
    if ($private_reply_enabled && !empty($private_reply_text) && strpos($private_reply_text, '|') !== false) {
        $options = explode('|', $private_reply_text);
        $private_reply_text = trim($options[array_rand($options)]);
    }

    // 2. Mention User: Smart replacement based on source and destination
    // For the PUBLIC COMMENT: We use @[ID] (Blue mention)
    // For ANY MESSAGE (Private or Direct): We use the Name
    if ($source === 'comment') {
        // Public Reply (The actual comment)
        if (!empty($customer_id)) {
            $mention = ($platform === 'instagram') ? '@' . $sender_name : '@[' . $customer_id . ']';
            $reply_msg = str_replace('{name}', $mention, $reply_msg);
        }
        // Private Reply (The inbox message sent after comment)
        if (!empty($sender_name)) {
            $private_reply_text = str_replace('{name}', $sender_name, $private_reply_text);
        }
    } else {
        // Source is 'message' (Direct Messenger Bot)
        if (!empty($sender_name)) {
            $reply_msg = str_replace('{name}', $sender_name, $reply_msg);
            $private_reply_text = str_replace('{name}', $sender_name, $private_reply_text);
        }
    }

    // If no action at all
    if (empty($reply_msg) && empty($image_url) && !$auto_like_comment && !$hide_comment && !$private_reply_enabled) {
        debugLog("SILENCE: No reply content or action defined for matched rule ID: " . ($matched_rule['id'] ?? 'NONE'));
        return;
    }

    // 4. Advanced SaaS Logic (Repeat Detection)
    $is_ai_safe = (int) ($matched_rule['is_ai_safe'] ?? 1);

    // For Default Replies, we are more lenient with AI safety unless explicitly strict
    if ($matched_rule['trigger_type'] === 'default') {
        $is_ai_safe = 0;
    }

    // 4.1 Repeat Detection (ALWAYS runs if AI sentiment enabled, regardless of is_ai_safe)
    if ($page['bot_ai_sentiment_enabled'] && $customer_id && $state) {
        // B. Repeat Detection (Configurable Threshold)
        if ($state['last_bot_reply_text'] === $reply_msg) {
            $threshold = (int) ($page['bot_repetition_threshold'] ?? 3);
            if ($threshold < 1)
                $threshold = 3;

            $new_count = $state['repeat_count'] + 1;

            if ($new_count >= $threshold) {
                // Too many repeats! Switch to handover
                $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'handover', repeat_count = ?, last_user_message = ?, last_user_message_at = NOW(), user_name = ? WHERE id = ?");
                $stmt->execute([$new_count, $incoming_text, $sender_name, $state['id']]);

                // Add Internal Notification
                $notify_title = 'handover_notification_title';
                $section_key = ($source === 'message') ? 'nav_messenger_bot' : 'nav_auto_reply';

                $notify_msg = json_encode([
                    'key' => 'handover_notification_msg',
                    'params' => [$page['page_name'], $section_key],
                    'param_keys' => [1]
                ]);

                $notify_link = ($source === 'message') ? 'user/page_messenger_bot.php' : 'user/page_auto_reply.php';
                addNotification($page['user_id'], $notify_title, $notify_msg, $notify_link);

                // --- SEND EMAIL ALERT ---
                triggerHandoverEmail($pdo, $page['user_id'], $page['page_name'], $source, $sender_name, $incoming_text);

                // Send Handover Reply if set
                if (!empty($page['bot_handover_reply'])) {
                    $fb = new FacebookAPI();
                    $h_msg = $page['bot_handover_reply'];

                    if (strpos($h_msg, '|') !== false) {
                        $opts = explode('|', $h_msg);
                        $h_msg = trim($opts[array_rand($opts)]);
                    }

                    if ($source === 'message') {
                        $p_msg = str_replace('{name}', $sender_name, $h_msg);
                        $fb->sendMessage($api_actor_id, $access_token, $customer_id, $p_msg);
                    } else {
                        $pub_msg = str_replace('{name}', $mention, $h_msg);
                        $priv_msg = str_replace('{name}', $sender_name, $h_msg);

                        $fb->likeComment($target_id, $access_token, $platform, false);
                        $fb->replyToComment($target_id, $pub_msg, $access_token, $platform);
                        try {
                            $private_res = $fb->replyPrivateToComment($target_id, $priv_msg, $access_token, $platform);
                            debugLog("Private Reply Res for $target_id: " . json_encode($private_res));
                        } catch (Exception $e) {
                            debugLog("Private Reply ERROR for $target_id: " . $e->getMessage());
                        }
                    }
                }

                debugLog("HANDOVER: Repeat threshold reached ($new_count >= $threshold) for $customer_id");
                return; // SILENCE
            }
        }
    }

    // 4.2 User Repetition Threshold Check (Only for AI-safe rules)
    if ($page['bot_ai_sentiment_enabled'] && $is_ai_safe && $customer_id) {
        // If user sends the same thing too many times, we silence to avoid ban
        if ($page['bot_repetition_threshold'] > 0 && $state && $state['last_user_message'] === $incoming_text) {
            $reps = (int) ($state['repetition_count'] ?? 0);
            if ($reps >= $page['bot_repetition_threshold']) {
                debugLog("SILENCE: Repetition threshold reached ($reps) for $customer_id. Message: $incoming_text");
                return;
            }
        }
    }

    // 5. Decision Logic: Schedule & Cooldown
    debugLog("STEP: Decision Logic (Schedule & Cooldown)...");
    $bypass_schedule = (int) ($matched_rule['bypass_schedule'] ?? 0);
    $bypass_cooldown = (int) ($matched_rule['bypass_cooldown'] ?? 0);

    // Compatibility: If it's a keyword match and old global exclude is ON, force bypass
    if ($is_keyword_match && (int) ($page['bot_exclude_keywords'] ?? 0) === 1) {
        $bypass_schedule = 1;
        $bypass_cooldown = 1;
        debugLog("Applying Keyword Bypass for Schedule/Cooldown (Global Setting)");
    }

    // 5.1 Schedule Check
    if (!$bypass_schedule && $page['bot_schedule_enabled']) {
        date_default_timezone_set('Africa/Cairo');
        $now = date('H:i');
        $start = !empty($page['bot_schedule_start']) ? substr($page['bot_schedule_start'], 0, 5) : '00:00';
        $end = !empty($page['bot_schedule_end']) ? substr($page['bot_schedule_end'], 0, 5) : '23:59';

        $is_inside = ($start <= $end) ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end);
        if (!$is_inside) {
            debugLog("SILENCE: Outside working hours ($now) for $customer_id. Range: $start - $end");
            return;
        }
    }

    // 5.2 Cooldown / Human Takeover Logic
    $cooldown_seconds = (int) ($page['bot_cooldown_seconds'] ?? 0);
    $should_check_cooldown = ($cooldown_seconds > 0 && !$bypass_cooldown);

    if ($should_check_cooldown) {
        debugLog("Checking Cooldown ($cooldown_seconds s) for user $customer_id...");
        $thread_user_id = ($source === 'message') ? $target_id : $actual_sender_id;
        $fb = new FacebookAPI();

        // A. Check Messenger Conversation (Safety: try-catch + correct account)
        if ($thread_user_id) {
            try {
                // Instagram uses 'me/conversations' (via token), FB can use ID
                $conv_actor = ($platform === 'instagram') ? 'me' : $api_actor_id;
                $convs = $fb->makeRequest("{$conv_actor}/conversations", [
                    'fields' => 'messages.limit(5){id,from,created_time}'
                ], $access_token);

                if (isset($convs['data'][0]['messages']['data'])) {
                    foreach ($convs['data'][0]['messages']['data'] as $msg) {
                        $msg_sender_id = $msg['from']['id'] ?? '';
                        $fb_msg_id = $msg['id'] ?? '';

                        if ($msg_sender_id == $api_actor_id || $msg_sender_id == $page_id) {
                            $chk = $pdo->prepare("SELECT id FROM bot_sent_messages WHERE message_id = ? OR ? LIKE CONCAT('%', message_id, '%') LIMIT 1");
                            $chk->execute([$fb_msg_id, $fb_msg_id]);

                            if (!$chk->fetch()) {
                                $created_time = strtotime($msg['created_time']);
                                if ((time() - $created_time) < $cooldown_seconds) {
                                    debugLog("SILENCE: Human admin activity detected for $customer_id");
                                    return;
                                }
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                debugLog("Cooldown Check Issue: " . $e->getMessage());
            }
        }

        // B. Check Comment Replies
        if ($source === 'comment') {
            try {
                $replies = $fb->makeRequest("{$target_id}/comments", [
                    'fields' => 'id,from,created_time',
                    'limit' => 5
                ], $access_token);

                if (isset($replies['data'])) {
                    foreach ($replies['data'] as $reply) {
                        $reply_sender_id = $reply['from']['id'] ?? '';
                        $fb_reply_id = $reply['id'] ?? '';

                        if ($reply_sender_id == $api_actor_id || $reply_sender_id == $page_id) {
                            $chk = $pdo->prepare("SELECT id FROM bot_sent_messages WHERE message_id = ? OR ? LIKE CONCAT('%', message_id, '%') LIMIT 1");
                            $chk->execute([$fb_reply_id, $fb_reply_id]);

                            if (!$chk->fetch()) {
                                $created_time = strtotime($reply['created_time']);
                                if ((time() - $created_time) < $cooldown_seconds) {
                                    debugLog("SILENCE: Admin reply found on comment for $customer_id");
                                    return;
                                }
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                debugLog("Comment Cooldown Issue: " . $e->getMessage());
            }
        }
    }

    // 5. Execute Reply
    debugLog("STEP: Initializing Compliance Engine for $customer_id...");
    // COMPLIANCE CHECK: Init Engine
    $compliance = new ComplianceEngine($pdo, $page_id, $access_token);

    // A. Track this user interaction (opens window) - MUST BE BEFORE canSendMessage check!
    debugLog("STEP: Refreshing interaction time...");
    $compliance->refreshLastInteraction($customer_id, $source);

    // B. Check if we CAN send (Rate Limit + Protocol)
    debugLog("STEP: Checking Compliance Policy...");
    $msg_type = ($source === 'comment') ? 'COMMENT' : 'RESPONSE';
    $policy = $compliance->canSendMessage($customer_id, $msg_type);
    if (!$policy['allowed']) {
        $reason = $policy['reason'] ?? 'Unknown Policy';
        debugLog("SILENCE: Compliance Engine blocked message to $customer_id. Reason: $reason");
        return;
    }

    debugLog("STEP: Compliance PASSED. Preparing to send via Facebook API...");
    $fb = new FacebookAPI();
    $res = null;
    $buttons = isset($matched_rule['reply_buttons']) ? json_decode($matched_rule['reply_buttons'], true) : null;
    $image_url = isset($matched_rule['reply_image_url']) ? $matched_rule['reply_image_url'] : null;

    if ($source === 'message') {
        // Send image first if exists
        if (!empty($image_url)) {
            $fb->sendImageMessage($api_actor_id, $access_token, $target_id, $image_url);
        }

        // DEBUG: Log button data
        debugLog("BUTTONS DEBUG: Raw from DB: " . ($matched_rule['reply_buttons'] ?? 'NULL'));
        debugLog("BUTTONS DEBUG: Decoded: " . json_encode($buttons));
        debugLog("BUTTONS DEBUG: Is Array: " . (is_array($buttons) ? 'YES' : 'NO'));
        debugLog("BUTTONS DEBUG: Count: " . (is_array($buttons) ? count($buttons) : 0));

        // RESTORED FEATURE: Persistent Menu Logic
        // If the current reply HAS NO BUTTONS, try to append the "items" from the Main Menu rule (keyword: 'مهتم')
        // *SMART LOGIC*: Only append if the user INTERACTED WITH A BUTTON (Payload/Quick Reply).
        // This prevents buttons from appearing on every random text message.
        if ((!$buttons || !is_array($buttons) || count($buttons) === 0) && $is_payload_interaction) {

            // Only do this if we are NOT already in the main menu (avoid loop if main menu itself has no buttons - unlikely)
            if (mb_strpos($matched_rule['keywords'], 'مهتم') === false) {

                debugLog("BUTTONS DEBUG: No buttons in current rule. Attempting to fetch Main Menu buttons...");

                // Fetch rule with keyword 'مهتم' for this page & platform
                $stmt = $pdo->prepare("SELECT reply_buttons FROM auto_reply_rules WHERE page_id = ? AND platform = ? AND reply_source = 'message' AND trigger_type = 'keyword' AND keywords LIKE '%مهتم%' AND is_active = 1 LIMIT 1");
                $stmt->execute([$db_page_id, $platform]);
                $main_menu_rule = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($main_menu_rule && !empty($main_menu_rule['reply_buttons'])) {
                    $potential_buttons = json_decode($main_menu_rule['reply_buttons'], true);
                    if (is_array($potential_buttons) && count($potential_buttons) > 0) {
                        $buttons = $potential_buttons;
                        debugLog("BUTTONS DEBUG: Successfully loaded Main Menu buttons from 'مهتم' rule.");
                    }
                }
            }
        }

        // Then send text with buttons
        if ($buttons && is_array($buttons) && count($buttons) > 0) {
            debugLog("BUTTONS DEBUG: Sending WITH buttons");
            $res = $fb->sendButtonMessage($api_actor_id, $access_token, $target_id, $reply_msg, $buttons);
        } else {
            debugLog("BUTTONS DEBUG: Sending WITHOUT buttons");
            $res = $fb->sendMessage($api_actor_id, $access_token, $target_id, $reply_msg);
        }

        error_log("WEBHOOK DEBUG: Messenger Send Res: " . json_encode($res));
    } else {
        $res = $fb->replyToComment($target_id, $reply_msg, $access_token, $platform);

        // DEBUG: Log the result of the comment reply
        file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Comment Reply Res: " . json_encode($res) . " | ID: $target_id | Msg: $reply_msg\n", FILE_APPEND);

        if ($hide_comment) {
            $fb->hideComment($target_id, $access_token, true, $platform);
        }

        // --- NEW: PRIVATE REPLY TO COMMENT ---
        // For Instagram, private_replies only works for TSL (Top Level Comments) and NOT Story comments.
        // Story comments in webhooks often lack media info or have specific flags.
        $is_ig_reply = ($platform === 'instagram' && !empty($parent_id) && $parent_id !== $post_id);

        if ($private_reply_enabled && !empty($private_reply_text)) {
            if ($is_ig_reply) {
                debugLog("Skipping IG Private Reply: Nested comments not supported by API ($target_id)");
            } else {
                try {
                    $p_res = $fb->replyPrivateToComment($target_id, $private_reply_text, $access_token, $platform);
                    debugLog("Auto-Reply Private Result for $target_id: " . json_encode($p_res));
                    file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Private Reply Res: " . json_encode($p_res) . " | ID: $target_id\n", FILE_APPEND);
                } catch (Exception $ep) {
                    debugLog("Auto-Reply Private ERROR for $target_id: " . $ep->getMessage());
                }
            }
        }

        // --- NEW: AUTO LIKE COMMENT ---
        if ($auto_like_comment) {
            try {
                $like_res = $fb->likeComment($target_id, $access_token, $platform, $hide_comment);
                debugLog("Auto-Like Result for $target_id: " . json_encode($like_res));
                file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Auto-Like Res: " . json_encode($like_res) . " | ID: $target_id\n", FILE_APPEND);
            } catch (Exception $el) {
                debugLog("Auto-Like ERROR for $target_id: " . $el->getMessage());
            }
        }
    }

    // 7. Update Tracking (ID Log & Conversation State)
    $sent_id = $res['id'] ?? $res['message_id'] ?? null;
    if ($sent_id) {
        $rule_id = $matched_rule['id'] ?? null;
        $stmt = $pdo->prepare("INSERT IGNORE INTO bot_sent_messages (message_id, page_id, rule_id, reply_source, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sent_id, $page_id, $rule_id, $source, $customer_id]);

        // --- NEW: Increment Usage Count ---
        if ($rule_id) {
            try {
                // Check if usage_count exists first (safe update)
                $check = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'usage_count'")->fetchAll();
                if (count($check) > 0) {
                    $stmt = $pdo->prepare("UPDATE auto_reply_rules SET usage_count = COALESCE(usage_count, 0) + 1 WHERE id = ?");
                    $stmt->execute([$rule_id]);
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

    if ($customer_id) {
        $repeat_val = ($state && $state['last_bot_reply_text'] === $reply_msg) ? $state['repeat_count'] + 1 : 1;
        $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, last_bot_reply_text, repeat_count, reply_source, platform) 
                               VALUES (?, ?, ?, ?, NOW(), 'active', ?, 1, ?, ?) 
                               ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), last_bot_reply_text = ?, repeat_count = ?, conversation_state = 'active'");
        $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $reply_msg, $source, $platform, $reply_msg, $repeat_val]);
    }
}

function processModeration($pdo, $id, $comment_id, $message_text, $sender_name = '', $platform = 'facebook', $post_id = null)
{
    debugLog("processModeration START: Page=$id, Comment=$comment_id, Platform=$platform");

    // Get Moderation Settings
    // For Instagram, $id is the IG Business ID. Rules are saved under page_id column with platform='instagram'.
    // We remove ig_business_id from the query to avoid "column not found" errors
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE page_id = ? AND platform = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$id, $platform]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rules) {
        debugLog("No active moderation rules found for ID: $id on Platform: $platform");
        return false;
    }

    debugLog("Moderation Rules Found: Phones=" . ($rules['hide_phones'] ? 'ON' : 'OFF') . ", Links=" . ($rules['hide_links'] ? 'ON' : 'OFF') . ", Action=" . $rules['action_type']);

    $violation = false;
    $reason = "";

    // A. Check Phone Numbers
    if ($rules['hide_phones']) {
        // Matches typical numbers 8+ digits, supports Arabic digits ٠-٩
        $phone_pattern = '/(\+?[\d٠-٩]{1,4}[\s-]?[\d٠-٩]{7,14})|([\d٠-٩]{8,15})/u';
        if (preg_match($phone_pattern, $message_text)) {
            $violation = true;
            $reason = "تم اكتشاف رقم هاتف";
        }
    }

    // B. Check Links/URLs
    if (!$violation && $rules['hide_links']) {
        $link_pattern = '/(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9-]+\.(com|net|org|me|info|biz|co|app|xyz|site|online|link))/iu';
        if (preg_match($link_pattern, $message_text)) {
            $violation = true;
            $reason = "تم اكتشاف رابط";
        }
    }

    // C. Check Banned Keywords
    if (!$violation && !empty($rules['banned_keywords'])) {
        $keywords = preg_split('/[,،]/u', $rules['banned_keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (!empty($kw) && mb_stripos($message_text, $kw) !== false) {
                $violation = true;
                $reason = "كلمة محظورة: $kw";
                break;
            }
        }
    }

    if ($violation) {
        debugLog("Moderation VIOLATION Found: $reason");
        $fb = new FacebookAPI();

        // Find Token
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ? OR ig_business_id = ? LIMIT 1");
        $stmt->execute([$id, $id]);
        $token = $stmt->fetchColumn();

        if ($token) {
            $action = $rules['action_type'] ?? 'hide';
            if ($action === 'hide') {
                $res = $fb->hideComment($comment_id, $token, true, $platform);
                debugLog("Action HIDE result: " . json_encode($res));
            } else {
                $res = $fb->makeRequest($comment_id, [], $token, 'DELETE');
                debugLog("Action DELETE result: " . json_encode($res));
            }

            // Log the action to DB
            try {
                // Find the page owner (User ID) to ensure the log is visible to the right person
                $uStmt = $pdo->prepare("SELECT a.user_id FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE p.page_id = ? OR p.ig_business_id = ? LIMIT 1");
                $uStmt->execute([$id, $id]);
                $user_id_to_log = $uStmt->fetchColumn() ?: ($rules['user_id'] ?? 0);

                $stmt = $pdo->prepare("INSERT INTO fb_moderation_logs (user_id, page_id, post_id, comment_id, content, user_name, reason, action_taken, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $log_res = $stmt->execute([
                    (int) $user_id_to_log,
                    (string) $id,
                    (string) $post_id,
                    (string) $comment_id,
                    (string) $message_text,
                    (string) $sender_name,
                    (string) $reason,
                    (string) $action,
                    (string) $platform
                ]);
                debugLog("ULTIMATE LOGGING: User=$user_id_to_log, Page=$id, Status=" . ($log_res ? "SUCCESS" : "FAILED"));
            } catch (Exception $logEx) {
                debugLog("ULTIMATE LOGGING ERROR: " . $logEx->getMessage());
            }
        } else {
            debugLog("Moderation ERROR: No token found for Page/IG $id");
        }
        return true;
    }

    debugLog("RESULT: No violation found for Comment $comment_id");
    return false;
}



// ----------------------------------------------------------------------
// Evolution API (WhatsApp) Handler
// ----------------------------------------------------------------------
function handleEvolutionEvent($data, $pdo)
{
    $instanceName = $data['instance'] ?? '';
    $eventType = $data['event'] ?? '';

    switch ($eventType) {
        case 'connection.update':
            $state = $data['data']['state'] ?? '';
            $status = ($state === 'open') ? 'connected' : (($state === 'connecting') ? 'pairing' : 'disconnected');
            $stmt = $pdo->prepare("UPDATE wa_accounts SET status = ? WHERE instance_name = ?");
            $stmt->execute([$status, $instanceName]);
            break;

        case 'messages.upsert':
            $msg = $data['data'] ?? [];
            $remoteJid = $msg['key']['remoteJid'] ?? '';
            $fromMe = $msg['key']['fromMe'] ?? false;
            $text = $msg['message']['conversation'] ?? $msg['message']['extendedTextMessage']['text'] ?? '';

            if ($remoteJid && $text) {
                try {
                    $sender_name = $msg['pushName'] ?? 'WhatsApp User';
                    $senderType = $fromMe ? 'page' : 'user';
                    $cleanJid = explode('@', $remoteJid)[0];
                    $meta_id = $msg['key']['id'] ?? null;
                    updateUnifiedInbox($pdo, 'whatsapp', $instanceName, $cleanJid, $sender_name, $text, $senderType, $meta_id);
                } catch (Exception $e) {
                    debugLog("WA Unified Inbox Update Failed: " . $e->getMessage());
                }
            }
            break;
    }
}

function triggerHandoverEmail($pdo, $user_id, $page_name, $source, $sender_name, $incoming_text)
{
    $stmt = $pdo->prepare("SELECT email, username, preferences, smtp_config FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user)
        return;

    $smtp = json_decode($user['smtp_config'] ?: '{}', true);
    if (empty($smtp['enabled']))
        return;

    $prefs = json_decode($user['preferences'] ?: '{}', true);
    $user_lang = $prefs['lang'] ?? 'ar';

    $subject = sprintf(__('handover_alert_subject', $user_lang), $page_name);

    $body = "
    <div dir=\"" . ($user_lang === 'ar' ? 'rtl' : 'ltr') . "\" style=\"font-family: sans-serif; line-height: 1.6; color: #333;\">
        <h2 style=\"color: #4f46e5;\">" . __('handover_alert_subject', $user_lang) . "</h2>
        <p>" . sprintf(__('handover_alert_body_intro', $user_lang), $user['username'] ?? 'User') . "</p>
        <p>" . sprintf(__('handover_alert_body_msg', $user_lang), $page_name) . "</p>
        <hr style=\"border: none; border-top: 1px solid #eee; margin: 20px 0;\">
        <p><strong>" . __('customer_name', $user_lang) . ":</strong> " . htmlspecialchars($sender_name) . "</p>
        <p><strong>" . __('message_snippet', $user_lang) . ":</strong><br>
           <i style=\"color: #666;\">\"" . htmlspecialchars(mb_substr($incoming_text, 0, 200)) . "...\"</i></p>
        <p style=\"margin-top: 30px;\">
            <a href=\"https://" . ($_SERVER['HTTP_HOST'] ?? 'marketation.net') . "/user/\" style=\"background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">
                " . __('view_conversation', $user_lang) . "
            </a>
        </p>
    </div>";

    sendUserEmail($user_id, $user['email'], $subject, $body, $smtp);
}

/**
 * Updates the Unified Inbox tables and triggers Pusher real-time event
 */
function updateUnifiedInbox($pdo, $platform, $pageId, $senderId, $senderName, $messageText, $senderType = 'user', $metaMessageId = null)
{
    // 1. Find User ID
    $userId = null;
    if ($platform === 'whatsapp') {
        $stmt = $pdo->prepare("SELECT user_id FROM wa_accounts WHERE instance_name = ? LIMIT 1");
        $stmt->execute([$pageId]);
        $userId = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT a.user_id FROM fb_pages p JOIN fb_accounts a ON p.account_id = a.id WHERE p.page_id = ? OR p.ig_business_id = ? LIMIT 1");
        $stmt->execute([$pageId, $pageId]);
        $userId = $stmt->fetchColumn();
    }

    if (!$userId)
        return;

    // 2. Resolve Name if empty (for FB/IG)
    if (empty($senderName) && $platform !== 'whatsapp') {
        $senderName = ($platform === 'instagram') ? 'Instagram User' : 'Facebook User';
        // Optional: Could fetch from API if not too heavy
    }

    // 3. Upsert Conversation
    $stmt = $pdo->prepare("SELECT id FROM unified_conversations WHERE user_id = ? AND platform = ? AND client_psid = ?");
    $stmt->execute([$userId, $platform, $senderId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $convId = $existing['id'];
        $stmt = $pdo->prepare("UPDATE unified_conversations SET last_message_text = ?, last_message_time = NOW(), client_name = ?, page_id = ? WHERE id = ?");
        $stmt->execute([$messageText, $senderName, $pageId, $convId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO unified_conversations (user_id, platform, client_psid, client_name, last_message_text, last_message_time, page_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$userId, $platform, $senderId, $senderName, $messageText, $pageId]);
        $convId = $pdo->lastInsertId();
    }

    // 4. Save Message
    $stmt = $pdo->prepare("INSERT IGNORE INTO unified_messages (conversation_id, sender, message_text, meta_message_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$convId, $senderType, $messageText, $metaMessageId]);
    $msgId = $pdo->lastInsertId();

    if (!$msgId && $metaMessageId) {
        // If insert ignored because of duplicate meta_id, get the existing ID
        $stmt = $pdo->prepare("SELECT id FROM unified_messages WHERE meta_message_id = ?");
        $stmt->execute([$metaMessageId]);
        $msgId = $stmt->fetchColumn();
    }

    // 5. Trigger Pusher
    $eventData = [
        'conversation_id' => $convId,
        'message' => [
            'id' => $msgId,
            'sender' => $senderType,
            'message_text' => $messageText,
            'created_at' => date('Y-m-d H:i:s')
        ],
        'conversation' => [
            'id' => $convId,
            'client_name' => $senderName,
            'last_message_text' => $messageText,
            'last_message_time' => date('Y-m-d H:i:s'),
            'platform' => $platform
        ]
    ];
    triggerPusherEvent($userId, 'new-message', $eventData);
}
