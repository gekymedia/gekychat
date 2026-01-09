@extends('layouts.admin')

@section('title', 'Bot Contacts Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Bot Contacts</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Bot Contacts Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Create and manage bot contacts that can log in without SMS OTP</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="{{ route('admin.bot-contacts.create') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Add Bot Contact
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
            <p class="text-green-800 dark:text-green-300">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
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

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Bots</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $bots->total() }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-robot text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Bots</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $bots->where('is_active', true)->count() }}
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Inactive Bots</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $bots->where('is_active', false)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-pause-circle text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Bots Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Registered Bot Contacts</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $bots->firstItem() ?? 0 }}-{{ $bots->lastItem() ?? 0 }} of {{ $bots->total() }} bots
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Bot Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Bot Number
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            6-Digit Code
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Created
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($bots as $bot)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $bot->name }}
                                </div>
                                @if($bot->description)
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ Str::limit($bot->description, 60) }}
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="text-sm bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded font-mono">
                                {{ $bot->bot_number }}
                            </code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="text-sm bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded font-mono">
                                {{ $bot->code }}
                            </code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
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
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div>{{ $bot->created_at->format('M j, Y') }}</div>
                            <div>{{ $bot->created_at->format('g:i A') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('admin.bot-contacts.show', $bot) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                                   title="View Details">
                                    <i class="fas fa-eye mr-1.5 text-xs"></i>
                                    View
                                </a>
                                <a href="{{ route('admin.bot-contacts.edit', $bot) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                   title="Edit Bot">
                                    <i class="fas fa-edit mr-1.5 text-xs"></i>
                                    Edit
                                </a>
                                <form action="{{ route('admin.bot-contacts.regenerate-code', $bot) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 rounded-lg text-sm font-medium hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors"
                                            title="Regenerate Code"
                                            onclick="return confirm('Are you sure you want to regenerate the code? The old code will no longer work.')">
                                        <i class="fas fa-key mr-1.5 text-xs"></i>
                                        New Code
                                    </button>
                                </form>
                                @if($bot->bot_number !== '0000000000')
                                <form action="{{ route('admin.bot-contacts.destroy', $bot) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                                            title="Delete Bot"
                                            onclick="return confirm('Are you sure you want to delete this bot? This action cannot be undone.')">
                                        <i class="fas fa-trash mr-1.5 text-xs"></i>
                                        Delete
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <i class="fas fa-robot text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No bot contacts found</p>
                                <p class="text-sm mt-1">Create your first bot contact to get started.</p>
                                <a href="{{ route('admin.bot-contacts.create') }}" 
                                   class="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Bot Contact
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($bots->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ $bots->firstItem() }} to {{ $bots->lastItem() }} of {{ $bots->total() }} results
                </div>
                <div class="flex space-x-2">
                    @if($bots->onFirstPage())
                    <span class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $bots->previousPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif

                    @foreach($bots->getUrlRange(max(1, $bots->currentPage() - 2), min($bots->lastPage(), $bots->currentPage() + 2)) as $page => $url)
                    @if($page == $bots->currentPage())
                    <span class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium">
                        {{ $page }}
                    </span>
                    @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        {{ $page }}
                    </a>
                    @endif
                    @endforeach

                    @if($bots->hasMorePages())
                    <a href="{{ $bots->nextPageUrl() }}" class="px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
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

    <!-- Info Panel -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3">
            <i class="fas fa-info-circle mr-2"></i>
            About Bot Contacts
        </h3>
        <div class="text-blue-800 dark:text-blue-300 space-y-2 text-sm">
            <p>• Bot contacts can log in using their bot number and 6-digit code (no SMS required)</p>
            <p>• Bot numbers are automatically generated in sequence (0000000000, 0000000001, etc.)</p>
            <p>• Each bot gets a unique 6-digit code that serves as their OTP</p>
            <p>• The default GekyBot (0000000000) cannot be deleted</p>
            <p>• Bots behave like regular users but can be assigned special roles</p>
        </div>
    </div>
</div>
@endsection
