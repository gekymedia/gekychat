@extends('layouts.admin')

@section('title', 'Feature Flags')

@section('breadcrumb')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2 text-xs"></i>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Feature Flags</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-4 sm:space-y-6 max-w-4xl">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Feature Flags</h1>
        <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400 mt-1">
            Turn product features on or off without redeploying. Changes apply immediately for app and web clients.
        </p>
    </div>

    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3 sm:p-4">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-emerald-600 dark:text-emerald-400 mt-0.5"></i>
            <p class="text-sm text-emerald-800 dark:text-emerald-300">
                Looking for <strong>Sika Wallet</strong>? Enable the <code class="px-1 rounded bg-emerald-100 dark:bg-emerald-900/40">sika_wallet</code> flag below so it appears in the mobile/desktop attach menu.
            </p>
        </div>
    </div>

    <div id="featureFlagsAlert" class="hidden rounded-lg p-3 text-sm"></div>

    <div id="featureFlagsContent" class="space-y-3">
        <div class="text-center py-10">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600 mx-auto"></div>
            <p class="text-gray-500 dark:text-gray-400 mt-4">Loading feature flags...</p>
        </div>
    </div>
</div>

<script>
(function () {
    const requiredFlags = [
        { key: 'sika_wallet', label: 'Sika Wallet', description: 'Show Sika coins wallet in the app attach menu and related entry points' },
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

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const listUrl = @json(route('admin.feature-flags.index'));

    function showAlert(type, message) {
        const el = document.getElementById('featureFlagsAlert');
        if (!el) return;
        el.className = `rounded-lg p-3 text-sm ${type === 'success'
            ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800'
            : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800'}`;
        el.textContent = message;
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 4000);
    }

    async function loadFeatureFlags() {
        const response = await fetch(listUrl, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Failed to load feature flags');
        const data = await response.json();
        const flags = data.data || [];

        const knownKeys = new Set(requiredFlags.map(f => f.key));
        const extras = flags
            .filter(f => !knownKeys.has(f.key))
            .map(f => ({
                key: f.key,
                label: f.description || f.key.replace(/_/g, ' '),
                description: f.platform ? `Platform: ${f.platform}` : 'Custom feature flag',
            }));

        const allDefs = [...requiredFlags, ...extras];

        document.getElementById('featureFlagsContent').innerHTML = `
            <div class="space-y-3">
                ${allDefs.map(flagDef => {
                    const flag = flags.find(f => f.key === flagDef.key) || { key: flagDef.key, enabled: false };
                    return `
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white">${flagDef.label}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">${flagDef.description}</p>
                                <p class="text-xs text-gray-400 mt-1 font-mono">${flagDef.key}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer self-start sm:self-center sm:ml-4">
                                <input type="checkbox" ${flag.enabled ? 'checked' : ''}
                                       data-flag-key="${flagDef.key}"
                                       class="sr-only peer flag-toggle">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 dark:peer-focus:ring-emerald-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        document.querySelectorAll('.flag-toggle').forEach(input => {
            input.addEventListener('change', async function () {
                const key = this.dataset.flagKey;
                const desired = this.checked;
                try {
                    await toggleFeatureFlag(key, desired);
                } catch (e) {
                    this.checked = !desired;
                    showAlert('error', e.message || 'Failed to update flag');
                }
            });
        });
    }

    async function toggleFeatureFlag(key, desiredEnabled) {
        const encodedKey = encodeURIComponent(key);
        const response = await fetch(`/admin/feature-flags/${encodedKey}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ enabled: desiredEnabled }),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error occurred' }));
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }

        const data = await response.json();
        showAlert('success', data.message || `Feature flag ${key} updated`);
    }

    loadFeatureFlags().catch(err => {
        console.error(err);
        document.getElementById('featureFlagsContent').innerHTML = `
            <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300">
                Failed to load feature flags. Refresh and try again.
            </div>
        `;
    });
})();
</script>
@endsection
