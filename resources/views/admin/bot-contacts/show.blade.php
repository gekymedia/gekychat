@extends('layouts.admin')

@section('title', 'Bot Contact Details')

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
            <a href="{{ route('admin.bot-contacts.index') }}" class="text-sm font-medium text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                Bot Contacts
            </a>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $bot->name }}</span>
        </div>
    </li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $bot->name }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Bot contact details and credentials</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.bot-contacts.edit', $bot) }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
            <a href="{{ route('admin.bot-contacts.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
            <p class="text-green-800 dark:text-green-300">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    <!-- Bot Details Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Bot Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Bot Name -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Bot Name</label>
                <p class="text-gray-900 dark:text-white text-lg font-medium">{{ $bot->name }}</p>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Status</label>
                @if($bot->is_active)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-300">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                    Active
                </span>
                @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                    <span class="w-2 h-2 bg-gray-500 rounded-full mr-2"></span>
                    Inactive
                </span>
                @endif
            </div>

            <!-- Bot Number -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Bot Number</label>
                <div class="flex items-center space-x-2">
                    <code class="text-lg bg-gray-100 dark:bg-gray-700 px-3 py-2 rounded font-mono text-gray-900 dark:text-white">
                        {{ $bot->bot_number }}
                    </code>
                    <button onclick="copyToClipboard('{{ $bot->bot_number }}')" 
                            class="px-3 py-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use this number to log in as this bot</p>
            </div>

            <!-- 6-Digit Code -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">6-Digit Code</label>
                <div class="flex items-center space-x-2">
                    <code class="text-lg bg-gray-100 dark:bg-gray-700 px-3 py-2 rounded font-mono text-gray-900 dark:text-white">
                        {{ $bot->code }}
                    </code>
                    <button onclick="copyToClipboard('{{ $bot->code }}')" 
                            class="px-3 py-2 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter this code when logging in (no SMS sent)</p>
            </div>

            <!-- Description -->
            @if($bot->description)
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Description</label>
                <p class="text-gray-900 dark:text-white">{{ $bot->description }}</p>
            </div>
            @endif

            <!-- Created At -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Created</label>
                <p class="text-gray-900 dark:text-white">
                    {{ $bot->created_at->format('F j, Y g:i A') }}
                </p>
            </div>

            <!-- Updated At -->
            <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Last Updated</label>
                <p class="text-gray-900 dark:text-white">
                    {{ $bot->updated_at->format('F j, Y g:i A') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Actions Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Actions</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Regenerate Code -->
            <form action="{{ route('admin.bot-contacts.regenerate-code', $bot) }}" method="POST">
                @csrf
                <button type="submit" 
                        class="w-full flex items-center justify-center px-4 py-3 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors"
                        onclick="return confirm('Are you sure you want to regenerate the code? The old code will no longer work.')">
                    <i class="fas fa-key mr-2"></i>
                    Regenerate Code
                </button>
            </form>

            <!-- Edit Bot -->
            <a href="{{ route('admin.bot-contacts.edit', $bot) }}" 
               class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-edit mr-2"></i>
                Edit Bot
            </a>

            @if($bot->bot_number !== '0000000000')
            <!-- Delete Bot -->
            <form action="{{ route('admin.bot-contacts.destroy', $bot) }}" method="POST" class="md:col-span-2">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="w-full flex items-center justify-center px-4 py-3 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors"
                        onclick="return confirm('Are you sure you want to delete this bot? This action cannot be undone.')">
                    <i class="fas fa-trash mr-2"></i>
                    Delete Bot
                </button>
            </form>
            @endif
        </div>
    </div>

    <!-- Login Instructions -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3">
            <i class="fas fa-info-circle mr-2"></i>
            How to Log In as This Bot
        </h3>
        <div class="text-blue-800 dark:text-blue-300 space-y-2 text-sm">
            <p><strong>1.</strong> Enter the bot number: <code class="bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded">{{ $bot->bot_number }}</code></p>
            <p><strong>2.</strong> No SMS will be sent (this is a bot number)</p>
            <p><strong>3.</strong> Enter the 6-digit code: <code class="bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded">{{ $bot->code }}</code></p>
            <p><strong>4.</strong> You'll be logged in as this bot</p>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        toast.innerHTML = '<i class="fas fa-check mr-2"></i>Copied to clipboard!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }).catch(err => {
        console.error('Copy failed:', err);
        alert('Failed to copy to clipboard');
    });
}
</script>
@endsection
