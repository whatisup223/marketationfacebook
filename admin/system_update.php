<?php
// admin/system_update.php - System Update Manager
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$currentVersion = '1.0.0'; // Current version
if (file_exists(__DIR__ . '/../version.txt')) {
    $currentVersion = trim(file_get_contents(__DIR__ . '/../version.txt'));
}

// Initialize messages from session if any (PRG pattern)
$updateLog = isset($_SESSION['update_log']) ? $_SESSION['update_log'] : [];
// Clear session messages after retrieving
if (isset($_SESSION['update_log'])) {
    unset($_SESSION['update_log']);
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $newLog = [];

    if ($action === 'check_updates') {
        // Check for updates from GitHub
        $newLog[] = ['type' => 'info', 'message' => __('step_1') . '...'];

        $repoUrl = 'https://api.github.com/repos/whatisup223/marketationfacebook/commits/main';
        $ch = curl_init($repoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Marketation-Updater/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['sha'])) {
                $latestCommit = substr($data['sha'], 0, 7);
                $newLog[] = ['type' => 'success', 'message' => __('latest_commit') . ": $latestCommit"];
                // You might want to translate 'Message' or just show the commit message as is
                $newLog[] = ['type' => 'success', 'message' => $data['commit']['message']];
            } else {
                $newLog[] = ['type' => 'warning', 'message' => __('could_not_get_version')];
            }
        } else {
            $newLog[] = ['type' => 'error', 'message' => __('connection_failed') . " (HTTP $httpCode) $curlError"];
        }

    } elseif ($action === 'pull_code') {
        // Pull updates from GitHub
        $newLog[] = ['type' => 'info', 'message' => __('step_2') . '...'];

        $gitPath = realpath(__DIR__ . '/..');

        $output = [];
        $returnCode = 0;
        exec("cd \"$gitPath\" && git pull origin main 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $newLog[] = ['type' => 'success', 'message' => __('code_updated_success')];
            foreach ($output as $line) {
                // Ideally, output lines from git are in English, hard to translate dynamically
                $newLog[] = ['type' => 'info', 'message' => $line];
            }
        } else {
            $newLog[] = ['type' => 'error', 'message' => __('failed_pull_updates')];
            foreach ($output as $line) {
                $newLog[] = ['type' => 'error', 'message' => $line];
            }
        }

    } elseif ($action === 'run_migrations') {
        // Run database migrations
        $newLog[] = ['type' => 'info', 'message' => __('step_3') . '...'];

        $migrationsDir = __DIR__ . '/../migrations';
        if (is_dir($migrationsDir)) {
            $migrations = glob($migrationsDir . '/*.php');
            sort($migrations);

            foreach ($migrations as $migration) {
                $migrationName = basename($migration);
                $newLog[] = ['type' => 'info', 'message' => __('migration_running') . " $migrationName"];

                try {
                    ob_start();
                    include $migration;
                    $output = ob_get_clean();
                    $newLog[] = ['type' => 'success', 'message' => "✓ $migrationName " . __('migration_completed')];
                    if ($output) {
                        $newLog[] = ['type' => 'info', 'message' => $output];
                    }
                } catch (Exception $e) {
                    $newLog[] = ['type' => 'error', 'message' => "✗ $migrationName " . __('migration_failed') . ": " . $e->getMessage()];
                }
            }
        } else {
            $newLog[] = ['type' => 'warning', 'message' => __('no_migrations_found')];
        }

    } elseif ($action === 'fix_git') {
        // Fix Git Configuration (Initialize & Link)
        $newLog[] = ['type' => 'info', 'message' => __('migration_running') . ' Git Init...'];

        $gitPath = realpath(__DIR__ . '/..');
        $repoUrl = 'https://github.com/whatisup223/marketationfacebook.git'; // Public Repo
        $configFile = $gitPath . '/includes/db_config.php';

        // 1. SMART BACKUP: Save critical config in known location
        $configContent = null;
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            $newLog[] = ['type' => 'success', 'message' => 'Config backup created in memory.'];
        }

        $commands = [
            "cd \"$gitPath\" && git init",
            "cd \"$gitPath\" && git remote remove origin 2>&1",
            "cd \"$gitPath\" && git remote add origin $repoUrl",
            "cd \"$gitPath\" && git fetch origin",
            "cd \"$gitPath\" && git branch -M main",
            "cd \"$gitPath\" && git reset --hard origin/main" // Warning: This kills untracked files
        ];

        $allSuccess = true;
        foreach ($commands as $cmd) {
            $output = [];
            $returnCode = 0;
            exec($cmd . " 2>&1", $output, $returnCode);
            foreach ($output as $line) {
                if (strpos($line, 'error: No such remote') !== false)
                    continue;
                $newLog[] = ['type' => 'info', 'message' => $line];
            }
        }

        // 2. RESTORE: Put config back immediately
        if ($configContent) {
            file_put_contents($configFile, $configContent);
            $newLog[] = ['type' => 'success', 'message' => 'Config restored successfully.'];
        } else {
            $newLog[] = ['type' => 'warning', 'message' => 'No config file found to backup (db_config.php).'];
        }

        // Final verification
        if (file_exists("$gitPath/.git")) {
            $newLog[] = ['type' => 'success', 'message' => __('git_fix_success')];
        } else {
            $newLog[] = ['type' => 'error', 'message' => __('git_fix_failed')];
        }
    } elseif ($action === 'clean_files') {
        // Smart Cleanup
        $newLog[] = ['type' => 'info', 'message' => __('cleanup_running')];

        $gitPath = realpath(__DIR__ . '/..');

        // Command to clean untracked files and directories, excluding ignored files
        // -f: force, -d: directories
        $command = "cd \"$gitPath\" && git clean -fd 2>&1";

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $newLog[] = ['type' => 'success', 'message' => __('cleanup_success')];
            foreach ($output as $line) {
                if (trim($line)) {
                    $newLog[] = ['type' => 'info', 'message' => "Removed: " . $line];
                }
            }
            if (empty($output)) {
                $newLog[] = ['type' => 'info', 'message' => "System is already clean."];
            }
        } else {
            $newLog[] = ['type' => 'error', 'message' => __('cleanup_failed')];
            foreach ($output as $line) {
                $newLog[] = ['type' => 'error', 'message' => $line];
            }
        }
    }

    // Store log in session and redirect
    $_SESSION['update_log'] = $newLog;
    header("Location: system_update.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                    <?php echo __('system_update_manager'); ?>
                </h1>
                <p class="text-gray-400">
                    <?php echo __('current_version'); ?>:
                    <span
                        class="text-green-400 font-mono bg-green-500/10 px-2 py-0.5 rounded-lg border border-green-500/20"><?php echo $currentVersion; ?></span>
                </p>
            </div>

            <a href="backup.php"
                class="bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 px-4 py-2 rounded-xl transition-colors text-sm font-bold flex items-center gap-2 border border-indigo-500/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <?php echo __('backup_restore'); ?>
            </a>
        </div>

        <!-- Warning -->
        <div
            class="bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-6 py-4 rounded-2xl mb-8 flex items-start gap-4">
            <div class="p-2 bg-yellow-500/20 rounded-lg shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-lg mb-1"><?php echo __('backup_warning'); ?></h3>
                <p class="text-yellow-500/80 text-sm">
                    <?php echo __('backup_warning_msg'); ?>
                </p>
            </div>
        </div>

        <!-- Troubleshooting Action (Visible if Git error suspected or always) -->
        <div class="mb-8">
            <details class="group">
                <summary
                    class="flex items-center gap-2 cursor-pointer text-gray-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span
                        class="font-bold text-sm bg-gray-800/50 px-3 py-1 rounded-lg border border-white/5"><?php echo __('troubleshooting'); ?></span>
                </summary>
                <div
                    class="glass-card bg-red-500/5 border border-red-500/10 rounded-2xl p-6 mt-4 animate-in slide-in-from-top-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-white font-bold mb-1"><?php echo __('force_git_init'); ?></h4>
                            <p class="text-gray-400 text-sm"><?php echo __('force_git_desc'); ?></p>
                        </div>

                        <!-- Open Modal Button -->
                        <button type="button"
                            onclick="document.getElementById('git-fix-modal').classList.remove('hidden')"
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-bold text-xs shadow-lg transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                </path>
                            </svg>
                            <?php echo __('btn_fix_git'); ?>
                        </button>
                    </div>
                </div>
            </details>
        </div>

        <!-- Update Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Checking -->
            <form method="POST" class="contents">
                <input type="hidden" name="action" value="check_updates">
                <button type="submit"
                    class="glass-card p-6 rounded-2xl text-left hover:bg-white/5 transition-all group relative overflow-hidden w-full">
                    <div
                        class="absolute inset-0 bg-blue-600/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center text-blue-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1"><?php echo __('check_updates'); ?></h3>
                        <p class="text-gray-400 text-sm"><?php echo __('update_step_1_desc'); ?></p>
                    </div>
                </button>
            </form>

            <!-- Pulling -->
            <form method="POST" class="contents">
                <input type="hidden" name="action" value="pull_code">
                <button type="submit"
                    class="glass-card p-6 rounded-2xl text-left hover:bg-white/5 transition-all group relative overflow-hidden w-full">
                    <div
                        class="absolute inset-0 bg-green-600/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center text-green-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1"><?php echo __('pull_code'); ?></h3>
                        <p class="text-gray-400 text-sm"><?php echo __('update_step_2_desc'); ?></p>
                    </div>
                </button>
            </form>

            <!-- Migrating -->
            <form method="POST" class="contents">
                <input type="hidden" name="action" value="run_migrations">
                <button type="submit"
                    class="glass-card p-6 rounded-2xl text-left hover:bg-white/5 transition-all group relative overflow-hidden w-full">
                    <div
                        class="absolute inset-0 bg-purple-600/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center text-purple-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1"><?php echo __('run_migrations'); ?></h3>
                        <p class="text-gray-400 text-sm"><?php echo __('update_step_3_desc'); ?></p>
                    </div>
                </button>
            </form>

            <!-- Smart Cleanup -->
            <form method="POST" class="contents">
                <input type="hidden" name="action" value="clean_files">
                <button type="submit"
                    class="glass-card p-6 rounded-2xl text-left hover:bg-white/5 transition-all group relative overflow-hidden w-full">
                    <div
                        class="absolute inset-0 bg-orange-600/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center text-orange-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1"><?php echo __('smart_cleanup'); ?></h3>
                        <p class="text-gray-400 text-sm"><?php echo __('cleanup_desc'); ?></p>
                    </div>
                </button>
            </form>
        </div>

        <!-- Update Log -->
        <?php if (!empty($updateLog)): ?>
            <div class="glass-card rounded-2xl p-6 border border-gray-800">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <?php echo __('update_log'); ?>
                </h2>
                <div class="bg-black/30 rounded-xl p-4 font-mono text-sm max-h-96 overflow-y-auto space-y-2 border border-black/20"
                    dir="ltr"> <!-- Logs usually look better LTR -->
                    <?php foreach ($updateLog as $log): ?>
                        <div class="flex gap-3 items-start p-2 rounded-lg <?php
                        echo $log['type'] === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' :
                            ($log['type'] === 'error' ? 'bg-red-500/10 text-red-400 border border-red-500/20' :
                                ($log['type'] === 'warning' ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' :
                                    'bg-blue-500/10 text-blue-400 border border-blue-500/20'));
                        ?>">
                            <span class="mt-0.5 shrink-0">
                                <?php if ($log['type'] === 'success'): ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                        </path>
                                    </svg>
                                <?php elseif ($log['type'] === 'error'): ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                <?php elseif ($log['type'] === 'warning'): ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                        </path>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <span class="break-all"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Instructions -->
        <div class="mt-8 glass-card rounded-2xl p-6 border border-gray-800">
            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                    </path>
                </svg>
                <?php echo __('how_to_update'); ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700/50">
                    <div class="text-blue-400 font-bold mb-2 flex items-center gap-2">
                        <span
                            class="w-6 h-6 rounded-full bg-blue-500/20 flex items-center justify-center text-xs">1</span>
                        <?php echo __('check_updates'); ?>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo __('update_step_1_desc'); ?></p>
                </div>
                <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700/50">
                    <div class="text-green-400 font-bold mb-2 flex items-center gap-2">
                        <span
                            class="w-6 h-6 rounded-full bg-green-500/20 flex items-center justify-center text-xs">2</span>
                        <?php echo __('pull_code'); ?>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo __('update_step_2_desc'); ?></p>
                </div>
                <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700/50">
                    <div class="text-purple-400 font-bold mb-2 flex items-center gap-2">
                        <span
                            class="w-6 h-6 rounded-full bg-purple-500/20 flex items-center justify-center text-xs">3</span>
                        <?php echo __('run_migrations'); ?>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo __('update_step_3_desc'); ?></p>
                </div>
                <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700/50">
                    <div class="text-orange-400 font-bold mb-2 flex items-center gap-2">
                        <span
                            class="w-6 h-6 rounded-full bg-orange-500/20 flex items-center justify-center text-xs">4</span>
                        <?php echo __('smart_cleanup'); ?>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo __('cleanup_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WARNING MODAL -->
<div id="git-fix-modal"
    class="fixed inset-0 z-[100] hidden bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-gray-900 border border-red-500/30 w-full max-w-lg rounded-3xl p-8 shadow-2xl animate-in fade-in zoom-in duration-300">
        <div
            class="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 mx-auto mb-6 ring-4 ring-red-500/20">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-white text-center mb-3">
            ⚠️ <?php echo __('backup_warning'); ?>
        </h3>
        <p class="text-gray-300 text-center text-sm mb-6 leading-relaxed">
            <?php echo __('backup_warning_msg'); ?> <br><br>
            <span class="text-red-400 font-bold block bg-red-500/10 p-2 rounded-lg">
                <?php echo __('force_git_desc'); ?>
            </span>
        </p>

        <div class="space-y-3">
            <a href="backup.php"
                class="w-full py-3.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg shadow-green-600/20 transition-all flex items-center justify-center gap-2 group">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                    </path>
                </svg>
                <?php echo __('take_backup_now'); ?>
            </a>

            <form method="POST">
                <input type="hidden" name="action" value="fix_git">
                <button type="submit"
                    class="w-full py-3.5 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white font-bold rounded-xl border border-red-500/20 transition-all flex items-center justify-center gap-2">
                    <span><?php echo __('confirm_git_fix'); ?></span>
                </button>
            </form>

            <button onclick="document.getElementById('git-fix-modal').classList.add('hidden')"
                class="w-full text-gray-500 text-sm font-medium hover:text-white transition-colors py-2">
                <?php echo __('cancel'); ?>
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>