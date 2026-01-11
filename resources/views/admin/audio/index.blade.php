@extends('layouts.admin')

@section('title', 'Audio Library Management')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Audio Library Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage audio tracks and their usage</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('audio.browse') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" 
               target="_blank">
                <i class="fas fa-search mr-2"></i>
                Browse Audio
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Audio</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-music text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['active']) }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">CC0 (Public Domain)</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($stats['cc0']) }}</p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <i class="fas fa-globe text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Usage</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($stats['total_usage']) }}</p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-chart-line text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <form method="GET" action="{{ route('admin.audio.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <input type="text" 
                       name="search" 
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                       placeholder="Search audio..." 
                       value="{{ request('search') }}">
            </div>
            <div>
                <select name="status" 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <select name="license" 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Licenses</option>
                    <option value="CC0" {{ request('license') == 'CC0' ? 'selected' : '' }}>CC0</option>
                    <option value="Attribution" {{ request('license') == 'Attribution' ? 'selected' : '' }}>Attribution</option>
                </select>
            </div>
            <div>
                <button type="submit" 
                        class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Audio List -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Artist</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">License</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($audio as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item->name }}</div>
                            @if($item->attribution_required)
                                <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                                    <i class="fas fa-info-circle"></i> Attribution required
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->freesound_username ?? 'Unknown' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->formatted_duration }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ str_contains($item->license_type, 'CC0') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                {{ $item->license_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($item->usage_count) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $item->validation_status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($item->validation_status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                    {{ ucfirst($item->validation_status) }}
                                </span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $item->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-2">
                                <button class="p-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors preview-audio" 
                                        data-url="{{ $item->preview_url }}"
                                        title="Preview">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="p-2 {{ $item->is_active ? 'text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300' : 'text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300' }} hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors toggle-status" 
                                        data-id="{{ $item->id }}"
                                        title="{{ $item->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="fas fa-{{ $item->is_active ? 'pause' : 'play' }}"></i>
                                </button>
                                <button class="p-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors view-details" 
                                        data-id="{{ $item->id }}"
                                        title="View Details">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-music text-4xl mb-4 block"></i>
                            <p class="text-lg">No audio found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($audio->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $audio->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
let audioPlayer = new Audio();
let currentlyPlaying = null;

// Preview audio
document.querySelectorAll('.preview-audio').forEach(btn => {
    btn.addEventListener('click', function() {
        const url = this.dataset.url;
        
        if (currentlyPlaying === url) {
            audioPlayer.pause();
            currentlyPlaying = null;
            this.innerHTML = '<i class="fas fa-play"></i>';
        } else {
            audioPlayer.pause();
            audioPlayer.src = url;
            audioPlayer.play();
            currentlyPlaying = url;
            
            // Reset all buttons
            document.querySelectorAll('.preview-audio').forEach(b => {
                b.innerHTML = '<i class="fas fa-play"></i>';
            });
            this.innerHTML = '<i class="fas fa-stop"></i>';
            
            // Auto-stop after 10 seconds
            setTimeout(() => {
                if (currentlyPlaying === url) {
                    audioPlayer.pause();
                    currentlyPlaying = null;
                    this.innerHTML = '<i class="fas fa-play"></i>';
                }
            }, 10000);
        }
    });
});

// Toggle status
document.querySelectorAll('.toggle-status').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const icon = this.querySelector('i');
        const originalIcon = icon.className;
        
        // Show loading state
        icon.className = 'fas fa-spinner fa-spin';
        this.disabled = true;
        
        try {
            const response = await fetch(`/admin/audio/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            
            if (response.ok) {
                location.reload();
            } else {
                icon.className = originalIcon;
                this.disabled = false;
                alert('Failed to toggle status');
            }
        } catch (error) {
            console.error('Error:', error);
            icon.className = originalIcon;
            this.disabled = false;
            alert('Failed to toggle status');
        }
    });
});

// View details (placeholder for now)
document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        alert('Details view coming soon for audio ID: ' + id);
    });
});
</script>
@endpush
@endsection
