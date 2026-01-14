@extends('layouts.admin')

@section('title', 'Special API Privileges')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Special API Privileges</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Special API Privileges</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">View accounts with special API creation privileges and their API clients</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Privileged Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $privilegedUsers->count() }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-key text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">API Clients</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $privilegedApiClients->count() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-code text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Platform Clients</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $privilegedApiClients->where('type', 'platform')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <i class="fas fa-server text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Privileged Users Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-users mr-2 text-green-600 dark:text-green-400"></i>
                    Users with Special API Privilege
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $privilegedUsers->count() }} user(s)
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Contact Info
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            API Clients
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Activity
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($privilegedUsers as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $user->name }}
                                        <span class="ml-2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs px-2 py-1 rounded-full" title="Special API Creation Privilege">
                                            <i class="fas fa-key mr-1"></i>Special API
                                        </span>
                                        @if($user->is_admin)
                                        <span class="ml-2 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs px-2 py-1 rounded-full">Admin</span>
                                        @endif
                                        @if($user->developer_mode)
                                        <span class="ml-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs px-2 py-1 rounded-full">Developer</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        ID: {{ $user->id }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">
                                {{ $user->email ?? 'No email' }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->phone ?? 'No phone' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">
                                <span class="font-medium">{{ $user->api_clients_count ?? 0 }}</span> client(s)
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-4 text-sm">
                                <div class="flex items-center space-x-1 text-blue-600 dark:text-blue-400">
                                    <i class="fas fa-comment text-xs"></i>
                                    <span>{{ $user->sent_messages_count ?? 0 }}</span>
                                </div>
                                <div class="flex items-center space-x-1 text-purple-600 dark:text-purple-400">
                                    <i class="fas fa-clock text-xs"></i>
                                    <span>{{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'Never' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('admin.users.stats', $user->id) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                                   title="View User Stats">
                                    <i class="fas fa-chart-bar mr-1.5 text-xs"></i>
                                    Stats
                                </a>
                                <a href="{{ route('admin.users.index') }}?search={{ urlencode($user->phone ?? $user->email ?? '') }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                   title="View in User Management">
                                    <i class="fas fa-user mr-1.5 text-xs"></i>
                                    View
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No privileged users found</p>
                                <p class="text-sm mt-1">No users currently have special API creation privileges.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Clients Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-code mr-2 text-blue-600 dark:text-blue-400"></i>
                    API Clients Owned by Privileged Users
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $privilegedApiClients->count() }} client(s)
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Client
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Owner
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Activity
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($privilegedApiClients as $client)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                                    <i class="fas fa-code text-xs"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $client['name'] }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($client['client_id'])
                                            ID: {{ substr($client['client_id'], 0, 12) }}...
                                        @else
                                            No Client ID
                                        @endif
                                    </div>
                                    @if(isset($client['webhook_url']) && $client['webhook_url'])
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        <i class="fas fa-link mr-1"></i>
                                        {{ \Illuminate\Support\Str::limit($client['webhook_url'], 40) }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">
                                {{ $client['user']->name ?? 'Unknown' }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $client['user']->email ?? $client['user']->phone ?? 'No contact' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($client['type'] === 'platform')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                <i class="fas fa-server mr-1"></i>
                                Platform
                            </span>
                            @elseif($client['type'] === 'user')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                <i class="fas fa-user mr-1"></i>
                                User API Key
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                <i class="fas fa-key mr-1"></i>
                                Legacy Token
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($client['status'] === 'active')
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">Active</span>
                            </div>
                            @else
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-gray-400 rounded-full mr-2"></div>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Inactive</span>
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">
                                @if($client['type'] === 'platform')
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-1 text-blue-600 dark:text-blue-400">
                                        <i class="fas fa-comment text-xs"></i>
                                        <span>{{ $client['messages_count'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex items-center space-x-1 text-purple-600 dark:text-purple-400">
                                        <i class="fas fa-users text-xs"></i>
                                        <span>{{ $client['conversations_count'] ?? 0 }}</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Created: {{ $client['created_at']->diffForHumans() }}
                            </div>
                            @if($client['last_used_at'])
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Last used: {{ $client['last_used_at']->diffForHumans() }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                @if($client['type'] === 'platform')
                                <a href="{{ route('admin.api-clients.index') }}#client-{{ $client['id'] }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                                   title="View Client Details">
                                    <i class="fas fa-eye mr-1.5 text-xs"></i>
                                    View
                                </a>
                                @endif
                                @if($client['user'])
                                <a href="{{ route('admin.users.stats', $client['user']->id) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                   title="View Owner Stats">
                                    <i class="fas fa-user mr-1.5 text-xs"></i>
                                    Owner
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <i class="fas fa-code text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No API clients found</p>
                                <p class="text-sm mt-1">No API clients are currently owned by users with special privileges.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Auto-refresh data every 5 minutes
setInterval(() => {
    window.location.reload();
}, 300000);
</script>
@endsection
