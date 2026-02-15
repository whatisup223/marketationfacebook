<?php
require_once 'includes/functions.php';

// Check if admin
if (!isLoggedIn() || !isAdmin()) {
    die("Access denied. Admins only.");
}

$pdo = getDB();

try {
    // 1. AI Advisor Settings Table
    $sql1 = "CREATE TABLE IF NOT EXISTS ai_advisor_settings (
        user_id INT PRIMARY KEY,
        business_name VARCHAR(255),
        business_description TEXT,
        products_services TEXT,
        tone_of_voice VARCHAR(50) DEFAULT 'friendly',
        is_active TINYINT(1) DEFAULT 0,
        custom_instructions TEXT, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql1);
    echo "Table 'ai_advisor_settings' created or already exists.<br>";

    // 2. Unified Conversations Table (Stores Analysis)
    $sql2 = "CREATE TABLE IF NOT EXISTS unified_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        platform ENUM('facebook', 'instagram') NOT NULL,
        client_psid VARCHAR(255) NOT NULL,
        client_name VARCHAR(255),
        last_message_text TEXT,
        last_message_time DATETIME,
        is_read TINYINT(1) DEFAULT 0,
        
        -- AI Fields
        ai_sentiment ENUM('positive', 'neutral', 'negative', 'angry') DEFAULT NULL,
        ai_intent VARCHAR(100) DEFAULT NULL,
        ai_summary TEXT, -- Brief summary of the context
        ai_next_best_action TEXT, -- Recommendation for the agent
        ai_suggested_replies JSON, -- Array of strings
        last_analyzed_at DATETIME,
        
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_client (user_id, platform, client_psid),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql2);
    echo "Table 'unified_conversations' created or already exists.<br>";

    // 3. Unified Messages Table (Stores Chat History for Context)
    $sql3 = "CREATE TABLE IF NOT EXISTS unified_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender ENUM('user', 'page') NOT NULL,
        message_text TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES unified_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql3);
    echo "Table 'unified_messages' created or already exists.<br>";

    echo "<h3>Success! Smart Inbox Database Setup Complete.</h3>";
    echo "<a href='user/dashboard.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>