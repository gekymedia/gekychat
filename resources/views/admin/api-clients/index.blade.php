@extends('layouts.admin')

@section('title', 'API Clients Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">API Clients Management</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats and Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">API Clients Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage registered API clients and their access</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <!-- Search Box -->
            <div class="relative">
                <input type="text" 
                       placeholder="Search API clients..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors w-64">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="relative">
                <select class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                    <option value="all">All Clients</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="revoked">Revoked</option>
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Clients</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $clients->total() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-code text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $clients->where('status', 'active')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Suspended</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $clients->where('status', 'suspended')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-pause-circle text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Revoked</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $clients->where('status', 'revoked')->count() }}
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- API Clients Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Registered API Clients</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $clients->firstItem() ?? 0 }}-{{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }} clients
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Client Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Owner
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Last Used
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($clients as $client)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="max-w-xs">
                                <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                    {{ $client->name ?? 'Unnamed Client' }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                    ID: <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">{{ $client->id }}</code>
                                </div>
                                @if($client->description)
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ Str::limit($client->description, 80) }}
                                </div>
                                @endif
                                @if($client->webhook_url)
                                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    <i class="fas fa-link mr-1"></i>
                                    Webhook: {{ Str::limit($client->webhook_url, 40) }}
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-xs flex-shrink-0">
                                    {{ substr($client->user->name ?? 'U', 0, 1) }}
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $client->user->name ?? 'Unknown User' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $client->user->email ?? $client->user->phone ?? 'No contact' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'active' => ['bg' => 'bg-green-100 dark:bg-green-900/20', 'text' => 'text-green-800 dark:text-green-300', 'dot' => 'bg-green-500'],
                                    'suspended' => ['bg' => 'bg-orange-100 dark:bg-orange-900/20', 'text' => 'text-orange-800 dark:text-orange-300', 'dot' => 'bg-orange-500'],
                                    'revoked' => ['bg' => 'bg-red-100 dark:bg-red-900/20', 'text' => 'text-red-800 dark:text-red-300', 'dot' => 'bg-red-500'],
                                ];
                                $statusConfig = $statusColors[$client->status] ?? $statusColors['active'];
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                                <span class="w-2 h-2 {{ $statusConfig['dot'] }} rounded-full mr-2"></span>
                                {{ ucfirst($client->status) }}
                            </span>
                            @if($client->last_used_at)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Used: {{ $client->last_used_at->diffForHumans() }}
                            </div>
                            @else
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                Never used
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $client->created_at->format('M j, Y') }}</div>
                            <div>{{ $client->created_at->format('g:i A') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- Status Update Buttons -->
                                @if($client->status !== 'active')
                                <form action="{{ route('admin.api-clients.update-status', $client->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-lg text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                                            title="Activate Client">
                                        <i class="fas fa-play mr-1.5 text-xs"></i>
                                        Activate
                                    </button>
                                </form>
                                @endif

                                @if($client->status !== 'suspended')
                                <form action="{{ route('admin.api-clients.update-status', $client->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="suspended">
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 rounded-lg text-sm font-medium hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors"
                                            title="Suspend Client"
                                            onclick="return confirm('Are you sure you want to suspend this API client?')">
                                        <i class="fas fa-pause mr-1.5 text-xs"></i>
                                        Suspend
                                    </button>
                                </form>
                                @endif

                                @if($client->status !== 'revoked')
                                <form action="{{ route('admin.api-clients.update-status', $client->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="revoked">
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                                            title="Revoke Client"
                                            onclick="return confirm('Are you sure you want to revoke this API client? This action cannot be undone.')">
                                        <i class="fas fa-ban mr-1.5 text-xs"></i>
                                        Revoke
                                    </button>
                                </form>
                                @endif

                                <!-- Quick Actions Dropdown -->
                                <div class="relative">
                                    <button class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                            onclick="toggleDropdown('client-actions-{{ $client->id }}')">
                                        <i class="fas fa-ellipsis-h text-xs"></i>
                                    </button>
                                    <div id="client-actions-{{ $client->id }}" 
                                         class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10 hidden">
                                        <!-- View Details -->
                                        <button onclick="showClientDetails({{ $client->id }})"
                                                class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 w-full text-left">
                                            <i class="fas fa-eye mr-3 text-xs"></i>
                                            View Details
                                        </button>
                                        
                                        <!-- View Owner -->
                                        <a href="{{ route('admin.users.stats', $client->user_id) }}" 
                                           class="flex items-center px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                            <i class="fas fa-user mr-3 text-xs"></i>
                                            View Owner
                                        </a>
                                        
                                        <!-- Usage Statistics -->
                                        <button onclick="showClientStats({{ $client->id }})"
                                                class="flex items-center px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 w-full text-left">
                                            <i class="fas fa-chart-bar mr-3 text-xs"></i>
                                            Usage Stats
                                        </button>
                                        
                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                        
                                        <!-- Regenerate Secret -->
                                        <form action="{{ route('admin.api-clients.regenerate-secret', $client->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 w-full text-left"
                                                    onclick="return confirm('Regenerate client secret? This will invalidate the current secret.')">
                                                <i class="fas fa-key mr-3 text-xs"></i>
                                                Regenerate Secret
                                            </button>
                                        </form>
                                        
                                        <!-- Delete Client -->
                                        <form action="{{ route('admin.api-clients.destroy', $client->id) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left"
                                                    onclick="return confirm('Are you sure you want to delete this API client? This action cannot be undone.')">
                                                <i class="fas fa-trash mr-3 text-xs"></i>
                                                Delete Client
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
                                <i class="fas fa-code text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No API clients found</p>
                                <p class="text-sm mt-1">There are no API clients registered yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($clients->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ $clients->firstItem() }} to {{ $clients->lastItem() }} of {{ $clients->total() }} results
                </div>
                <div class="flex space-x-2">
                    <!-- Previous Page -->
                    @if($clients->onFirstPage())
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $clients->previousPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif

                    <!-- Page Numbers -->
                    @foreach($clients->getUrlRange(max(1, $clients->currentPage() - 2), min($clients->lastPage(), $clients->currentPage() + 2)) as $page => $url)
                    @if($page == $clients->currentPage())
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
                    @if($clients->hasMorePages())
                    <a href="{{ $clients->nextPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
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
                Export Clients
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-check-circle mr-2"></i>
                Activate All
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh Data
            </button>
        </div>
    </div>
</div>

<!-- Client Details Modal -->
<div id="clientDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">API Client Details</h3>
                <button onclick="closeClientDetails()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="clientDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            
            <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button onclick="closeClientDetails()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentClientId = null;

function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    dropdown.classList.toggle('hidden');
    
    // Close other dropdowns
    document.querySelectorAll('.absolute[class*="client-actions"]').forEach(otherDropdown => {
        if (otherDropdown.id !== id) {
            otherDropdown.classList.add('hidden');
        }
    });
}

function showClientDetails(clientId) {
    currentClientId = clientId;
    
    // In a real implementation, you would fetch the client details via AJAX
    const modalContent = document.getElementById('clientDetailsContent');
    modalContent.innerHTML = `
        <div class="space-y-6">
            <!-- Client Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Client Name</h4>
                    <p class="text-gray-900 dark:text-white">Loading...</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Status</h4>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-300">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                        Loading...
                    </span>
                </div>
            </div>
            
            <!-- Client ID & Secret -->
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Credentials</h4>
                <div class="space-y-2">
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400">Client ID</label>
                        <div class="flex items-center space-x-2">
                            <code class="text-sm bg-white dark:bg-gray-600 px-2 py-1 rounded flex-1">Loading...</code>
                            <button class="px-2 py-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400">Client Secret</label>
                        <div class="flex items-center space-x-2">
                            <code class="text-sm bg-white dark:bg-gray-600 px-2 py-1 rounded flex-1">••••••••••••••••</code>
                            <button class="px-2 py-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="px-2 py-1 text-xs text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-200 transition-colors">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Created</h4>
                    <p class="text-gray-900 dark:text-white">Loading...</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Last Used</h4>
                    <p class="text-gray-900 dark:text-white">Loading...</p>
                </div>
            </div>
            
            <!-- Permissions -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Permissions</h4>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300">
                        <i class="fas fa-check mr-1"></i>
                        read:messages
                    </span>
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300">
                        <i class="fas fa-check mr-1"></i>
                        send:messages
                    </span>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('clientDetailsModal').classList.remove('hidden');
}

function closeClientDetails() {
    document.getElementById('clientDetailsModal').classList.add('hidden');
    currentClientId = null;
}

function showClientStats(clientId) {
    // Implement client statistics view
    alert('Client statistics feature to be implemented');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.relative')) {
        document.querySelectorAll('.absolute[class*="client-actions"]').forEach(dropdown => {
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