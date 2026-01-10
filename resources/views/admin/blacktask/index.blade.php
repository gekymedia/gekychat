@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                📋 BlackTask Integration
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Manage todo list integration for AI Assistant
            </p>
        </div>

        <!-- Status Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Integration Status
                </h2>
                @if($isConfigured)
                    <span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full text-sm font-medium">
                        ✅ Configured
                    </span>
                @else
                    <span class="px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full text-sm font-medium">
                        ❌ Not Configured
                    </span>
                @endif
            </div>

            @if($connectionTest)
                <div class="mt-4 p-4 rounded-lg {{ $connectionTest['success'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">{{ $connectionTest['success'] ? '✅' : '❌' }}</span>
                        <div>
                            <p class="font-medium {{ $connectionTest['success'] ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                {{ $connectionTest['message'] }}
                            </p>
                            @if(isset($connectionTest['status']))
                                <p class="text-sm {{ $connectionTest['success'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    HTTP Status: {{ $connectionTest['status'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Configuration Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                Configuration
            </h2>

            <form id="blacktask-config-form" class="space-y-4">
                @csrf
                
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        BlackTask URL
                    </label>
                    <input 
                        type="url" 
                        id="url" 
                        name="url" 
                        value="{{ $blacktaskUrl }}"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="https://blacktask.com"
                        required
                    >
                </div>

                <div>
                    <label for="api_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        API Token
                    </label>
                    <input 
                        type="password" 
                        id="api_token" 
                        name="api_token" 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter BlackTask admin API token"
                        required
                    >
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Generate this token from BlackTask admin panel
                    </p>
                </div>

                <div class="flex gap-4">
                    <button 
                        type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition"
                    >
                        💾 Save Configuration
                    </button>
                    
                    <button 
                        type="button" 
                        id="test-connection-btn"
                        class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition"
                    >
                        🔌 Test Connection
                    </button>
                </div>
            </form>
        </div>

        <!-- Features Info -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                Available Commands
            </h2>

            <div class="space-y-4">
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">📝 Create Tasks</h3>
                    <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-1">
                        <li>Add task Buy groceries tomorrow</li>
                        <li>Create todo Call John on Friday</li>
                        <li>New reminder Meeting at 3pm urgent</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">👀 View Tasks</h3>
                    <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-1">
                        <li>Show my tasks</li>
                        <li>List my todos</li>
                        <li>What are my tasks?</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">✅ Complete Tasks</h3>
                    <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-1">
                        <li>Complete task 5</li>
                        <li>Mark task 3 as done</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">📊 Statistics</h3>
                    <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-1">
                        <li>Task statistics</li>
                        <li>Show my task stats</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('blacktask-config-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('/admin/blacktask/config', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': data._token
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Configuration saved successfully!');
            window.location.reload();
        } else {
            alert('❌ Failed to save configuration: ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
});

document.getElementById('test-connection-btn').addEventListener('click', async () => {
    const btn = document.getElementById('test-connection-btn');
    btn.disabled = true;
    btn.textContent = '🔄 Testing...';
    
    try {
        const response = await fetch('/admin/blacktask/test');
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Connection successful!');
        } else {
            alert('❌ Connection failed: ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '🔌 Test Connection';
    }
});
</script>
@endsection
