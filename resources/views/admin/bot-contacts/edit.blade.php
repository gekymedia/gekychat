@extends('layouts.admin')

@section('title', 'Edit Bot Contact')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Edit</span>
        </div>
    </li>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Bot Contact</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Update bot information</p>
        </div>

        @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-2"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <p class="text-red-800 dark:text-red-300">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Current Bot Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Current Bot Information</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Bot Number:</span>
                    <code class="ml-2 bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded font-mono">{{ $bot->bot_number }}</code>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Code:</span>
                    <code class="ml-2 bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded font-mono">{{ $bot->code }}</code>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.bot-contacts.update', $bot) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <!-- Bot Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Bot Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name', $bot->name) }}"
                           required
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors"
                           placeholder="e.g., Support Bot, AI Assistant">
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Description (Optional)
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors"
                              placeholder="Optional description of what this bot does...">{{ old('description', $bot->description) }}</textarea>
                </div>

                <!-- Active Status -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1"
                               {{ old('is_active', $bot->is_active) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Bot is active
                        </span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Inactive bots cannot log in
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.bot-contacts.show', $bot) }}" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
