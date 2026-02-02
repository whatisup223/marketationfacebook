<?php
include '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$page_title = __('settings');
include '../includes/header.php';
?>

<div class="flex min-h-screen pt-4">
    <?php include '../includes/user_sidebar.php'; ?>

    <div class="flex-1 min-w-0 p-4 md:p-8">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Header -->
            <h2 class="text-3xl font-black text-white">
                <?php echo __('settings'); ?>
            </h2>

            <!-- Tabs Navigation -->
            <div x-data="{ activeTab: 'smtp' }">
                <div class="border-b border-white/10 mb-8">
                    <nav class="-mb-px flex space-x-8">
                        <button @click="activeTab = 'smtp'"
                            :class="{ 'border-indigo-500 text-indigo-400': activeTab === 'smtp', 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300': activeTab !== 'smtp' }"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                            <?php echo __('smtp_settings'); ?>
                        </button>
                        <!-- More tabs can be added here in the future -->
                    </nav>
                </div>

                <!-- SMTP Settings Tab -->
                <div x-show="activeTab === 'smtp'" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">

                    <div class="glass-panel p-8 rounded-[2rem] border border-white/10 relative overflow-hidden"
                        x-data="smtpSettings()">

                        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 blur-[100px] pointer-events-none">
                        </div>

                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">
                                    <?php echo __('smtp_configuration'); ?>
                                </h3>
                                <p class="text-gray-400 text-sm">
                                    <?php echo __('smtp_user_config_desc'); ?>
                                </p>
                            </div>

                            <!-- Enable/Disable Toggle -->
                            <div class="flex items-center gap-3 bg-black/20 p-2 rounded-xl border border-white/5">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                    <?php echo __('enable_email_alerts'); ?>
                                </span>
                                <div
                                    class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                                    <input type="checkbox" x-model="enabled" id="toggleSmtp"
                                        class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 right-6 transition-all duration-300" />
                                    <label for="toggleSmtp" :class="enabled ? 'bg-indigo-600' : 'bg-gray-700'"
                                        class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors"></label>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="saveSettings()" class="space-y-6 relative z-10">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Host -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_host'); ?>
                                    </label>
                                    <input type="text" x-model="host"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>

                                <!-- Port -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_port'); ?>
                                    </label>
                                    <input type="number" x-model="port"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>

                                <!-- Encryption -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_encryption'); ?>
                                    </label>
                                    <select x-model="encryption"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>

                                <!-- Auth User -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_username'); ?>
                                    </label>
                                    <input type="text" x-model="username"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>

                                <!-- Auth Pass -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_password'); ?>
                                    </label>
                                    <input type="password" x-model="password"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>

                                <!-- From Email -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_from_email'); ?>
                                    </label>
                                    <input type="email" x-model="from_email"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>

                                <!-- From Name -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                        <?php echo __('smtp_from_name'); ?>
                                    </label>
                                    <input type="text" x-model="from_name"
                                        class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white focus:ring-2 focus:ring-indigo-500 transition-all">
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col md:flex-row items-center gap-4 pt-6 border-t border-white/5">
                                <button type="submit" :disabled="saving"
                                    class="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl transition-all flex items-center justify-center gap-2">
                                    <span x-show="saving"
                                        class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                    <span
                                        x-text="saving ? '<?php echo __('saving'); ?>...' : '<?php echo __('save_settings'); ?>'"></span>
                                </button>

                                <button type="button" @click="testSmtp()" :disabled="testing || !host"
                                    class="w-full md:w-auto px-8 py-3 bg-white/5 hover:bg-white/10 text-white font-bold rounded-xl transition-all flex items-center justify-center gap-2">
                                    <span x-show="testing"
                                        class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                    <span
                                        x-text="testing ? '<?php echo __('testing'); ?>...' : '<?php echo __('test_smtp_settings'); ?>'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <style>
            /* Custom Toggle Styles */
            .toggle-checkbox:checked {
                right: 0;
            }

            .toggle-checkbox {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
        </style>

        <script>
            function smtpSettings() {
                return {
                    enabled: false,
                    host: '',
                    port: '587',
                    encryption: 'tls',
                    username: '',
                    password: '',
                    from_email: '',
                    from_name: '',
                    saving: false,
                    testing: false,

                    init() {
                        this.fetchSettings();
                    },

                    fetchSettings() {
                        fetch('ajax_settings.php?action=fetch_smtp')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.config) {
                                    const c = data.config;
                                    this.enabled = c.enabled === true || c.enabled === 'true' || c.enabled === 1;
                                    this.host = c.host || '';
                                    this.port = c.port || '587';
                                    this.encryption = c.encryption || 'tls';
                                    this.username = c.username || '';
                                    this.password = c.password || ''; // Usually stored empty or encrypted, logic depends on requirement
                                    this.from_email = c.from_email || '';
                                    this.from_name = c.from_name || '';
                                }
                            });
                    },

                    saveSettings() {
                        this.saving = true;
                        const formData = new FormData();
                        formData.append('enabled', this.enabled ? 1 : 0);
                        formData.append('host', this.host);
                        formData.append('port', this.port);
                        formData.append('encryption', this.encryption);
                        formData.append('username', this.username);
                        formData.append('password', this.password);
                        formData.append('from_email', this.from_email);
                        formData.append('from_name', this.from_name);

                        fetch('ajax_settings.php?action=save_smtp', {
                            method: 'POST',
                            body: formData
                        })
                            .then(res => res.json())
                            .then(data => {
                                this.saving = false;
                                alert(data.message || data.error);
                            })
                            .catch(() => {
                                this.saving = false;
                                alert('Network Error');
                            });
                    },

                    testSmtp() {
                        this.testing = true;
                        fetch('ajax_settings.php?action=test_smtp')
                            .then(res => res.json())
                            .then(data => {
                                this.testing = false;
                                alert(data.message || data.error);
                            })
                            .catch(() => {
                                this.testing = false;
                                alert('Network Error');
                            });
                    }
                }
            }
        </script>
    </div>
</div>

<?php include '../includes/footer.php'; ?>