@extends('layouts.admin')

@section('title', 'Reports Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Reports Management</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats and Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reports Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Review and manage user-submitted reports</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <!-- Search Box -->
            <div class="relative">
                <input type="text" 
                       placeholder="Search reports..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors w-64">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="relative">
                <select class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                    <option value="all">All Reports</option>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="resolved">Resolved</option>
                    <option value="dismissed">Dismissed</option>
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Reports</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $reports->total() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-flag text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pendingReportsCount }}</p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Resolved</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $reports->where('status', 'resolved')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Dismissed</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $reports->where('status', 'dismissed')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">User Reports</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $reports->firstItem() ?? 0 }}-{{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} reports
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Report Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Users Involved
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
                    @forelse($reports as $report)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="max-w-xs">
                                <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                    {{ $report->reason }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ Str::limit($report->description, 100) }}
                                </div>
                                @if($report->admin_notes)
                                <div class="mt-2 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                                    <strong>Admin Notes:</strong> {{ $report->admin_notes }}
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-xs flex-shrink-0">
                                        {{ substr($report->reporter->name ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $report->reporter->name ?? 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Reporter
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-orange-600 rounded-full flex items-center justify-center text-white font-semibold text-xs flex-shrink-0">
                                        {{ substr($report->reportedUser->name ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $report->reportedUser->name ?? 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Reported User
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'pending' => ['bg' => 'bg-orange-100 dark:bg-orange-900/20', 'text' => 'text-orange-800 dark:text-orange-300', 'dot' => 'bg-orange-500'],
                                    'reviewed' => ['bg' => 'bg-blue-100 dark:bg-blue-900/20', 'text' => 'text-blue-800 dark:text-blue-300', 'dot' => 'bg-blue-500'],
                                    'resolved' => ['bg' => 'bg-green-100 dark:bg-green-900/20', 'text' => 'text-green-800 dark:text-green-300', 'dot' => 'bg-green-500'],
                                    'dismissed' => ['bg' => 'bg-red-100 dark:bg-red-900/20', 'text' => 'text-red-800 dark:text-red-300', 'dot' => 'bg-red-500'],
                                ];
                                $statusConfig = $statusColors[$report->status] ?? $statusColors['pending'];
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                                <span class="w-2 h-2 {{ $statusConfig['dot'] }} rounded-full mr-2"></span>
                                {{ ucfirst($report->status) }}
                            </span>
                            @if($report->reviewed_by && $report->reviewed_at)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Reviewed: {{ $report->reviewed_at->format('M j, Y') }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $report->created_at->format('M j, Y') }}</div>
                            <div>{{ $report->created_at->format('g:i A') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- Quick Actions -->
                                <div class="relative">
                                    <button class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                            onclick="toggleDropdown('report-actions-{{ $report->id }}')">
                                        <i class="fas fa-ellipsis-h text-xs"></i>
                                    </button>
                                    <div id="report-actions-{{ $report->id }}" 
                                         class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10 hidden">
                                        <!-- View Details -->
                                        <button onclick="showReportDetails({{ $report->id }})"
                                                class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 w-full text-left">
                                            <i class="fas fa-eye mr-3 text-xs"></i>
                                            View Details
                                        </button>
                                        
                                        <!-- Status Update Options -->
                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                        <form action="{{ route('admin.reports.update', $report->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="reviewed">
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 w-full text-left">
                                                <i class="fas fa-search mr-3 text-xs"></i>
                                                Mark as Reviewed
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.reports.update', $report->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 w-full text-left">
                                                <i class="fas fa-check mr-3 text-xs"></i>
                                                Mark as Resolved
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.reports.update', $report->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="dismissed">
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left">
                                                <i class="fas fa-times mr-3 text-xs"></i>
                                                Dismiss Report
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Status Update -->
                                <form action="{{ route('admin.reports.update', $report->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-lg text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                                            title="Resolve Report">
                                        <i class="fas fa-check mr-1.5 text-xs"></i>
                                        Resolve
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <i class="fas fa-flag text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No reports found</p>
                                <p class="text-sm mt-1">There are no reports matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($reports->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ $reports->firstItem() }} to {{ $reports->lastItem() }} of {{ $reports->total() }} results
                </div>
                <div class="flex space-x-2">
                    <!-- Previous Page -->
                    @if($reports->onFirstPage())
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $reports->previousPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif

                    <!-- Page Numbers -->
                    @foreach($reports->getUrlRange(max(1, $reports->currentPage() - 2), min($reports->lastPage(), $reports->currentPage() + 2)) as $page => $url)
                    @if($page == $reports->currentPage())
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
                    @if($reports->hasMorePages())
                    <a href="{{ $reports->nextPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
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
                Export Reports
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-check-circle mr-2"></i>
                Mark All as Reviewed
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div id="reportDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Report Details</h3>
                <button onclick="closeReportDetails()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="reportDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button onclick="closeReportDetails()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Close
                </button>
                <button onclick="resolveCurrentReport()" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Mark as Resolved
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentReportId = null;

function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    dropdown.classList.toggle('hidden');
    
    // Close other dropdowns
    document.querySelectorAll('.absolute[class*="report-actions"]').forEach(otherDropdown => {
        if (otherDropdown.id !== id) {
            otherDropdown.classList.add('hidden');
        }
    });
}

function showReportDetails(reportId) {
    currentReportId = reportId;
    
    // In a real implementation, you would fetch the report details via AJAX
    // For now, we'll simulate with static content
    const modalContent = document.getElementById('reportDetailsContent');
    modalContent.innerHTML = `
        <div class="space-y-6">
            <!-- Report Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Report Reason</h4>
                    <p class="text-gray-900 dark:text-white" id="reportReason">Loading...</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Status</h4>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 dark:bg-orange-900/20 text-orange-800 dark:text-orange-300" id="reportStatus">
                        <span class="w-2 h-2 bg-orange-500 rounded-full mr-2"></span>
                        Loading...
                    </span>
                </div>
            </div>
            
            <!-- Description -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Description</h4>
                <p class="text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-4 rounded-lg" id="reportDescription">Loading...</p>
            </div>
            
            <!-- Users Involved -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Reporter</h4>
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                            U
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white" id="reporterName">Loading...</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Reporter</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Reported User</h4>
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="w-10 h-10 bg-gradient-to-r from-red-500 to-orange-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                            U
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white" id="reportedUserName">Loading...</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Reported User</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Notes -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Admin Notes</h4>
                <textarea id="adminNotes" class="w-full h-24 p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors" placeholder="Add admin notes here..."></textarea>
                <button onclick="saveAdminNotes()" class="mt-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    Save Notes
                </button>
            </div>
        </div>
    `;
    
    // Simulate loading data
    setTimeout(() => {
        document.getElementById('reportReason').textContent = 'Inappropriate Content';
        document.getElementById('reportDescription').textContent = 'User posted offensive content in the group chat. Multiple users have complained about this behavior.';
        document.getElementById('reporterName').textContent = 'John Doe';
        document.getElementById('reportedUserName').textContent = 'Jane Smith';
    }, 500);
    
    document.getElementById('reportDetailsModal').classList.remove('hidden');
}

function closeReportDetails() {
    document.getElementById('reportDetailsModal').classList.add('hidden');
    currentReportId = null;
}

function resolveCurrentReport() {
    if (currentReportId) {
        // In a real implementation, submit a form to update the report status
        alert(`Report ${currentReportId} marked as resolved`);
        closeReportDetails();
        // Optionally refresh the page or update the table
        window.location.reload();
    }
}

function saveAdminNotes() {
    const notes = document.getElementById('adminNotes').value;
    // In a real implementation, save notes via AJAX
    console.log('Saving admin notes:', notes);
    alert('Admin notes saved successfully');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.relative')) {
        document.querySelectorAll('.absolute[class*="report-actions"]').forEach(dropdown => {
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