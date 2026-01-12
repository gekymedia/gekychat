@extends('layouts.admin')

@section('title', 'Email Log Details')

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
            <a href="{{ route('admin.email-logs.index') }}" class="text-sm font-medium text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">Email Logs</a>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Details</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Log Details</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Email processing information (content not shown for privacy)</p>
        </div>
        <a href="{{ route('admin.email-logs.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Logs
        </a>
    </div>

    <!-- Status Badge -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Processing Status</h3>
                @if($log->status === 'success')
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i>Successfully Processed
                </span>
                @elseif($log->status === 'failed')
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <i class="fas fa-times-circle mr-2"></i>Failed
                </span>
                @else
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    <i class="fas fa-ban mr-2"></i>Ignored
                </span>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500 dark:text-gray-400">Processed At</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $log->processed_at->format('M d, Y H:i:s') }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $log->processed_at->diffForHumans() }}
                </p>
            </div>
        </div>
    </div>

    <!-- Email Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- From Information -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-paper-plane mr-2 text-blue-500"></i>From
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Email Address</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">{{ $log->from_email }}</p>
                </div>
                @if($log->from_name)
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                    <p class="text-base text-gray-900 dark:text-white">{{ $log->from_name }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- To Information -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-inbox mr-2 text-green-500"></i>To (Recipients)
            </h3>
            <div class="space-y-2">
                @if(is_array($log->to_emails) && count($log->to_emails) > 0)
                    @foreach($log->to_emails as $toEmail)
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Recipient</p>
                        <p class="text-base font-medium text-gray-900 dark:text-white">
                            {{ is_array($toEmail) ? ($toEmail['address'] ?? $toEmail) : $toEmail }}
                        </p>
                    </div>
                    @endforeach
                @else
                <p class="text-sm text-gray-400 dark:text-gray-500">No recipients found</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Routing Information -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            <i class="fas fa-route mr-2 text-purple-500"></i>Routing Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Routed To Username</p>
                @if($log->routed_to_username)
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    <i class="fas fa-user mr-1"></i>{{ $log->routed_to_username }}
                </p>
                @else
                <p class="text-sm text-gray-400 dark:text-gray-500">Not routed</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">User Account</p>
                @if($log->routedToUser)
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    {{ $log->routedToUser->name ?? $log->routedToUser->phone }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500">ID: {{ $log->routedToUser->id }}</p>
                @else
                <p class="text-sm text-gray-400 dark:text-gray-500">User not found</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Conversation</p>
                @if($log->conversation_id)
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    <i class="fas fa-comments mr-1"></i>Conversation #{{ $log->conversation_id }}
                </p>
                @else
                <p class="text-sm text-gray-400 dark:text-gray-500">No conversation created</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Email Metadata -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            <i class="fas fa-info-circle mr-2 text-indigo-500"></i>Email Metadata
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Subject</p>
                <p class="text-base text-gray-900 dark:text-white">{{ $log->subject ?: '(No subject)' }}</p>
            </div>
            @if($log->message_id_header)
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Message ID</p>
                <p class="text-base font-mono text-sm text-gray-900 dark:text-white break-all">{{ $log->message_id_header }}</p>
            </div>
            @endif
            @if($log->message_id)
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Created Message</p>
                <p class="text-base font-medium text-gray-900 dark:text-white">
                    <i class="fas fa-envelope mr-1"></i>Message #{{ $log->message_id }}
                </p>
            </div>
            @endif
        </div>
    </div>

    <!-- Error Information (if failed) -->
    @if($log->status === 'failed' && $log->failure_reason)
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-red-900 dark:text-red-200 mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>Failure Reason
        </h3>
        <div class="space-y-4">
            <div>
                <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-1">Error Message</p>
                <p class="text-base text-red-900 dark:text-red-200">{{ $log->failure_reason }}</p>
            </div>
            @if($log->error_details)
            <div>
                <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-1">Error Details</p>
                <pre class="text-xs text-red-900 dark:text-red-200 bg-red-100 dark:bg-red-900/40 p-3 rounded overflow-x-auto">{{ $log->error_details }}</pre>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Privacy Notice -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl shadow-lg p-6">
        <div class="flex items-start">
            <i class="fas fa-shield-alt text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
            <div>
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-2">Privacy Notice</h3>
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    Email content is not stored in logs for privacy reasons. Only routing information, metadata, and processing status are recorded. This allows administrators to review email processing without accessing sensitive content.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
