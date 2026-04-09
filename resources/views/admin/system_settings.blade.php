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
                <button id="engagement-boost-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('engagement-boost')">
                    <i class="fas fa-rocket mr-2"></i>
                    Engagement Boost
                </button>
                <button id="priority_bank-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('priority_bank')">
                    <i class="fas fa-university mr-2"></i>
                    Priority Bank
                </button>
                <button id="in-app-notices-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap" onclick="switchTab('in-app-notices')">
                    <i class="fas fa-bullhorn mr-2"></i>
                    In-app Messages
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

            <!-- Priority Bank Tab -->
            <div id="priority_bank-tab-content" class="tab-content space-y-6 hidden">
                @if(session('success') && session('tab') == 'priority_bank')
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3"></i>
                            <p class="text-green-800 dark:text-green-300 font-medium">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif
                @if($errors->any() && session('tab') == 'priority_bank')
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3"></i>
                            <div>
                                @foreach ($errors->all() as $error)
                                    <p class="text-red-800 dark:text-red-300 text-sm">{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                <div class="bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-cyan-600 dark:text-cyan-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-cyan-900 dark:text-cyan-300 mb-1">Priority Bank</h4>
                            <p class="text-sm text-cyan-800 dark:text-cyan-400">Configure the connection for Account & Finance (income/expenditure sync). Values saved here are used when syncing from Income and Expenditure. Database settings take precedence over .env.</p>
                        </div>
                    </div>
                </div>
                <form action="{{ route('admin.system-settings.priority-bank.update') }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="priority_bank_api_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API URL</label>
                            <input type="url"
                                   id="priority_bank_api_url"
                                   name="priority_bank_api_url"
                                   value="{{ old('priority_bank_api_url', $settings['priority_bank_api_url'] ?? '') }}"
                                   placeholder="https://bank.prioritysolutionsagency.com"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Base URL of the Priority Bank API (no trailing slash).</p>
                        </div>
                        <div class="md:col-span-2">
                            <label for="priority_bank_api_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Token</label>
                            <div class="relative">
                                <input type="password"
                                       id="priority_bank_api_token"
                                       name="priority_bank_api_token"
                                       value="{{ !empty($settings['priority_bank_api_token']) ? '••••••••••••' : '' }}"
                                       placeholder="Leave blank to keep existing token"
                                       autocomplete="off"
                                       class="w-full p-3 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button"
                                        id="priority_bank_api_token_eye"
                                        onclick="togglePriorityBankTokenVisibility()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        title="Show token">
                                    <i class="fas fa-eye" id="priority_bank_api_token_icon"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bearer token for Priority Bank API. Leave blank to keep the current token. The eye icon reveals what you type; stored tokens are not shown for security.</p>
                        </div>
                        <div>
                            <label for="priority_bank_system_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">System ID</label>
                            <input type="text"
                                   id="priority_bank_system_id"
                                   name="priority_bank_system_id"
                                   value="{{ old('priority_bank_system_id', $settings['priority_bank_system_id'] ?? 'gekychat') }}"
                                   placeholder="gekychat"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">System ID for this app in Priority Bank (e.g. gekychat).</p>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Save Priority Bank Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Engagement Boost Tab -->
            <div id="engagement-boost-tab-content" class="tab-content space-y-6 hidden">
                <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-rocket text-orange-600 dark:text-orange-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-orange-900 dark:text-orange-300 mb-1">Engagement Boost (Early Stage Growth)</h4>
                            <p class="text-sm text-orange-800 dark:text-orange-400">Multiply engagement metrics (views, likes, comments, shares) to encourage content creators during the app's early growth phase. Disable this when the app gains stability and organic engagement.</p>
                        </div>
                    </div>
                </div>

                <div id="engagementBoostContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading engagement boost settings...</p>
                    </div>
                </div>
            </div>

            <!-- In-app Notices Tab -->
            <div id="in-app-notices-tab-content" class="tab-content space-y-6 hidden">
                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-bullhorn text-indigo-600 dark:text-indigo-400 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-indigo-900 dark:text-indigo-300 mb-1">In-app Messages</h4>
                            <p class="text-sm text-indigo-800 dark:text-indigo-400">
                                Manage banners shown under chat filters in mobile. Activate one or more templates; mobile will show active items and swipe as a carousel when there are multiple.
                            </p>
                        </div>
                    </div>
                </div>
                <div id="inAppNoticesContent">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                        <p class="text-gray-500 dark:text-gray-400 mt-4">Loading in-app messages...</p>
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
    
    // Load tab data (priority_bank has no async content)
    if (tabName !== 'priority_bank') {
        loadTabData(tabName);
    }
}

function togglePriorityBankTokenVisibility() {
    var input = document.getElementById('priority_bank_api_token');
    var icon = document.getElementById('priority_bank_api_token_icon');
    var btn = document.getElementById('priority_bank_api_token_eye');
    if (!input || !icon) return;
    var val = (input.value || '').trim();
    var mask = '••••••••••••';
    if (val === mask || /^[\*•]+$/.test(val)) {
        showAlert('info', 'Stored token cannot be displayed for security. Enter a new token above to replace it; you can then show/hide that value.');
        return;
    }
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
        btn.setAttribute('title', 'Hide token');
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
        btn.setAttribute('title', 'Show token');
    }
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
    const contentEl = document.getElementById(`${tabName}-tab-content`);
    if (!contentEl) return;
    const inner = contentEl.querySelector('[id$="Content"]');

    try {
        if (tabName === 'phase-mode') {
            await loadPhaseMode();
        } else if (tabName === 'testing-mode') {
            await loadTestingMode();
        } else if (tabName === 'feature-flags') {
            await loadFeatureFlags();
        } else if (tabName === 'engagement-boost') {
            await loadEngagementBoost();
        } else if (tabName === 'in-app-notices') {
            await loadInAppNotices();
        }
    } catch (error) {
        console.error('Error loading tab data:', error);
        showAlert('error', 'Failed to load data. Please refresh the page.');
    }
}

async function loadInAppNotices() {
    const response = await fetch('{{ route("admin.in-app-notices.index") }}', {
        headers: { 'Accept': 'application/json' }
    });
    const data = await response.json();
    const notices = data.notices || [];

    const html = `
        <div class="space-y-4">
            ${notices.map((n) => `
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-700">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-3">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">${n.title || '(No title)'}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Key: ${n.notice_key}
                                ${n.is_system_notice ? ' • System notice (cannot be deleted)' : ''}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 rounded text-xs ${n.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}">
                                ${n.is_active ? 'Active' : 'Inactive'}
                            </span>
                            <button onclick="toggleInAppNotice(${n.id})" class="px-3 py-1.5 rounded text-xs font-medium ${n.is_active ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'}">
                                ${n.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Title</label>
                            <input id="notice-title-${n.id}" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white" value="${(n.title || '').replace(/"/g, '&quot;')}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Style</label>
                            <select id="notice-style-${n.id}" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="info" ${n.style === 'info' ? 'selected' : ''}>info</option>
                                <option value="warning" ${n.style === 'warning' ? 'selected' : ''}>warning</option>
                                <option value="promo" ${n.style === 'promo' ? 'selected' : ''}>promo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Condition</label>
                            <select id="notice-condition-${n.id}" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="always" ${(n.condition_type || 'always') === 'always' ? 'selected' : ''}>Always</option>
                                <option value="birthday_contact_today" ${n.condition_type === 'birthday_contact_today' ? 'selected' : ''}>Birthday contact today</option>
                                <option value="device_storage_low" ${n.condition_type === 'device_storage_low' ? 'selected' : ''}>Device storage low</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Body</label>
                            <textarea id="notice-body-${n.id}" rows="2" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white">${n.body || ''}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Action Label</label>
                            <input id="notice-action-label-${n.id}" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white" value="${(n.action_label || '').replace(/"/g, '&quot;')}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Action URL</label>
                            <input id="notice-action-url-${n.id}" class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white" value="${(n.action_url || '').replace(/"/g, '&quot;')}">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button onclick="saveInAppNotice(${n.id})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm font-medium">Save</button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    document.getElementById('inAppNoticesContent').innerHTML = html || `
        <p class="text-gray-500 dark:text-gray-400">No in-app messages found.</p>
    `;
}

async function toggleInAppNotice(id) {
    try {
        const response = await fetch(`{{ url('/admin/in-app-notices') }}/${id}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert('success', data.message || 'Notice status updated.');
            loadInAppNotices();
        } else {
            showAlert('error', data.message || 'Failed to toggle notice.');
        }
    } catch (e) {
        console.error(e);
        showAlert('error', 'Failed to toggle notice.');
    }
}

async function saveInAppNotice(id) {
    const payload = {
        title: document.getElementById(`notice-title-${id}`).value,
        body: document.getElementById(`notice-body-${id}`).value,
        style: document.getElementById(`notice-style-${id}`).value,
        condition_type: document.getElementById(`notice-condition-${id}`).value,
        action_label: document.getElementById(`notice-action-label-${id}`).value || null,
        action_url: document.getElementById(`notice-action-url-${id}`).value || null
    };

    try {
        const response = await fetch(`{{ url('/admin/in-app-notices') }}/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert('success', data.message || 'Notice updated.');
            loadInAppNotices();
        } else {
            showAlert('error', data.message || 'Failed to update notice.');
        }
    } catch (e) {
        console.error(e);
        showAlert('error', 'Failed to update notice.');
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

// Load Engagement Boost Settings
async function loadEngagementBoost() {
    const response = await fetch('{{ route("admin.engagement-boost.index") }}');
    const data = await response.json();
    
    const settings = data.data || {};
    const enabled = settings.enabled || false;
    const multipliers = settings.multipliers || { views: 1, likes: 1, comments: 1, shares: 1 };
    
    const html = `
        <div class="space-y-6">
            <!-- Master Toggle -->
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Engagement Boost Status</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            ${enabled 
                                ? '<span class="text-orange-600 dark:text-orange-400"><i class="fas fa-rocket mr-1"></i> Active - Metrics are being boosted</span>' 
                                : '<span class="text-gray-500"><i class="fas fa-pause mr-1"></i> Disabled - Showing real metrics</span>'}
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" ${enabled ? 'checked' : ''} onchange="toggleEngagementBoost(this.checked)" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 dark:peer-focus:ring-orange-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-orange-500"></div>
                    </label>
                </div>
                
                ${enabled ? `
                    <div class="mt-4 p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
                        <p class="text-sm text-orange-800 dark:text-orange-400">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Important:</strong> Engagement boost is active. All World Feed metrics shown to users are multiplied. Remember to disable this when the app gains organic traction.
                        </p>
                    </div>
                ` : ''}
            </div>
            
            <!-- Multiplier Settings -->
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Boost Multipliers</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Set how much to multiply each metric. For example, x5 means 10 real views will show as 50.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Views Multiplier -->
                    <div class="bg-gray-50 dark:bg-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-eye text-blue-500 mr-2"></i>
                                <span class="font-medium text-gray-900 dark:text-white">Views</span>
                            </div>
                            <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">x${multipliers.views}</span>
                        </div>
                        <input type="range" min="1" max="20" step="1" value="${multipliers.views}" 
                               onchange="updateMultiplierPreview('views', this.value)"
                               oninput="document.getElementById('views-preview').textContent = 'x' + this.value"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 accent-blue-500">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>x1 (real)</span>
                            <span id="views-preview">x${multipliers.views}</span>
                            <span>x20</span>
                        </div>
                    </div>
                    
                    <!-- Likes Multiplier -->
                    <div class="bg-gray-50 dark:bg-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-heart text-red-500 mr-2"></i>
                                <span class="font-medium text-gray-900 dark:text-white">Likes</span>
                            </div>
                            <span class="text-2xl font-bold text-red-600 dark:text-red-400">x${multipliers.likes}</span>
                        </div>
                        <input type="range" min="1" max="20" step="1" value="${multipliers.likes}" 
                               onchange="updateMultiplierPreview('likes', this.value)"
                               oninput="document.getElementById('likes-preview').textContent = 'x' + this.value"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 accent-red-500">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>x1 (real)</span>
                            <span id="likes-preview">x${multipliers.likes}</span>
                            <span>x20</span>
                        </div>
                    </div>
                    
                    <!-- Comments Multiplier -->
                    <div class="bg-gray-50 dark:bg-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-comment text-green-500 mr-2"></i>
                                <span class="font-medium text-gray-900 dark:text-white">Comments</span>
                            </div>
                            <span class="text-2xl font-bold text-green-600 dark:text-green-400">x${multipliers.comments}</span>
                        </div>
                        <input type="range" min="1" max="20" step="1" value="${multipliers.comments}" 
                               onchange="updateMultiplierPreview('comments', this.value)"
                               oninput="document.getElementById('comments-preview').textContent = 'x' + this.value"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 accent-green-500">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>x1 (real)</span>
                            <span id="comments-preview">x${multipliers.comments}</span>
                            <span>x20</span>
                        </div>
                    </div>
                    
                    <!-- Shares Multiplier -->
                    <div class="bg-gray-50 dark:bg-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-share text-purple-500 mr-2"></i>
                                <span class="font-medium text-gray-900 dark:text-white">Shares</span>
                            </div>
                            <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">x${multipliers.shares}</span>
                        </div>
                        <input type="range" min="1" max="20" step="1" value="${multipliers.shares}" 
                               onchange="updateMultiplierPreview('shares', this.value)"
                               oninput="document.getElementById('shares-preview').textContent = 'x' + this.value"
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700 accent-purple-500">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>x1 (real)</span>
                            <span id="shares-preview">x${multipliers.shares}</span>
                            <span>x20</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-between mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button onclick="resetEngagementBoost()" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        <i class="fas fa-undo mr-2"></i>
                        Reset to Defaults
                    </button>
                    <button onclick="saveEngagementBoostMultipliers()" class="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Save Multipliers
                    </button>
                </div>
            </div>
            
            <!-- Example Preview -->
            <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Preview Example</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">How a post with 10 real engagements would appear to users:</p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Views</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">${10 * multipliers.views}</p>
                        <p class="text-xs text-gray-500">(10 real × ${multipliers.views})</p>
                    </div>
                    <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Likes</p>
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">${10 * multipliers.likes}</p>
                        <p class="text-xs text-gray-500">(10 real × ${multipliers.likes})</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Comments</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">${10 * multipliers.comments}</p>
                        <p class="text-xs text-gray-500">(10 real × ${multipliers.comments})</p>
                    </div>
                    <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Shares</p>
                        <p class="text-xl font-bold text-purple-600 dark:text-purple-400">${10 * multipliers.shares}</p>
                        <p class="text-xs text-gray-500">(10 real × ${multipliers.shares})</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('engagementBoostContent').innerHTML = html;
}

// Toggle Engagement Boost
async function toggleEngagementBoost(enabled) {
    const action = enabled ? 'enable' : 'disable';
    if (!confirm(`Are you sure you want to ${action} engagement boost? This affects how metrics appear to all users.`)) {
        document.querySelector('#engagement-boost-tab-content input[type="checkbox"]').checked = !enabled;
        return;
    }
    
    try {
        const response = await fetch('{{ route("admin.engagement-boost.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', data.message);
            loadEngagementBoost();
        } else {
            showAlert('error', 'Failed to toggle engagement boost.');
            document.querySelector('#engagement-boost-tab-content input[type="checkbox"]').checked = !enabled;
        }
    } catch (error) {
        console.error('Error toggling engagement boost:', error);
        showAlert('error', 'Failed to toggle engagement boost.');
        document.querySelector('#engagement-boost-tab-content input[type="checkbox"]').checked = !enabled;
    }
}

// Update multiplier preview (called on slider change)
function updateMultiplierPreview(type, value) {
    // Just update the preview text - actual save happens on button click
}

// Save Engagement Boost Multipliers
async function saveEngagementBoostMultipliers() {
    const viewsSlider = document.querySelector('input[onchange*="views"]');
    const likesSlider = document.querySelector('input[onchange*="likes"]');
    const commentsSlider = document.querySelector('input[onchange*="comments"]');
    const sharesSlider = document.querySelector('input[onchange*="shares"]');
    
    try {
        const response = await fetch('{{ route("admin.engagement-boost.update") }}', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                views_multiplier: viewsSlider ? viewsSlider.value : 1,
                likes_multiplier: likesSlider ? likesSlider.value : 1,
                comments_multiplier: commentsSlider ? commentsSlider.value : 1,
                shares_multiplier: sharesSlider ? sharesSlider.value : 1
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', 'Multipliers saved successfully!');
            loadEngagementBoost();
        } else {
            showAlert('error', 'Failed to save multipliers.');
        }
    } catch (error) {
        console.error('Error saving multipliers:', error);
        showAlert('error', 'Failed to save multipliers.');
    }
}

// Reset Engagement Boost to Defaults
async function resetEngagementBoost() {
    if (!confirm('Are you sure you want to reset engagement boost to defaults? This will disable boost and set all multipliers to 1x.')) {
        return;
    }
    
    try {
        const response = await fetch('{{ route("admin.engagement-boost.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('success', data.message);
            loadEngagementBoost();
        } else {
            showAlert('error', 'Failed to reset engagement boost.');
        }
    } catch (error) {
        console.error('Error resetting engagement boost:', error);
        showAlert('error', 'Failed to reset engagement boost.');
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
    var tabParam = new URLSearchParams(window.location.search).get('tab');
    if (tabParam === 'priority_bank' && document.getElementById('priority_bank-tab')) {
        switchTab('priority_bank');
    } else if (tabParam === 'in-app-notices' && document.getElementById('in-app-notices-tab')) {
        switchTab('in-app-notices');
    } else {
        switchTab('phase-mode');
    }

    // Refresh button
    document.getElementById('refreshData').addEventListener('click', function() {
        const activeTab = document.querySelector('.tab-button.border-blue-500');
        if (activeTab) {
            const tabName = activeTab.id.replace('-tab', '');
            loadTabData(tabName);
        }
    });
    
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

