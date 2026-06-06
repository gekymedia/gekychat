@extends('layouts.admin')

@section('title', 'Installed Devices')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Installed Devices</span>
        </div>
    </li>
@endsection

@section('content')
@php
    $resolveType = function ($device) use ($typeColumn) {
        if ($typeColumn && !empty($device->{$typeColumn})) {
            return $device->{$typeColumn};
        }
        return $device->device_type ?? $device->platform ?? 'unknown';
    };
    $typeBadge = function (string $type) {
        return match ($type) {
            'android' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'ios' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            'web' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        };
    };
    $typeIcon = function (string $type) {
        return match ($type) {
            'android' => 'fab fa-android',
            'ios' => 'fab fa-apple',
            'web' => 'fas fa-globe',
            default => 'fas fa-mobile-alt',
        };
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Installed Devices</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Push notification registrations from <code class="text-sm">device_tokens</code>.
                Reinstalling the app updates <strong>Last seen</strong> for the same device ID.
            </p>
        </div>
        <a href="{{ route('admin.device-tokens.index') }}"
           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-sync-alt mr-2"></i>
            Refresh
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Total devices</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-indigo-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Unique users</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['unique_users']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-green-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Android</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['android']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-gray-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">iOS</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['ios']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-cyan-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Web</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['web']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">New today</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['registered_today']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-orange-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Updated today</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['updated_today']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 border-l-4 border-pink-500">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Last 24h</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['updated_24h']) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4">
        <form method="GET" action="{{ route('admin.device-tokens.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search user / device ID</label>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Name, phone, username, device ID..."
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-[120px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User ID</label>
                <input type="number"
                       name="user_id"
                       value="{{ request('user_id') }}"
                       placeholder="e.g. 1"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-[140px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Device type</label>
                <select name="device_type" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                    <option value="">All types</option>
                    <option value="android" @selected(request('device_type') === 'android')>Android</option>
                    <option value="ios" @selected(request('device_type') === 'ios')>iOS</option>
                    <option value="web" @selected(request('device_type') === 'web')>Web</option>
                </select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Updated from</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-[140px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Updated to</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            @if(request()->hasAny(['search', 'user_id', 'device_type', 'date_from', 'date_to']))
                <a href="{{ route('admin.device-tokens.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Device ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">First registered</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Last seen</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Token</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($devices as $device)
                        @php $type = $resolveType($device); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm">
                                @if($device->user)
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $device->user->name ?: ($device->user->username ?: '—') }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs">
                                        ID {{ $device->user_id }}
                                        @if($device->user->phone)
                                            · {{ $device->user->phone }}
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-500">User #{{ $device->user_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $typeBadge($type) }}">
                                    <i class="{{ $typeIcon($type) }}"></i>
                                    {{ ucfirst($type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300 max-w-[200px] truncate" title="{{ $device->device_id }}">
                                {{ $device->device_id ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $device->created_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="text-gray-900 dark:text-white">{{ $device->updated_at?->format('Y-m-d H:i') ?? '—' }}</span>
                                @if($device->updated_at && $device->created_at && $device->updated_at->gt($device->created_at->addMinute()))
                                    <span class="ml-1 text-xs text-orange-600 dark:text-orange-400" title="Token refreshed (reinstall or re-login)">↻</span>
                                @endif
                                @if(!empty($device->last_used_at))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Used {{ $device->last_used_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">
                                @if($device->token)
                                    {{ Str::limit($device->token, 12, '…') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-mobile-alt text-3xl mb-3 block opacity-50"></i>
                                No devices registered yet. Install the app, log in, and allow notifications — then refresh this page.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($devices->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $devices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
