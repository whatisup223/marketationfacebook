<?php
// user/page_inbox_search.php
require_once __DIR__ . '/../includes/functions.php';

// Check Auth
if (!isLoggedIn()) {
    http_response_code(403);
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$q = trim($_POST['q'] ?? '');
if (strlen($q) < 2)
    exit('');

// Identify Current Page ID (to possibly keep consistent layout or show context)
// But user requested "Search in ALL pages" so we will join to see source page.
$current_page_id = intval($_POST['current_page_id'] ?? 0);

try {
    // Search query: Find leads in ANY page owned by this User
    // We join fb_pages ensuring the page belongs to an account owned by User
    $sql = "
        SELECT l.*, p.page_name 
        FROM fb_leads l
        JOIN fb_pages p ON l.page_id = p.id
        JOIN fb_accounts a ON p.account_id = a.id
        WHERE a.user_id = ? 
        AND (l.fb_user_name LIKE ? OR l.fb_user_id LIKE ?)
        ORDER BY l.last_interaction DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $term = "%$q%";
    $stmt->execute([$user_id, $term, $term]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        echo '';
        exit;
    }

    foreach ($leads as $lead) {
        // We render rows similar to page_inbox.php but maybe adding a "Page Name" badge if it differs
        ?>
        <tr class="hover:bg-indigo-600/5 transition-all duration-200 group cursor-pointer" onclick="toggleRow(this)">
            <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center">
                    <input type="checkbox" name="leads[]" value="<?php echo $lead['id']; ?>"
                        class="lead-checkbox w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-800 transition-all cursor-pointer"
                        onclick="event.stopPropagation()">
                </div>
            </td>
            <td class="px-6 py-4 text-start">
                <div class="flex items-center gap-4">
                    <div
                        class="w-10 h-10 rounded-full bg-[#1877F2] flex items-center justify-center text-white shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform ring-2 ring-white/10 shrink-0">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z" />
                        </svg>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-start">
                <div class="flex flex-col">
                    <span class="text-white font-bold group-hover:text-indigo-400 transition-colors">
                        <?php echo htmlspecialchars($lead['fb_user_name'] ?? 'Unknown'); ?>
                    </span>
                    <!-- Show Source Page Name if searching globally -->
                    <span class="text-xs text-gray-400 mt-1">
                        via <span class="text-indigo-300">
                            <?php echo htmlspecialchars($lead['page_name']); ?>
                        </span>
                    </span>
                </div>
            </td>
            <td class="px-6 py-4 text-start">
                <div class="flex items-center gap-2">
                    <code
                        class="px-2 py-1 rounded bg-black/30 text-xs font-mono text-indigo-300 border border-indigo-500/20 group-hover:border-indigo-500/50 transition-colors">
                                <?php echo htmlspecialchars($lead['fb_user_id']); ?>
                            </code>
                    <button onclick="copyToClipboard('<?php echo $lead['fb_user_id']; ?>', event)"
                        class="p-1 hover:bg-white/10 rounded transition-colors text-gray-400 hover:text-white" title="Copy ID">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                            </path>
                        </svg>
                    </button>
                </div>
            </td>
            <td class="px-6 py-4 text-start">
                <span class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">
                    <?php echo $lead['last_interaction']; ?>
                </span>
            </td>
        </tr>
        <?php
    }

} catch (PDOException $e) {
    error_log("Search Error: " . $e->getMessage());
    echo '<tr><td colspan="5" class="text-center text-red-400">Database Error</td></tr>';
}
