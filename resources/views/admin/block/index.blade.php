@extends('layouts.admin')

@section('title', 'Blocks Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Blocks Management</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats and Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Blocks Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage user blocks and restrictions</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <!-- Search Box -->
            <div class="relative">
                <input type="text" 
                       placeholder="Search blocks..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors w-64">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="relative">
                <select class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                    <option value="all">All Blocks</option>
                    <option value="active">Active Only</option>
                    <option value="admin">Admin Blocks</option>
                    <option value="user">User Blocks</option>
                    <option value="expired">Expired</option>
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Blocks</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $blocks->total() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-ban text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Blocks</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $blocks->where(function($block) {
                            return is_null($block->expires_at) || $block->expires_at->isFuture();
                        })->count() }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-user-clock text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Admin Blocks</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $blocks->where('blocked_by_admin', true)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-user-shield text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Expired</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $blocks->where('expires_at', '<', now())->count() }}
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-history text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Blocks Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Block Records</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $blocks->firstItem() ?? 0 }}-{{ $blocks->lastItem() ?? 0 }} of {{ $blocks->total() }} blocks
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Block Relationship
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Reason & Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($blocks as $block)
                    @php
                        $isExpired = $block->expires_at && $block->expires_at->isPast();
                        $isPermanent = is_null($block->expires_at);
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors {{ $isExpired ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4">
                            <div class="space-y-3">
                                <!-- Blocker -->
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-xs flex-shrink-0">
                                        {{ substr($block->blocker->name ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $block->blocker->name ?? 'Unknown User' }}
                                            @if($block->blocked_by_admin)
                                            <span class="ml-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs px-1.5 py-0.5 rounded">Admin</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Blocker
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Blocked User -->
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-orange-600 rounded-full flex items-center justify-center text-white font-semibold text-xs flex-shrink-0">
                                        {{ substr($block->blocked->name ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $block->blocked->name ?? 'Unknown User' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Blocked User
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="max-w-xs">
                                <div class="text-sm text-gray-900 dark:text-white mb-2">
                                    {{ $block->reason ?? 'No reason provided' }}
                                </div>
                                @if($block->blocked_by_admin)
                                <div class="text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded inline-block">
                                    <i class="fas fa-shield-alt mr-1"></i>
                                    Admin Block
                                </div>
                                @else
                                <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded inline-block">
                                    <i class="fas fa-user mr-1"></i>
                                    User Block
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($isExpired)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                <span class="w-2 h-2 bg-gray-500 rounded-full mr-2"></span>
                                Expired
                            </span>
                            @elseif($isPermanent)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-300">
                                <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                Permanent
                            </span>
                            @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 dark:bg-orange-900/20 text-orange-800 dark:text-orange-300">
                                <span class="w-2 h-2 bg-orange-500 rounded-full mr-2"></span>
                                Temporary
                            </span>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Expires: {{ $block->expires_at->format('M j, Y') }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $block->created_at->format('M j, Y') }}</div>
                            <div>{{ $block->created_at->format('g:i A') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                @if(!$isExpired)
                                <!-- Unblock Button -->
                                <form action="{{ route('admin.blocks.destroy', $block->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-lg text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                                            title="Remove Block"
                                            onclick="return confirm('Are you sure you want to remove this block?')">
                                        <i class="fas fa-unlock mr-1.5 text-xs"></i>
                                        Unblock
                                    </button>
                                </form>
                                @else
                                <!-- Delete Expired Block -->
                                <form action="{{ route('admin.blocks.destroy', $block->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                                            title="Delete Expired Block"
                                            onclick="return confirm('Are you sure you want to delete this expired block?')">
                                        <i class="fas fa-trash mr-1.5 text-xs"></i>
                                        Delete
                                    </button>
                                </form>
                                @endif

                                <!-- Quick Actions Dropdown -->
                                <div class="relative">
                                    <button class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                            onclick="toggleDropdown('block-actions-{{ $block->id }}')">
                                        <i class="fas fa-ellipsis-h text-xs"></i>
                                    </button>
                                    <div id="block-actions-{{ $block->id }}" 
                                         class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10 hidden">
                                        <!-- View User Details -->
                                        <a href="{{ route('admin.users.stats', $block->blocked_user_id) }}" 
                                           class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-user mr-3 text-xs"></i>
                                            View Blocked User
                                        </a>
                                        
                                        <!-- Extend Block -->
                                        @if(!$isExpired && !$isPermanent)
                                        <button onclick="extendBlock({{ $block->id }})"
                                                class="flex items-center px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 w-full text-left">
                                            <i class="fas fa-clock mr-3 text-xs"></i>
                                            Extend Block
                                        </button>
                                        @endif
                                        
                                        <!-- Make Permanent -->
                                        @if(!$isPermanent && !$isExpired)
                                        <form action="{{ route('admin.blocks.make-permanent', $block->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left"
                                                    onclick="return confirm('Make this block permanent?')">
                                                <i class="fas fa-infinity mr-3 text-xs"></i>
                                                Make Permanent
                                            </button>
                                        </form>
                                        @endif
                                        
                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                        
                                        <!-- Delete Block -->
                                        <form action="{{ route('admin.blocks.destroy', $block->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left"
                                                    onclick="return confirm('Are you sure you want to delete this block?')">
                                                <i class="fas fa-trash mr-3 text-xs"></i>
                                                Delete Record
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
                                <i class="fas fa-ban text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No blocks found</p>
                                <p class="text-sm mt-1">There are no block records matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($blocks->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ $blocks->firstItem() }} to {{ $blocks->lastItem() }} of {{ $blocks->total() }} results
                </div>
                <div class="flex space-x-2">
                    <!-- Previous Page -->
                    @if($blocks->onFirstPage())
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $blocks->previousPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif

                    <!-- Page Numbers -->
                    @foreach($blocks->getUrlRange(max(1, $blocks->currentPage() - 2), min($blocks->lastPage(), $blocks->currentPage() + 2)) as $page => $url)
                    @if($page == $blocks->currentPage())
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
                    @if($blocks->hasMorePages())
                    <a href="{{ $blocks->nextPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
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
                Export Blocks
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-unlock mr-2"></i>
                Remove Expired Blocks
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
        </div>
    </div>
</div>

<!-- Extend Block Modal -->
<div id="extendBlockModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Extend Block Duration</h3>
                <button onclick="closeExtendModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="extendBlockForm" method="POST">
                @csrf
                @method('PATCH')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            New Expiration Date
                        </label>
                        <input type="datetime-local" 
                               name="expires_at"
                               class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors"
                               min="{{ now()->format('Y-m-d\TH:i') }}"
                               required>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" onclick="closeExtendModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Extend Block
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentBlockId = null;

function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    dropdown.classList.toggle('hidden');
    
    // Close other dropdowns
    document.querySelectorAll('.absolute[class*="block-actions"]').forEach(otherDropdown => {
        if (otherDropdown.id !== id) {
            otherDropdown.classList.add('hidden');
        }
    });
}

function extendBlock(blockId) {
    currentBlockId = blockId;
    const form = document.getElementById('extendBlockForm');
    form.action = `/admin/blocks/${blockId}/extend`;
    document.getElementById('extendBlockModal').classList.remove('hidden');
}

function closeExtendModal() {
    document.getElementById('extendBlockModal').classList.add('hidden');
    currentBlockId = null;
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.relative')) {
        document.querySelectorAll('.absolute[class*="block-actions"]').forEach(dropdown => {
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