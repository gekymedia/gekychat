@extends('layouts.admin')

@section('title', 'Live & Call Management - Admin Panel')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Live & Call Management</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Live & Call Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Monitor and manage active calls and live broadcasts. Force-end sessions if necessary.</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <button id="refreshLiveCalls" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="p-6">
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

@push('scripts')
<script>
(function() {
    function showAlert(type, message) {
        const alertClass = {
            'success': 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-400',
            'error': 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-400',
            'warning': 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-400',
            'info': 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-400'
        }[type] || 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-400';
        const alertHtml = '<div class="' + alertClass + ' border rounded-lg p-4 mb-4 flex items-center justify-between"><span>' + message + '</span><button onclick="this.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button></div>';
        const container = document.getElementById('alertContainer');
        container.innerHTML = alertHtml;
        setTimeout(function() { container.innerHTML = ''; }, 5000);
    }

    async function loadLiveCalls() {
        const contentEl = document.getElementById('liveCallsContent');
        try {
            const response = await fetch('{{ route("admin.live-calls.stats") }}');
            const data = await response.json();
            const stats = data.stats || {};
            const callsDirect = data.calls_direct || [];
            const callsGroup = data.calls_group || [];
            const broadcasts = data.broadcasts || [];

            const renderCallRow = function(call) {
                const joined = call.participants_joined_count ?? 0;
                const joinedLabel = joined === 0 ? 'No one joined' : joined === 1 ? '1 joined' : joined + ' joined';
                return '<div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-600 rounded-lg"><div><p class="font-medium text-gray-900 dark:text-white">' + (call.type || 'video') + ' Call</p><p class="text-sm text-gray-600 dark:text-gray-400">' + (call.caller && call.caller.name ? call.caller.name : 'Unknown') + (call.callee ? ' ↔ ' + call.callee.name : '') + (call.group ? ' | Group: ' + call.group.name : '') + '</p><p class="text-xs text-gray-500 dark:text-gray-400 mt-1">' + joinedLabel + '</p></div><button onclick="window.forceEndCall(' + call.id + ')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Force End</button></div>';
            };

            let html = '<div class="space-y-6"><div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            html += '<div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800"><p class="text-sm text-blue-600 dark:text-blue-400 mb-1">1:1 Calls</p><p class="text-2xl font-bold text-blue-900 dark:text-blue-300">' + (stats.active_direct_calls ?? stats.active_calls ?? 0) + '</p></div>';
            html += '<div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400 mb-1">Group Calls</p><p class="text-2xl font-bold text-green-900 dark:text-green-300">' + (stats.active_group_calls || 0) + '</p></div>';
            html += '<div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800"><p class="text-sm text-purple-600 dark:text-purple-400 mb-1">Live Broadcasts</p><p class="text-2xl font-bold text-purple-900 dark:text-purple-300">' + (stats.active_lives || 0) + '</p></div></div>';

            if (broadcasts.length > 0) {
                html += '<div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6"><h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Live Broadcasts</h3><div class="space-y-3">';
                broadcasts.forEach(function(broadcast) {
                    html += '<div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-600 rounded-lg"><div><p class="font-medium text-gray-900 dark:text-white">' + (broadcast.title || 'Untitled') + '</p><p class="text-sm text-gray-600 dark:text-gray-400">By: ' + (broadcast.broadcaster && broadcast.broadcaster.name ? broadcast.broadcaster.name : 'Unknown') + ' | Viewers: ' + (broadcast.viewers_count || 0) + '</p></div><button onclick="window.forceEndBroadcast(' + broadcast.id + ')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">Force End</button></div>';
                });
                html += '</div></div>';
            }
            if (callsDirect.length > 0) {
                html += '<div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6"><h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">1:1 Calls</h3><div class="space-y-3">';
                callsDirect.forEach(function(call) { html += renderCallRow(call); });
                html += '</div></div>';
            }
            if (callsGroup.length > 0) {
                html += '<div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6"><h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Group Calls</h3><div class="space-y-3">';
                callsGroup.forEach(function(call) { html += renderCallRow(call); });
                html += '</div></div>';
            }
            if (broadcasts.length === 0 && callsDirect.length === 0 && callsGroup.length === 0) {
                html += '<div class="text-center py-8 text-gray-500"><i class="fas fa-video text-4xl mb-4"></i><p>No active calls or live broadcasts</p></div>';
            }
            html += '</div>';
            contentEl.innerHTML = html;
        } catch (error) {
            console.error('Error loading live calls:', error);
            contentEl.innerHTML = '<div class="text-center py-8 text-red-500">Failed to load data. Please refresh the page.</div>';
        }
    }

    window.forceEndBroadcast = async function(id) {
        if (!confirm('Are you sure you want to force-end this live broadcast?')) return;
        try {
            const response = await fetch('{{ route("admin.live-calls.broadcast.force-end", ":id") }}'.replace(':id', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const data = await response.json();
            if (data.status === 'success') { showAlert('success', 'Live broadcast ended successfully'); loadLiveCalls(); }
            else { showAlert('error', 'Failed to end broadcast.'); }
        } catch (e) { console.error(e); showAlert('error', 'Failed to end broadcast.'); }
    };

    window.forceEndCall = async function(id) {
        if (!confirm('Are you sure you want to force-end this call?')) return;
        try {
            const response = await fetch('{{ route("admin.live-calls.call.force-end", ":id") }}'.replace(':id', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const data = await response.json();
            if (data.status === 'success') { showAlert('success', 'Call ended successfully'); loadLiveCalls(); }
            else { showAlert('error', 'Failed to end call.'); }
        } catch (e) { console.error(e); showAlert('error', 'Failed to end call.'); }
    };

    document.getElementById('refreshLiveCalls').addEventListener('click', loadLiveCalls);
    loadLiveCalls();
    setInterval(loadLiveCalls, 10000);
})();
</script>
@endpush
@endsection
