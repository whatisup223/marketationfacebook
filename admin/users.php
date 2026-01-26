<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Handle User Deletion (Be careful with this in production!)
if (isset($_POST['delete_user'])) {
    $uid = $_POST['user_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$uid]);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <h1 class="text-3xl font-bold mb-8"><?php echo __('manage_users'); ?></h1>

        <div class="glass-card rounded-2xl p-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700/50">
                            <th class="pb-3 pl-2"><?php echo __('id'); ?></th>
                            <th class="pb-3"><?php echo __('name'); ?></th>
                            <th class="pb-3"><?php echo __('email'); ?></th>
                            <th class="pb-3"><?php echo __('role'); ?></th>
                            <th class="pb-3"><?php echo __('joined'); ?></th>
                            <th class="pb-3"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="py-4 pl-2 font-mono text-sm text-gray-500">#
                                    <?php echo $row['id']; ?>
                                </td>
                                <td class="py-4 font-medium flex items-center">
                                    <?php if ($row['avatar']): ?>
                                        <img src="../<?php echo $row['avatar']; ?>"
                                            class="w-8 h-8 rounded-lg object-cover mr-3 rtl:mr-0 rtl:ml-3 border border-indigo-500/30">
                                    <?php else: ?>
                                        <div
                                            class="w-8 h-8 rounded-lg bg-indigo-500/30 flex items-center justify-center text-xs mr-3 rtl:mr-0 rtl:ml-3">
                                            <?php echo mb_substr($row['name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>
                                <td class="py-4 text-gray-400">
                                    <?php echo htmlspecialchars($row['email']); ?>
                                </td>
                                <td class="py-4">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $row['role'] == 'admin' ? 'bg-purple-500/20 text-purple-400' : 'bg-gray-700 text-gray-300'; ?>">
                                        <?php echo __('role_' . $row['role']); ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-500 text-sm">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="py-4">
                                    <div class="flex items-center space-x-2">
                                        <button
                                            onclick="showUserDetails('<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['email']); ?>', '<?php echo __('role_' . $row['role']); ?>', '<?php echo date('M d, Y', strtotime($row['created_at'])); ?>', '<?php echo $row['avatar'] ? '../' . $row['avatar'] : ''; ?>')"
                                            class="p-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-indigo-400 transition-colors border border-gray-700"
                                            title="<?php echo __('view'); ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </button>

                                        <?php if ($row['role'] != 'admin'): ?>
                                            <form method="POST"
                                                onsubmit="return confirm('<?php echo __('delete_user_confirm'); ?>');"
                                                class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_user"
                                                    class="p-2 bg-red-900/20 hover:bg-red-900/40 rounded-lg text-red-400 transition-colors border border-red-500/20"
                                                    title="<?php echo __('delete'); ?>">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div id="userModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    onclick="closeUserModal(event)">
    <div class="glass-card rounded-3xl p-8 max-w-sm w-full shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">
        <!-- Background Pattern -->
        <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-br from-indigo-600/20 to-purple-600/20 -z-10">
        </div>

        <div class="flex flex-col items-center mb-6">
            <div id="modalAvatarContainer" class="relative mb-4">
                <img id="modalUserAvatar" src=""
                    class="w-24 h-24 rounded-3xl object-cover border-4 border-indigo-500 shadow-lg hidden">
                <div id="modalUserFallback"
                    class="w-24 h-24 rounded-3xl bg-indigo-600/20 flex items-center justify-center border-4 border-indigo-500 shadow-lg text-3xl font-bold text-indigo-400">
                    ?
                </div>
            </div>
            <h3 id="modalUserName" class="text-2xl font-extrabold text-white text-center"></h3>
            <span id="modalUserRole"
                class="mt-1 px-3 py-1 bg-indigo-500/20 text-indigo-400 rounded-full text-xs font-bold uppercase tracking-wider"></span>
        </div>

        <div class="space-y-4">
            <div class="bg-gray-900/40 rounded-2xl p-5 border border-white/5 space-y-4">
                <div class="flex items-center group">
                    <div
                        class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center mr-4 rtl:mr-0 rtl:ml-4 group-hover:bg-indigo-600/20 transition-colors">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">
                            <?php echo __('email'); ?>
                        </p>
                        <p id="modalUserEmail" class="text-sm text-gray-200 font-medium"></p>
                    </div>
                </div>

                <div class="flex items-center group">
                    <div
                        class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center mr-4 rtl:mr-0 rtl:ml-4 group-hover:bg-green-600/20 transition-colors">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-green-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">
                            <?php echo __('joined'); ?>
                        </p>
                        <p id="modalUserJoined" class="text-sm text-gray-200 font-medium"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8">
            <button onclick="closeUserModal()"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-indigo-600/20 active:scale-95">
                <?php echo __('close'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    function showUserDetails(name, email, role, joined, avatar) {
        document.getElementById('modalUserName').textContent = name;
        document.getElementById('modalUserEmail').textContent = email;
        document.getElementById('modalUserRole').textContent = role;
        document.getElementById('modalUserJoined').textContent = joined;

        const avatarImg = document.getElementById('modalUserAvatar');
        const fallback = document.getElementById('modalUserFallback');

        if (avatar) {
            avatarImg.src = avatar;
            avatarImg.classList.remove('hidden');
            fallback.classList.add('hidden');
        } else {
            fallback.textContent = name.charAt(0).toUpperCase();
            avatarImg.classList.add('hidden');
            fallback.classList.remove('hidden');
        }

        document.getElementById('userModal').classList.remove('hidden');
    }

    function closeUserModal(event) {
        if (!event || event.target.id === 'userModal') {
            document.getElementById('userModal').classList.add('hidden');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>