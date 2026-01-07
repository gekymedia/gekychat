@extends('layouts.admin')

@section('title', 'System Settings - Admin Panel')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm font-medium text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
            <i class="fas fa-home mr-2"></i>
            Admin
        </a>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">System Settings</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6" id="admin-settings-app">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Settings</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Control system-wide behavior, limits, and feature availability</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <button id="refreshData" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer"></div>

    <!-- Settings Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <!-- Tab Headers -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-8 px-6 overflow-x-auto" aria-label="Tabs">
                <button id="phase-mode-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600 dark:text-blue-400 whitespace-nowrap" onclick="switchTab('phase-mode')">
                    <i class="fas fa-layer-group mr-2"></i>
                    Phase Mode
                </button>
                <button id="testing-mode-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('testing-mode')">
                    <i class="fas fa-vial mr-2"></i>
                    Testing Mode
                </button>
                <button id="feature-flags-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('feature-flags')">
                    <i class="fas fa-flag mr-2"></i>
                    Feature Flags
                </button>
                <button id="live-calls-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('live-calls')">
                    <i class="fas fa-video mr-2"></i>
                    Live & Calls
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Phase Mode Tab -->
            <div id="phase-mode-tab-content" class="tab-content space-y-6">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-1">Phase Mode Control</h4>
                            <p class="text-sm text-blue-800 dark:text-blue-400">Control system limits based on server capacity. Only one Phase Mode can be active at a time.</p>
                        </div>
                    </div>
                </div>

                <div id="phaseModeContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading phase modes...</p>
                    </div>
                </div>
            </div>

            <!-- Testing Mode Tab -->
            <div id="testing-mode-tab-content" class="tab-content space-y-6 hidden">
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-yellow-900 dark:text-yellow-300 mb-1">Testing Mode</h4>
                            <p class="text-sm text-yellow-800 dark:text-yellow-400">Allow limited users to test advanced features without affecting all users. Testing Mode overrides Phase limits for allowlisted users only.</p>
                        </div>
                    </div>
                </div>

                <div id="testingModeContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading testing mode...</p>
                    </div>
                </div>
            </div>

            <!-- Feature Flags Tab -->
            <div id="feature-flags-tab-content" class="tab-content space-y-6 hidden">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-green-600 dark:text-green-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-green-900 dark:text-green-300 mb-1">Feature Flag Management</h4>
                            <p class="text-sm text-green-800 dark:text-green-400">Enable or disable features without redeploying code. Changes apply immediately system-wide.</p>
                        </div>
                    </div>
                </div>

                <div id="featureFlagsContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading feature flags...</p>
                    </div>
                </div>
            </div>

            <!-- Live & Calls Tab -->
            <div id="live-calls-tab-content" class="tab-content space-y-6 hidden">
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-purple-600 dark:text-purple-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-purple-900 dark:text-purple-300 mb-1">Live & Call Management</h4>
                            <p class="text-sm text-purple-800 dark:text-purple-400">Monitor and manage active calls and live broadcasts. Force-end sessions if necessary.</p>
                        </div>
                    </div>
                </div>

                <div id="liveCallsContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading live calls...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Tab switching
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        button.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
    });

    // Show selected tab content
    document.getElementById(`${tabName}-tab-content`).classList.remove('hidden');

    // Activate selected tab
    document.getElementById(`${tabName}-tab`).classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
    document.getElementById(`${tabName}-tab`).classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
    
    // Load tab data
    loadTabData(tabName);
}

// Alert helper
function showAlert(type, message) {
    const alertClass = {
        'success': 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-400',
        'error': 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-400',
        'warning': 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-400',
        'info': 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-400'
    }[type] || 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-400';
    
    const alertHtml = `
        <div class="${alertClass} border rounded-lg p-4 mb-4 flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    const container = document.getElementById('alertContainer');
    container.innerHTML = alertHtml;
    setTimeout(() => container.innerHTML = '', 5000);
}

// Load tab data
async function loadTabData(tabName) {
    const contentEl = document.getElementById(`${tabName}-tab-content`).querySelector('[id$="Content"]');
    
    try {
        if (tabName === 'phase-mode') {
            await loadPhaseMode();
        } else if (tabName === 'testing-mode') {
            await loadTestingMode();
        } else if (tabName === 'feature-flags') {
            await loadFeatureFlags();
        } else if (tabName === 'live-calls') {
            await loadLiveCalls();
        }
    } catch (error) {
        console.error('Error loading tab data:', error);
        showAlert('error', 'Failed to load data. Please refresh the page.');
    }
}

// Load Phase Mode
async function loadPhaseMode() {
    const response = await fetch('{{ route("admin.phase-mode.index") }}');
    const data = await response.json();
    
    const current = data.current;
    const available = data.available;
    
    const html = `
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Current Phase Mode</h3>
                ${current ? `
                    <div class="flex items-center justify-between p-4 bg-${current.name === 'basic' ? 'blue' : current.name === 'essential' ? 'green' : 'purple'}-50 dark:bg-${current.name === 'basic' ? 'blue' : current.name === 'essential' ? 'green' : 'purple'}-900/20 rounded-lg">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white capitalize">${current.name}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Active since ${new Date(current.updated_at).toLocaleString()}</p>
                        </div>
                        <span class="px-3 py-1 bg-${current.name === 'basic' ? 'blue' : current.name === 'essential' ? 'green' : 'purple'}-600 text-white rounded-full text-sm font-medium">Active</span>
                    </div>
                ` : '<p class="text-gray-500">No phase mode is currently active.</p>'}
            </div>
            
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Available Phase Modes</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    ${available.map(phase => `
                        <div class="border ${phase.is_active ? 'border-blue-500' : 'border-gray-200 dark:border-gray-600'} rounded-lg p-4 ${phase.is_active ? 'bg-blue-50 dark:bg-blue-900/20' : ''}">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-900 dark:text-white capitalize">${phase.name}</h4>
                                ${phase.is_active ? '<span class="px-2 py-1 bg-blue-600 text-white rounded text-xs">Active</span>' : ''}
                            </div>
                            ${!phase.is_active ? `
                                <button onclick="switchPhaseMode('${phase.name}')" class="w-full mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                                    Switch to ${phase.name.charAt(0).toUpperCase() + phase.name.slice(1)}
                                </button>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('phaseModeContent').innerHTML = html;
}

// Switch Phase Mode
async function switchPhaseMode(phaseName) {
    if (!confirm(`Are you sure you want to switch to ${phaseName} phase mode? This will change system-wide limits immediately.`)) {
        return;
    }
    
    try {
        const response = await fetch('{{ route("admin.phase-mode.switch") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ phase_name: phaseName })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', data.message);
            loadPhaseMode();
        } else {
            showAlert('error', 'Failed to switch phase mode.');
        }
    } catch (error) {
        console.error('Error switching phase mode:', error);
        showAlert('error', 'Failed to switch phase mode.');
    }
}

// Load Testing Mode
async function loadTestingMode() {
    const response = await fetch('{{ route("admin.testing-mode.index") }}');
    const data = await response.json();
    
    const testingMode = data.testing_mode;
    const users = data.allowlisted_users || [];
    
    const html = `
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Testing Mode Status</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${testingMode.is_enabled ? 'Enabled' : 'Disabled'}</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" ${testingMode.is_enabled ? 'checked' : ''} onchange="toggleTestingMode(this.checked)" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                ${testingMode.is_enabled ? `
                    <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-400">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Testing Mode is enabled. Monitor CPU/RAM usage closely.
                        </p>
                    </div>
                ` : ''}
            </div>
            
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Allowlisted Users</h3>
                    <div class="flex items-center space-x-3">
                        <span class="px-3 py-1 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-full text-sm">${users.length} users</span>
                        <button onclick="showAddUserModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-plus mr-1"></i> Add User
                        </button>
                    </div>
                </div>
                ${users.length > 0 ? `
                    <div class="space-y-2">
                        ${users.map(user => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">${user.name || 'Unknown'}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">${user.phone || user.username || 'N/A'}</p>
                                </div>
                                <button onclick="removeTestingUser(${user.id})" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                                    Remove
                                </button>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="text-gray-500 text-center py-4">No users in testing allowlist. Click "Add User" to add users.</p>'}
            </div>
        </div>
    `;
    
    document.getElementById('testingModeContent').innerHTML = html;
}

// Toggle Testing Mode
async function toggleTestingMode(enabled) {
    if (enabled && !confirm('Enable Testing Mode? This will allow allowlisted users to bypass Phase Mode limits. Monitor system resources closely.')) {
        document.querySelector('#testing-mode-tab-content input[type="checkbox"]').checked = false;
        return;
    }
    
    try {
        const response = await fetch('{{ route("admin.testing-mode.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', `Testing Mode ${enabled ? 'enabled' : 'disabled'}`);
            loadTestingMode();
        }
    } catch (error) {
        console.error('Error toggling testing mode:', error);
        showAlert('error', 'Failed to toggle testing mode.');
    }
}

// Load Feature Flags
async function loadFeatureFlags() {
    const response = await fetch('{{ route("admin.feature-flags.index") }}');
    const data = await response.json();
    
    const flags = data.data || [];
    
    // Required feature flags from prompt
    const requiredFlags = [
        { key: 'channels_enabled', label: 'Channels', description: 'Enable channel functionality' },
        { key: 'email_chat', label: 'Email Chat', description: 'Enable email chat integration' },
        { key: 'world_feed', label: 'World Feed', description: 'Enable world feed feature' },
        { key: 'live_broadcast', label: 'Live Broadcast', description: 'Enable live broadcasting' },
        { key: 'live_chat', label: 'Live Chat', description: 'Enable live chat in broadcasts' },
        { key: 'group_calls', label: 'Group Calls', description: 'Enable group voice/video calls' },
        { key: 'meeting_mode', label: 'Meeting Mode', description: 'Enable meeting-style calls' },
        { key: 'advanced_ai', label: 'Advanced AI', description: 'Enable advanced AI features' },
        { key: 'multi_account', label: 'Multi-Account', description: 'Enable multi-account support' },
        { key: 'auto_reply', label: 'Auto-Reply', description: 'Enable auto-reply rules' },
        { key: 'media_compression', label: 'Media Compression', description: 'Enable media compression pipeline' },
    ];
    
    const html = `
        <div class="space-y-4">
            ${requiredFlags.map(flagDef => {
                const flag = flags.find(f => f.key === flagDef.key) || { key: flagDef.key, enabled: false };
                return `
                    <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900 dark:text-white">${flagDef.label}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${flagDef.description}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4">
                            <input type="checkbox" ${flag.enabled ? 'checked' : ''} 
                                   onchange="toggleFeatureFlag('${flag.key}', this.checked)" 
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                `;
            }).join('')}
        </div>
    `;
    
    document.getElementById('featureFlagsContent').innerHTML = html;
}

// Toggle Feature Flag
async function toggleFeatureFlag(key, enabled) {
    try {
        // URL encode the key in case it has special characters
        const encodedKey = encodeURIComponent(key);
        const url = `/admin/feature-flags/${encodedKey}/toggle`;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error occurred' }));
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', data.message);
            // Reload feature flags to get updated state
            loadFeatureFlags();
        } else {
            showAlert('error', data.message || 'Failed to toggle feature flag.');
            // Revert checkbox
            const checkbox = document.querySelector(`input[onchange*="${key}"]`);
            if (checkbox) checkbox.checked = !enabled;
        }
    } catch (error) {
        console.error('Error toggling feature flag:', error);
        showAlert('error', error.message || 'Failed to toggle feature flag.');
        // Revert checkbox
        const checkbox = document.querySelector(`input[onchange*="${key}"]`);
        if (checkbox) checkbox.checked = !enabled;
    }
}

// Load Live & Calls
async function loadLiveCalls() {
    const response = await fetch('{{ route("admin.live-calls.stats") }}');
    const data = await response.json();
    
    const stats = data.stats || {};
    const calls = data.calls || [];
    const broadcasts = data.broadcasts || [];
    
    const html = `
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-600 dark:text-blue-400 mb-1">Active Calls</p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">${stats.active_calls || 0}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                    <p class="text-sm text-green-600 dark:text-green-400 mb-1">Group Calls</p>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-300">${stats.active_group_calls || 0}</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                    <p class="text-sm text-purple-600 dark:text-purple-400 mb-1">Live Broadcasts</p>
                    <p class="text-2xl font-bold text-purple-900 dark:text-purple-300">${stats.active_lives || 0}</p>
                </div>
            </div>
            
            ${broadcasts.length > 0 ? `
                <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Live Broadcasts</h3>
                    <div class="space-y-3">
                        ${broadcasts.map(broadcast => `
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-600 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">${broadcast.title || 'Untitled'}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">By: ${broadcast.broadcaster?.name || 'Unknown'} | Viewers: ${broadcast.viewers_count || 0}</p>
                                </div>
                                <button onclick="forceEndBroadcast(${broadcast.id})" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                                    Force End
                                </button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
            
            ${calls.length > 0 ? `
                <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Calls</h3>
                    <div class="space-y-3">
                        ${calls.map(call => `
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-600 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">${call.type || 'Unknown'} Call</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        ${call.caller?.name || 'Unknown'} ${call.callee ? '↔ ' + call.callee.name : ''}
                                        ${call.group ? ' | Group: ' + call.group.name : ''}
                                    </p>
                                </div>
                                <button onclick="forceEndCall(${call.id})" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                                    Force End
                                </button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
            
            ${broadcasts.length === 0 && calls.length === 0 ? `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-video text-4xl mb-4"></i>
                    <p>No active calls or live broadcasts</p>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('liveCallsContent').innerHTML = html;
}

// Force End Broadcast
async function forceEndBroadcast(id) {
    if (!confirm('Are you sure you want to force-end this live broadcast?')) {
        return;
    }
    
    try {
        const response = await fetch(`{{ route("admin.live-calls.broadcast.force-end", ":id") }}`.replace(':id', id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', 'Live broadcast ended successfully');
            loadLiveCalls();
        } else {
            showAlert('error', 'Failed to end broadcast.');
        }
    } catch (error) {
        console.error('Error ending broadcast:', error);
        showAlert('error', 'Failed to end broadcast.');
    }
}

// Force End Call
async function forceEndCall(id) {
    if (!confirm('Are you sure you want to force-end this call?')) {
        return;
    }
    
    try {
        const response = await fetch(`{{ route("admin.live-calls.call.force-end", ":id") }}`.replace(':id', id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', 'Call ended successfully');
            loadLiveCalls();
        } else {
            showAlert('error', 'Failed to end call.');
        }
    } catch (error) {
        console.error('Error ending call:', error);
        showAlert('error', 'Failed to end call.');
    }
}

// Remove Testing User
async function removeTestingUser(userId) {
    if (!confirm('Are you sure you want to remove this user from the testing allowlist?')) {
        return;
    }
    
    try {
        const response = await fetch(`{{ route("admin.testing-mode.remove-user", ":userId") }}`.replace(':userId', userId), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', 'User removed from testing allowlist');
            loadTestingMode();
        } else {
            showAlert('error', 'Failed to remove user.');
        }
    } catch (error) {
        console.error('Error removing user:', error);
        showAlert('error', 'Failed to remove user.');
    }
}

// Show Add User Modal
function showAddUserModal() {
    const modalHtml = `
        <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add User to Testing Allowlist</h3>
                        <button onclick="closeAddUserModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Search by Phone, Username, or Name
                        </label>
                        <input type="text" 
                               id="userSearchInput" 
                               placeholder="Enter phone, username, or name..."
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               onkeyup="debounceSearchUsers(this.value)">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Start typing to search for users</p>
                    </div>
                    
                    <div id="userSearchResults" class="space-y-2 max-h-64 overflow-y-auto">
                        <p class="text-sm text-gray-500 text-center py-4">Enter a search query to find users</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('addUserModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('userSearchInput').focus();
}

// Close Add User Modal
function closeAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.remove();
    }
}

// Debounce search
let searchTimeout;
function debounceSearchUsers(query) {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        document.getElementById('userSearchResults').innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Enter at least 2 characters to search</p>';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        searchUsers(query);
    }, 300);
}

// Search Users
async function searchUsers(query) {
    const resultsEl = document.getElementById('userSearchResults');
    resultsEl.innerHTML = '<div class="text-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div></div>';
    
    try {
        const response = await fetch('{{ route("admin.users.index") }}?search=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('Search failed');
        }
        
        // Use the users index endpoint with JSON accept header
        const jsonResponse = await fetch('{{ route("admin.users.index") }}?search=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (jsonResponse.ok) {
            const data = await jsonResponse.json();
            displayUserSearchResults(data.users || []);
        } else {
            resultsEl.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Failed to search users. Please try again.</p>';
        }
    } catch (error) {
        console.error('Error searching users:', error);
        resultsEl.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Error searching users. Please try again.</p>';
    }
}

// Display User Search Results
function displayUserSearchResults(users) {
    const resultsEl = document.getElementById('userSearchResults');
    
    if (users.length === 0) {
        resultsEl.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No users found</p>';
        return;
    }
    
    const html = users.map(user => `
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
            <div class="flex-1">
                <p class="font-medium text-gray-900 dark:text-white">${user.name || 'Unknown'}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    ${user.phone ? 'Phone: ' + user.phone : ''}
                    ${user.username ? 'Username: ' + user.username : ''}
                </p>
            </div>
            <button onclick="addUserToTestingMode(${user.id})" class="ml-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                Add
            </button>
        </div>
    `).join('');
    
    resultsEl.innerHTML = html;
}

// Add User to Testing Mode
async function addUserToTestingMode(userId) {
    try {
        const response = await fetch('{{ route("admin.testing-mode.add-user") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ user_id: userId })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', data.message || 'User added to testing allowlist');
            closeAddUserModal();
            loadTestingMode();
        } else {
            showAlert('error', data.message || 'Failed to add user.');
        }
    } catch (error) {
        console.error('Error adding user:', error);
        showAlert('error', 'Failed to add user to testing allowlist.');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    switchTab('phase-mode');
    
    // Refresh button
    document.getElementById('refreshData').addEventListener('click', function() {
        const activeTab = document.querySelector('.tab-button.border-blue-500');
        if (activeTab) {
            const tabName = activeTab.id.replace('-tab', '');
            loadTabData(tabName);
        }
    });
    
    // Auto-refresh live calls every 10 seconds
    setInterval(() => {
        if (!document.getElementById('live-calls-tab-content').classList.contains('hidden')) {
            loadLiveCalls();
        }
    }, 10000);
    
    // Close modal on outside click
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('addUserModal');
        if (modal && e.target === modal) {
            closeAddUserModal();
        }
    });
});
</script>
@endpush
@endsection

