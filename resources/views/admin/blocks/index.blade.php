@extends('layouts.admin')

@section('title', 'Blocks Analytics')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Blocks Analytics</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats and Privacy Notice -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Blocks Analytics</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">View-only interface for monitoring user block patterns</p>
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
                    <option value="user">User Blocks</option>
                </select>
                <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>
        </div>
    </div>

    <!-- Privacy Notice -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-shield-alt text-blue-600 dark:text-blue-400 text-lg mt-0.5"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Privacy Notice</h3>
                <div class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                    <p>This is a view-only interface. User blocks represent personal privacy decisions and cannot be modified by administrators to respect user autonomy.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Blocks</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalBlocks }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-ban text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unique Blockers</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $uniqueBlockers }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-user-slash text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unique Blocked Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $uniqueBlocked }}</p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <i class="fas fa-user-times text-purple-600 dark:text-purple-400 text-xl"></i>
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
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Details
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($blocks as $block)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
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
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Blocker • {{ $block->blocker->phone ?? 'N/A' }}
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
                                            Blocked User • {{ $block->blocked->phone ?? 'N/A' }}
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
                                <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded inline-block">
                                    <i class="fas fa-user mr-1"></i>
                                    Personal Privacy Block
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300">
                                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                User-Initiated
                            </span>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Permanent Block
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $block->created_at->format('M j, Y') }}</div>
                            <div>{{ $block->created_at->format('g:i A') }}</div>
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ $block->created_at->diffForHumans() }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button onclick="openBlockDetails({{ $block->id }})"
                                    class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-eye mr-1.5 text-xs"></i>
                                View Details
                            </button>
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

    <!-- Analytics Panel -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Analytics & Export</h3>
        <div class="flex flex-wrap gap-3">
            <button class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-download mr-2"></i>
                Export Analytics
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-chart-bar mr-2"></i>
                Generate Report
            </button>
            <button onclick="refreshData()" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
        </div>
    </div>
</div>

<!-- Block Details Modal -->
<div id="blockDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Block Details</h3>
                <button onclick="closeBlockDetails()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Privacy Notice -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shield-alt text-yellow-600 dark:text-yellow-400 text-lg mt-0.5"></i>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Privacy Protected</h4>
                        <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                            <p>This block represents a user's personal privacy decision and cannot be modified by administrators.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Block Relationship -->
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Block Relationship</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Blocker -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0">
                                    <span id="blockerInitial">U</span>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white" id="blockerName">Unknown User</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Blocker</div>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <div><strong>Phone:</strong> <span id="blockerPhone">N/A</span></div>
                                <div><strong>User ID:</strong> <span id="blockerId">-</span></div>
                            </div>
                        </div>

                        <!-- Blocked User -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-red-500 to-orange-600 rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0">
                                    <span id="blockedInitial">U</span>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white" id="blockedName">Unknown User</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Blocked User</div>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <div><strong>Phone:</strong> <span id="blockedPhone">N/A</span></div>
                                <div><strong>User ID:</strong> <span id="blockedId">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block Details -->
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Block Information</h4>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason Provided</label>
                                <div class="text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 min-h-[60px]" id="blockReason">
                                    No reason provided
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Block Type</label>
                                    <div class="text-sm text-gray-900 dark:text-white">User-Initiated Privacy Block</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300">
                                        Active
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Blocked On</label>
                                    <div class="text-sm text-gray-900 dark:text-white" id="blockDate">-</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duration</label>
                                    <div class="text-sm text-gray-900 dark:text-white">Permanent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button onclick="closeBlockDetails()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Block details data (in a real app, this would come from an API)
const blockDetails = {
    @foreach($blocks as $block)
    {{ $block->id }}: {
        blocker: {
            name: "{{ $block->blocker->name ?? 'Unknown User' }}",
            phone: "{{ $block->blocker->phone ?? 'N/A' }}",
            id: "{{ $block->blocker_id }}",
            initial: "{{ substr($block->blocker->name ?? 'U', 0, 1) }}"
        },
        blocked: {
            name: "{{ $block->blocked->name ?? 'Unknown User' }}",
            phone: "{{ $block->blocked->phone ?? 'N/A' }}",
            id: "{{ $block->blocked_user_id }}",
            initial: "{{ substr($block->blocked->name ?? 'U', 0, 1) }}"
        },
        reason: `{{ $block->reason ?? 'No reason provided' }}`,
        date: "{{ $block->created_at->format('M j, Y \\a\\t g:i A') }}"
    },
    @endforeach
};

function openBlockDetails(blockId) {
    const details = blockDetails[blockId];
    if (!details) return;

    // Populate modal with block details
    document.getElementById('blockerName').textContent = details.blocker.name;
    document.getElementById('blockerPhone').textContent = details.blocker.phone;
    document.getElementById('blockerId').textContent = details.blocker.id;
    document.getElementById('blockerInitial').textContent = details.blocker.initial;

    document.getElementById('blockedName').textContent = details.blocked.name;
    document.getElementById('blockedPhone').textContent = details.blocked.phone;
    document.getElementById('blockedId').textContent = details.blocked.id;
    document.getElementById('blockedInitial').textContent = details.blocked.initial;

    document.getElementById('blockReason').textContent = details.reason;
    document.getElementById('blockDate').textContent = details.date;

    // Show modal
    document.getElementById('blockDetailsModal').classList.remove('hidden');
}

function closeBlockDetails() {
    document.getElementById('blockDetailsModal').classList.add('hidden');
}

function refreshData() {
    window.location.reload();
}

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

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBlockDetails();
        }
    });
});

// Auto-refresh data every 5 minutes
setInterval(() => {
    refreshData();
}, 300000);
</script>

<style>
/* Custom scrollbar for table and modal */
.table-responsive::-webkit-scrollbar,
.modal-content::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.table-responsive::-webkit-scrollbar-track,
.modal-content::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb,
.modal-content::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover,
.modal-content::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Dark mode scrollbar */
.dark .table-responsive::-webkit-scrollbar-track,
.dark .modal-content::-webkit-scrollbar-track {
    background: #374151;
}

.dark .table-responsive::-webkit-scrollbar-thumb,
.dark .modal-content::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark .table-responsive::-webkit-scrollbar-thumb:hover,
.dark .modal-content::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
@endsection