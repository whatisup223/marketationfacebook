<?php
require_once __DIR__ . '/../includes/functions.php';

// Production Settings: Increase limits for large backups
@ini_set('memory_limit', '512M'); // Attempt to increase RAM limit
@ini_set('max_execution_time', 300); // Attempt to allow 5 minutes for execution
@set_time_limit(300);

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();
$success = '';
$error = '';

$backupDir = __DIR__ . '/../backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// ----------------------
// BACKEND LOGIC
// ----------------------

// 1. Create Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = 'backup_' . $timestamp . '_' . uniqid() . '.json';
        $backupFile = $backupDir . '/' . $backupName;

        // DB & File Logic (Simplified for brevity, assuming previous logic works)
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tableName = $row[0];
            $createTable = $pdo->query("SHOW CREATE TABLE `$tableName`")->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->query("SELECT * FROM `$tableName`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tables[$tableName] = ['structure' => $createTable['Create Table'], 'data' => $rows];
        }

        $uploads = [];
        $uploadsDir = __DIR__ . '/../uploads';
        if (file_exists($uploadsDir)) {
            $uploads = backupDirectory($uploadsDir);
        }

        $dbConfigFile = __DIR__ . '/../includes/db_config.php';
        $dbConfig = file_exists($dbConfigFile) ? file_get_contents($dbConfigFile) : '';

        $backupData = [
            'created_at' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'site_name' => getSetting('site_name_ar', 'Unknown'),
            'database' => $tables,
            'uploads' => $uploads,
            'db_config' => $dbConfig,
            'version' => '1.0'
        ];

        if (file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $success = __('backup_created');
        } else {
            throw new Exception("Could not write backup file.");
        }

    } catch (Exception $e) {
        $error = __('backup_failed') . ': ' . $e->getMessage();
    }
}

// 2. Delete Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup_name'])) {
    $fileToDelete = trim($_POST['delete_backup_name']);
    $fileToDelete = basename($fileToDelete);
    $targetPath = $backupDir . '/' . $fileToDelete;

    if (file_exists($targetPath) && is_file($targetPath) && pathinfo($targetPath, PATHINFO_EXTENSION) === 'json') {
        if (unlink($targetPath)) {
            header("Location: backup.php?msg=deleted&t=" . time());
            exit;
        } else {
            $error = __('backup_delete_failed');
        }
    } else {
        $error = __('backup_delete_failed') . ' (File not found or invalid)';
    }
}

// 3. Restore Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    try {
        $uploadedFile = $_FILES['backup_file'];

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($lang === 'ar' ? 'خطأ في رفع الملف' : 'Error uploading file');
        }
        if (pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) !== 'json') {
            throw new Exception($lang === 'ar' ? 'يجب أن يكون الملف بصيغة JSON' : 'File must be in JSON format');
        }
        $jsonContent = file_get_contents($uploadedFile['tmp_name']);
        $backupData = json_decode($jsonContent, true);

        if (!$backupData)
            throw new Exception('Invalid JSON');

        // Restore Database
        if (isset($backupData['database'])) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            foreach ($backupData['database'] as $tableName => $tableData) {
                $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                $pdo->exec($tableData['structure']);
                if (!empty($tableData['data'])) {
                    foreach ($tableData['data'] as $row) {
                        $columns = '`' . implode('`, `', array_keys($row)) . '`';
                        $values = array_map(function ($value) use ($pdo) {
                            return $value === null ? 'NULL' : $pdo->quote($value);
                        }, array_values($row));
                        $pdo->exec("INSERT INTO `$tableName` ($columns) VALUES (" . implode(', ', $values) . ")");
                    }
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        }

        // Restore Uploads
        if (isset($backupData['uploads'])) {
            $uploadsTarget = __DIR__ . '/../uploads';
            if (file_exists($uploadsTarget))
                deleteDirectory($uploadsTarget);
            mkdir($uploadsTarget, 0755, true);
            restoreDirectory($backupData['uploads'], $uploadsTarget);
        }

        // Restore Config
        if (isset($backupData['db_config'])) {
            file_put_contents(__DIR__ . '/../includes/db_config.php', $backupData['db_config']);
        }

        $success = __('backup_restored');
    } catch (Exception $e) {
        $error = __('restore_failed') . ': ' . $e->getMessage();
    }
}

// Handle Messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = __('backup_deleted_success');
}

// Download
if (isset($_GET['download'])) {
    $f = basename($_GET['download']);
    $p = $backupDir . '/' . $f;
    if (file_exists($p) && pathinfo($p, PATHINFO_EXTENSION) === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p);
        exit;
    }
}

// List Backups
$backups = [];
if (file_exists($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json' && strpos($file, 'backup_') === 0) {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'date' => filemtime($backupDir . '/' . $file),
            ];
        }
    }
    usort($backups, function ($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Helpers
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
function backupDirectory($dir, $basePath = null)
{
    if ($basePath === null)
        $basePath = $dir;
    $result = [];
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..')
            continue;
        $path = $dir . '/' . $item;
        $rel = substr($path, strlen($basePath) + 1);
        if (is_dir($path))
            $result = array_merge($result, backupDirectory($path, $basePath));
        else
            $result[$rel] = base64_encode(file_get_contents($path));
    }
    return $result;
}
function restoreDirectory($files, $targetDir)
{
    foreach ($files as $rel => $content) {
        $full = $targetDir . '/' . $rel;
        if (!file_exists(dirname($full)))
            mkdir(dirname($full), 0755, true);
        file_put_contents($full, base64_decode($content));
    }
}
function deleteDirectory($dir)
{
    if (!file_exists($dir))
        return true;
    if (!is_dir($dir))
        return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..')
            continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
            return false;
    }
    return rmdir($dir);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Progress Modal Styles */
    .progress-container {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .progress-container.active {
        display: flex;
    }

    .progress-box {
        background: #1e293b;
        border: 1px solid #6366f1;
        border-radius: 1rem;
        padding: 2rem;
        min-width: 350px;
        text-align: center;
    }

    .spinner {
        border: 4px solid rgba(255, 255, 255, 0.1);
        border-left-color: #6366f1;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    /* Delete Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 9990;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-overlay.open {
        display: flex;
    }

    .modal-content {
        background: #1f2937;
        border: 1px solid #374151;
        border-radius: 1rem;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: scale(0.95);
        transition: transform 0.2s;
    }

    .modal-overlay.open .modal-content {
        transform: scale(1);
    }
</style>

<!-- Create Progress Modal -->
<div id="progressModal" class="progress-container">
    <div class="progress-box">
        <div class="spinner"></div>
        <h3 class="text-xl font-bold text-white mb-2"><?php echo __('creating_backup'); ?></h3>
        <p class="text-gray-300 text-sm mb-4" id="progressMessage">
            <?php echo $lang === 'ar' ? 'يرجى الانتظار...' : 'Please wait...'; ?></p>
        <div class="w-full bg-gray-700 rounded-full h-2.5 dark:bg-gray-700">
            <div class="bg-blue-600 h-2.5 rounded-full" style="width: 0%" id="progressBar"></div>
        </div>
        <p class="text-gray-400 text-xs mt-2" id="progressPercent">0%</p>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-white mb-2" id="modal-title">
                <?php echo $lang === 'ar' ? 'تأكيد الحذف' : 'Delete Confirmation'; ?>
            </h3>
            <div class="mt-2 text-sm text-gray-400">
                <p><?php echo $lang === 'ar' ? 'هل أنت متأكد أنك تريد حذف هذه النسخة الاحتياطية؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to delete this backup? This action cannot be undone.'; ?>
                </p>
                <p class="mt-2 text-indigo-400 font-mono text-xs" id="deleteFileName"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-center gap-3">
            <button type="button" onclick="closeDeleteModal()"
                class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                <?php echo $lang === 'ar' ? 'إلغاء' : 'Cancel'; ?>
            </button>
            <form method="POST" class="inline">
                <input type="hidden" name="delete_backup_name" id="deleteInputName" value="">
                <button type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors font-bold shadow-lg">
                    <?php echo $lang === 'ar' ? 'نعم، احذف' : 'Yes, Delete'; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="flex min-h-screen pt-4">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8 relative overflow-hidden">
        <!-- Animated Background Blobs -->
        <div
            class="absolute top-0 -left-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob pointer-events-none">
        </div>
        <div
            class="absolute top-0 -right-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000 pointer-events-none">
        </div>
        <div
            class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000 pointer-events-none">
        </div>

        <!-- Header Section -->
        <div class="mb-10 relative z-10 animate-fade-in">
            <div class="flex items-center justify-between mb-4">
                <div
                    class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                    </span>
                    <span
                        class="text-cyan-300 text-xs font-bold uppercase tracking-widest"><?php echo __('backup_restore'); ?></span>
                </div>
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">
                <?php echo __('backup_restore'); ?>
            </h1>
            <p class="text-gray-400 text-lg"><?php echo __('backup_restore_desc'); ?></p>
        </div>

        <?php if ($success): ?>
            <div
                class="bg-green-500/10 border border-green-500/50 text-green-400 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 relative z-10 animate-fade-in backdrop-blur-md">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                class="bg-red-500/10 border border-red-500/50 text-red-400 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 relative z-10 animate-fade-in backdrop-blur-md">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Create & Restore Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10 relative z-10">
            <!-- Create Backup Card -->
            <div class="group relative animate-fade-in" style="animation-delay: 100ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-indigo-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-indigo-500/30 transition-all duration-500 overflow-hidden flex flex-col h-full">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-indigo-600/5 border border-white/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(99,102,241,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                                </path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?php echo $lang === 'ar' ? 'إنشاء' : 'Create'; ?>
                        </span>
                    </div>
                    <h2 class="text-2xl font-black text-white mb-3 tracking-tight"><?php echo __('create_backup'); ?>
                    </h2>
                    <p class="text-gray-400 mb-6 leading-relaxed flex-grow"><?php echo __('backup_includes'); ?> (JSON)
                    </p>

                    <form method="POST" id="createBackupForm" class="mt-auto">
                        <input type="hidden" name="create_backup" value="1">
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-4 px-6 rounded-2xl transition-all shadow-lg transform hover:-translate-y-1 hover:shadow-indigo-500/50 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            <?php echo __('create_backup'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Restore Backup Card -->
            <div class="group relative animate-fade-in" style="animation-delay: 200ms;">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-purple-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-30 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-purple-500/30 transition-all duration-500 overflow-hidden flex flex-col h-full">
                    <div class="flex items-start justify-between mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500/20 to-purple-600/5 border border-white/10 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform duration-500 shadow-2xl">
                            <svg class="w-8 h-8 drop-shadow-[0_0_10px_rgba(168,85,247,0.5)]" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-500/10 text-purple-400 border border-purple-500/20">
                            <?php echo $lang === 'ar' ? 'استعادة' : 'Restore'; ?>
                        </span>
                    </div>
                    <h2 class="text-2xl font-black text-white mb-3 tracking-tight"><?php echo __('restore_backup'); ?>
                    </h2>

                    <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-2xl p-4 mb-6">
                        <p class="text-sm text-yellow-400 flex items-start gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                            <?php echo __('restore_warning'); ?>
                        </p>
                    </div>

                    <form method="POST" enctype="multipart/form-data"
                        onsubmit="return confirm('<?php echo __('confirm_restore'); ?>');" class="mt-auto">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <input type="file" name="backup_file" accept=".json" required
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                    onchange="document.getElementById('fileNameDisplay').textContent = this.files[0].name">
                                <div
                                    class="w-full glass-card border border-white/10 text-gray-400 rounded-2xl px-4 py-4 flex items-center justify-between hover:border-purple-500/30 transition-all">
                                    <span id="fileNameDisplay"
                                        class="truncate text-sm font-medium"><?php echo $lang === 'ar' ? 'اختر ملف...' : 'Choose file...'; ?></span>
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <button type="submit" name="restore_backup"
                                class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 text-white font-bold py-4 px-6 rounded-2xl transition-all shadow-lg transform hover:-translate-y-1 hover:shadow-purple-500/50 whitespace-nowrap">
                                <?php echo __('upload_backup'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backup List -->
        <div class="relative z-10 animate-fade-in" style="animation-delay: 300ms;">
            <div class="group relative">
                <div
                    class="absolute -inset-1 bg-gradient-to-br from-cyan-500 to-transparent rounded-[2rem] blur opacity-0 group-hover:opacity-20 transition duration-500">
                </div>
                <div
                    class="relative glass-card p-8 rounded-[2rem] border border-white/10 hover:border-cyan-500/20 transition-all duration-500">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-cyan-600/5 border border-white/10 flex items-center justify-center text-cyan-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-white tracking-tight">
                                    <?php echo __('backup_list'); ?></h2>
                                <p class="text-sm text-gray-500"><?php echo count($backups); ?>
                                    <?php echo $lang === 'ar' ? 'نسخة احتياطية' : 'backups'; ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($backups)): ?>
                        <div class="text-center py-16">
                            <div
                                class="w-20 h-20 mx-auto mb-6 rounded-3xl bg-gradient-to-br from-gray-700/20 to-gray-800/5 border border-white/10 flex items-center justify-center">
                                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                    </path>
                                </svg>
                            </div>
                            <p class="text-gray-500 text-lg font-medium"><?php echo __('no_backups'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-gray-400 border-b border-gray-700/50">
                                        <th class="pb-4 pl-2 text-xs font-bold uppercase tracking-wider">
                                            <?php echo __('backup_name'); ?></th>
                                        <th class="pb-4 text-xs font-bold uppercase tracking-wider">
                                            <?php echo __('backup_size'); ?></th>
                                        <th class="pb-4 text-xs font-bold uppercase tracking-wider">
                                            <?php echo __('backup_date'); ?></th>
                                        <th class="pb-4 text-xs font-bold uppercase tracking-wider text-center">
                                            <?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/50">
                                    <?php foreach ($backups as $backup): ?>
                                        <tr class="hover:bg-white/5 transition-all duration-300 group">
                                            <td class="py-4 pl-2 font-mono text-sm text-gray-300 group-hover:text-white transition-colors"
                                                dir="ltr">
                                                <?php echo htmlspecialchars($backup['name']); ?>
                                            </td>
                                            <td class="py-4 text-gray-400 text-sm font-medium">
                                                <?php echo formatBytes($backup['size']); ?>
                                            </td>
                                            <td class="py-4 text-gray-500 text-sm font-medium">
                                                <?php echo date('M d, Y H:i', $backup['date']); ?>
                                            </td>
                                            <td class="py-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="?download=<?php echo urlencode($backup['name']); ?>"
                                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 hover:text-blue-300 text-sm font-bold border border-blue-500/20 hover:border-blue-500/30 transition-all duration-300">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                            </path>
                                                        </svg>
                                                        <?php echo __('download_backup'); ?>
                                                    </a>
                                                    <button type="button"
                                                        onclick="openDeleteModal('<?php echo htmlspecialchars($backup['name']); ?>')"
                                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 text-sm font-bold border border-red-500/20 hover:border-red-500/30 transition-all duration-300">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                        <?php echo __('delete_backup'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Create Backup Progress Logic
    document.getElementById('createBackupForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const modal = document.getElementById('progressModal');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        modal.classList.add('active');
        let progress = 0;
        const interval = setInterval(() => {
            if (progress < 90) {
                progress += Math.random() * 5;
                progressBar.style.width = progress + '%';
                progressPercent.textContent = Math.round(progress) + '%';
            }
        }, 300);

        const formData = new FormData(this);
        fetch('backup.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(text => {
                clearInterval(interval);
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                window.location.reload();
            })
            .catch(err => {
                clearInterval(interval);
                alert('Error');
                modal.classList.remove('active');
            });
    });

    // Delete Modal Logic
    function openDeleteModal(fileName) {
        document.getElementById('deleteInputName').value = fileName;
        document.getElementById('deleteFileName').textContent = fileName;
        document.getElementById('deleteModal').classList.add('open');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('open');
    }

    // Close modal on click outside
    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) closeDeleteModal();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>