@extends('layouts.admin')

@section('title', 'Issue Report #' . $report->id)

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <a href="{{ route('admin.issue-reports.index') }}" class="text-sm text-indigo-600 hover:underline">← Issue Reports</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Report #{{ $report->id }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Submitted {{ $report->created_at->format('M j, Y H:i') }} ({{ $report->created_at->diffForHumans() }})
                · <span class="capitalize">{{ $report->source }}</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.product-analytics.user', ['userId' => $report->user_id]) }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                <i class="fas fa-chart-line mr-1"></i> User analytics
            </a>
            @if($report->user)
            <a href="{{ route('admin.users.index') }}?q={{ $report->user_id }}"
               class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-200">
                User #{{ $report->user_id }}
            </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 rounded-full text-sm font-medium capitalize bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">
                        {{ $report->category }}
                    </span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium capitalize
                        {{ $report->status === 'resolved' ? 'bg-green-100 text-green-800' : ($report->status === 'reviewed' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                        {{ $report->status }}
                    </span>
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Description</h3>
                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ $report->description }}</p>
            </div>

            @if($report->screenshot_path)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Screenshot</h3>
                <a href="{{ \App\Helpers\UrlHelper::secureStorageUrl($report->screenshot_path) }}" target="_blank">
                    <img src="{{ \App\Helpers\UrlHelper::secureStorageUrl($report->screenshot_path) }}"
                         alt="Screenshot"
                         class="max-w-full rounded-lg border dark:border-gray-700 max-h-96 object-contain">
                </a>
            </div>
            @endif

            @if($report->diagnostics)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Diagnostics (user opted in)</h3>
                <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-800 dark:text-gray-200">{{ json_encode($report->diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            @endif

            @if($report->admin_reply)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border-l-4 border-indigo-500">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Reply sent to user</h3>
                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $report->admin_reply }}</p>
                <p class="text-xs text-gray-500 mt-3">
                    {{ $report->admin_reply_at?->format('M j, Y H:i') }}
                    @if($report->repliedBy) · by {{ $report->repliedBy->name ?? 'Admin' }} @endif
                </p>
            </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Reply to user</h3>
                <p class="text-sm text-gray-500 mb-4">Sends a push notification and email (if the user has one). Does not include chat history.</p>
                <form method="post" action="{{ route('admin.issue-reports.reply', $report) }}" class="space-y-4">
                    @csrf
                    <textarea name="admin_reply" rows="4" required minlength="3" maxlength="5000"
                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                              placeholder="Thanks for reporting this. We fixed it in the latest update…">{{ old('admin_reply') }}</textarea>
                    @error('admin_reply')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        Send reply
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Reporter</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Name</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $report->user?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Phone</dt><dd>{{ $report->user?->phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd>{{ $report->user?->email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">User ID</dt><dd>#{{ $report->user_id }}</dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Device</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Platform</dt><dd class="capitalize">{{ $report->platform ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">App version</dt><dd>{{ $report->app_version ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Device</dt><dd>{{ $report->device_model ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">OS</dt><dd>{{ $report->os_version ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Screen</dt><dd class="break-all">{{ $report->screen_name ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Admin</h3>
                <form method="post" action="{{ route('admin.issue-reports.update', $report) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            @foreach(['pending', 'reviewed', 'resolved'] as $s)
                                <option value="{{ $s }}" @selected($report->status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Internal notes (not sent to user)</label>
                        <textarea name="admin_notes" rows="5"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                  placeholder="Investigation notes, Jira ticket, root cause…">{{ old('admin_notes', $report->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-gray-900 dark:bg-indigo-600 text-white rounded-lg text-sm font-medium hover:opacity-90">
                        Save status & notes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
