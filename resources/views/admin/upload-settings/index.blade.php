@extends('layouts.admin')

@section('title', 'Upload Settings')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 dark:text-gray-400">Dashboard</a>
        <i class="fas fa-chevron-right mx-2 text-gray-400"></i>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload Settings</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Upload Settings</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage global video upload limits</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-400 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-400 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Global Settings Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Global Upload Limits</h2>
        
        <form action="{{ route('admin.upload-settings.update-global') }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        World Feed Max Duration (seconds)
                    </label>
                    <input 
                        type="number" 
                        name="world_feed_max_duration" 
                        value="{{ $globalSettings['world_feed_max_duration'] }}"
                        min="1" 
                        max="600" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Default: 180 seconds (3 minutes)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Status Max Duration (seconds)
                    </label>
                    <input 
                        type="number" 
                        name="status_max_duration" 
                        value="{{ $globalSettings['status_max_duration'] }}"
                        min="1" 
                        max="600" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Default: 180 seconds (3 minutes)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Chat Video Max Size (MB)
                    </label>
                    <input 
                        type="number" 
                        name="chat_video_max_size" 
                        value="{{ round($globalSettings['chat_video_max_size'] / 1048576, 1) }}"
                        min="1" 
                        max="100" 
                        step="0.1"
                        required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Default: 10 MB</p>
                </div>
            </div>
            
            <div class="mt-6">
                <button 
                    type="submit"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                >
                    Save Global Settings
                </button>
            </div>
        </form>
    </div>

    <!-- User Overrides Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">User Overrides</h2>
            <button 
                onclick="document.getElementById('create-override-form').classList.toggle('hidden')"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
            >
                Add User Override
            </button>
        </div>

        <!-- Create Override Form -->
        <div id="create-override-form" class="hidden mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <form action="{{ route('admin.upload-settings.create-override') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            User ID
                        </label>
                        <input 
                            type="number" 
                            name="user_id" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="Enter user ID"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            World Feed Max Duration (seconds)
                        </label>
                        <input 
                            type="number" 
                            name="world_feed_max_duration" 
                            min="1" 
                            max="600"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="Optional"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status Max Duration (seconds)
                        </label>
                        <input 
                            type="number" 
                            name="status_max_duration" 
                            min="1" 
                            max="600"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="Optional"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Chat Video Max Size (MB)
                        </label>
                        <input 
                            type="number" 
                            name="chat_video_max_size" 
                            min="1" 
                            max="100"
                            step="0.1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="Optional"
                        >
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                    >
                        Create Override
                    </button>
                    <button 
                        type="button"
                        onclick="document.getElementById('create-override-form').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- User Overrides Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">World Feed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Chat Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($userOverrides as $override)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $override->user->name ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $override->user->phone ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $override->world_feed_max_duration ?? 'Default' }}s
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $override->status_max_duration ?? 'Default' }}s
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $override->chat_video_max_size ? round($override->chat_video_max_size / 1048576, 1) . ' MB' : 'Default' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form action="{{ route('admin.upload-settings.delete-override', $override->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button 
                                        type="submit"
                                        onclick="return confirm('Are you sure you want to delete this override?')"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                No user overrides found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
