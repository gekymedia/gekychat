@extends('layouts.admin')

@section('title', 'User Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">User Management</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats and Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage user accounts, permissions, and restrictions</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <!-- Search Box -->
            <div class="relative">
                <input type="text" 
                       placeholder="Search users..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors w-64">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="relative">
                <select class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                    <option value="all">All Users</option>
                    <option value="active">Active Only</option>
                    <option value="banned">Banned</option>
                    <option value="recent">Recently Active</option>
                </select>
                <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $users->total() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-users text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $users->where('is_banned', false)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-user-check text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Banned Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $users->where('is_banned', true)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-user-slash text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Messages</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ round($users->avg('messages_count') ?? 0) }}
                    </p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <i class="fas fa-comment text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">User Accounts</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>User</span>
                                <i class="fas fa-sort text-gray-400 cursor-pointer hover:text-gray-600"></i>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Contact Info
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>Activity</span>
                                <i class="fas fa-sort text-gray-400 cursor-pointer hover:text-gray-600"></i>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $user->name }}
                                        @if($user->is_admin)
                                        <span class="ml-2 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs px-2 py-1 rounded-full">Admin</span>
                                        @endif
                                        @if($user->developer_mode)
                                        <span class="ml-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs px-2 py-1 rounded-full">Developer</span>
                                        @endif
                                        @if($user->has_special_api_privilege)
                                        <span class="ml-2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs px-2 py-1 rounded-full" title="Special API Creation Privilege - Can auto-create users when sending messages">
                                            <i class="fas fa-key mr-1"></i>Special API
                                        </span>
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
                            <div class="flex items-center space-x-4 text-sm">
                                <div class="flex items-center space-x-1 text-blue-600 dark:text-blue-400">
                                    <i class="fas fa-comment text-xs"></i>
                                    <span>{{ $user->messages_count ?? 0 }}</span>
                                </div>
                                <div class="flex items-center space-x-1 text-green-600 dark:text-green-400">
                                    <i class="fas fa-users text-xs"></i>
                                    <span>{{ ($user->conversations_as_user_one_count ?? 0) + ($user->conversations_as_user_two_count ?? 0) }}</span>
                                </div>
                                <div class="flex items-center space-x-1 text-purple-600 dark:text-purple-400">
                                    <i class="fas fa-clock text-xs"></i>
                                    <span>{{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'Never' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->is_banned)
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                <span class="text-sm font-medium text-red-600 dark:text-red-400">Banned</span>
                            </div>
                            @else
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">Active</span>
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- View Profile -->
                                <a href="{{ route('admin.users.stats', $user->id) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                                   title="View User Stats">
                                    <i class="fas fa-chart-bar mr-1.5 text-xs"></i>
                                    Stats
                                </a>

                                <!-- Ban/Unban Toggle -->
                                <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('POST')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $user->is_banned ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/30' : 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 hover:bg-orange-100 dark:hover:bg-orange-900/30' }}"
                                            title="{{ $user->is_banned ? 'Unban User' : 'Ban User' }}"
                                            onclick="return confirm('Are you sure you want to {{ $user->is_banned ? 'unban' : 'ban' }} this user?')">
                                        <i class="fas {{ $user->is_banned ? 'fa-check-circle' : 'fa-ban' }} mr-1.5 text-xs"></i>
                                        {{ $user->is_banned ? 'Unban' : 'Ban' }}
                                    </button>
                                </form>

                                <!-- Quick Actions Dropdown -->
                                <div class="relative">
                                    <button class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                            onclick="toggleDropdown('user-actions-{{ $user->id }}')">
                                        <i class="fas fa-ellipsis-h text-xs"></i>
                                    </button>
                                    <div id="user-actions-{{ $user->id }}" 
                                         class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10 hidden">
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-eye mr-3 text-xs"></i>
                                            View Details
                                        </a>
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                            <i class="fas fa-envelope mr-3 text-xs"></i>
                                            Send Message
                                        </a>
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20">
                                            <i class="fas fa-history mr-3 text-xs"></i>
                                            Activity Log
                                        </a>
                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                        @if($user->developer_mode)
                                        <!-- Special API Creation Privilege Toggle -->
                                        <form action="{{ route('admin.users.toggle-special-api-privilege', $user->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm {{ $user->has_special_api_privilege ? 'text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }} w-full text-left"
                                                    title="{{ $user->has_special_api_privilege ? 'Revoke Special API Creation Privilege' : 'Grant Special API Creation Privilege' }}"
                                                    onclick="return confirm('{{ $user->has_special_api_privilege ? 'Revoke' : 'Grant' }} Special API Creation Privilege? This will {{ $user->has_special_api_privilege ? 'prevent' : 'allow' }} auto-creating GekyChat users when sending messages to unregistered phone numbers.')">
                                                <i class="fas {{ $user->has_special_api_privilege ? 'fa-check-circle' : 'fa-circle' }} mr-3 text-xs"></i>
                                                {{ $user->has_special_api_privilege ? 'Special API Privilege (Active)' : 'Grant Special API Privilege' }}
                                            </button>
                                        </form>
                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                        @endif
                                        <form action="{{ route('admin.users.activate', $user->id) }}" method="POST">
                                            @csrf
                                            @method('POST')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 w-full text-left"
                                                    onclick="return confirm('Reset user suspension?')">
                                                <i class="fas fa-play-circle mr-3 text-xs"></i>
                                                Activate
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No users found</p>
                                <p class="text-sm mt-1">There are no users matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} results
                </div>
                <div class="flex space-x-2">
                    <!-- Previous Page -->
                    @if($users->onFirstPage())
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif

                    <!-- Page Numbers -->
                    @foreach($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                    @if($page == $users->currentPage())
                    <span class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium">
                        {{ $page }}
                    </span>
                    @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        {{ $page }}
                    </a>
                    @endif
                    @endforeach

                    <!-- Next Page -->
                    @if($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                    @else
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Bulk Actions Panel -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bulk Actions</h3>
        <div class="flex flex-wrap gap-3">
            <button class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-download mr-2"></i>
                Export Users
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-envelope mr-2"></i>
                Send Bulk Message
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-trash mr-2"></i>
                Delete Inactive
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    dropdown.classList.toggle('hidden');
    
    // Close other dropdowns
    document.querySelectorAll('.absolute[class*="user-actions"]').forEach(otherDropdown => {
        if (otherDropdown.id !== id) {
            otherDropdown.classList.add('hidden');
        }
    });
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.relative')) {
        document.querySelectorAll('.absolute[class*="user-actions"]').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }
});

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[type="text"]');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            // Implement search logic here
            console.log('Searching for:', e.target.value);
        });
    }

    // Filter functionality
    const filterSelect = document.querySelector('select');
    if (filterSelect) {
        filterSelect.addEventListener('change', function(e) {
            // Implement filter logic here
            console.log('Filtering by:', e.target.value);
        });
    }
});

// Auto-refresh data every 5 minutes
setInterval(() => {
    window.location.reload();
}, 300000);
</script>

<style>
/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Dark mode scrollbar */
.dark .table-responsive::-webkit-scrollbar-track {
    background: #374151;
}

.dark .table-responsive::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark .table-responsive::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
@endsection