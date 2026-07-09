@extends('layouts.admin')

@section('title', 'Issue Reports')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Issue Reports</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Shake-to-report and in-app feedback from users</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.issue-reports.index', ['status' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                    {{ $label }} @if($key !== 'all') ({{ $counts[$key] ?? 0 }}) @endif
                </a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-left text-gray-500">
                    <tr>
                        <th class="p-3">When</th>
                        <th class="p-3">User</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Source</th>
                        <th class="p-3">Description</th>
                        <th class="p-3">Device</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr class="border-t dark:border-gray-700 align-top">
                        <td class="p-3 whitespace-nowrap text-gray-500">{{ $report->created_at->format('M j, H:i') }}</td>
                        <td class="p-3">
                            <div class="font-medium text-gray-900 dark:text-white">#{{ $report->user_id }}</div>
                            <div class="text-gray-500">{{ $report->user?->name ?? $report->user?->phone }}</div>
                        </td>
                        <td class="p-3 capitalize">{{ $report->category }}</td>
                        <td class="p-3 capitalize">{{ $report->source }}</td>
                        <td class="p-3 max-w-md">
                            <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($report->description, 200) }}</p>
                            @if($report->screen_name)
                                <p class="text-xs text-gray-500 mt-1">Screen: {{ $report->screen_name }}</p>
                            @endif
                            @if($report->screenshot_path)
                                <a href="{{ \App\Helpers\UrlHelper::secureStorageUrl($report->screenshot_path) }}" target="_blank" class="text-indigo-600 text-xs mt-1 inline-block">View screenshot</a>
                            @endif
                        </td>
                        <td class="p-3 text-gray-500 text-xs">
                            {{ $report->platform }} {{ $report->app_version }}<br>
                            {{ $report->device_model }}<br>
                            {{ $report->os_version }}
                        </td>
                        <td class="p-3">
                            <form method="post" action="{{ route('admin.issue-reports.update-status', $report) }}">
                                @csrf
                                @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    @foreach(['pending', 'reviewed', 'resolved'] as $s)
                                        <option value="{{ $s }}" @selected($report->status === $s)>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="p-8 text-center text-gray-500">No reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="p-4 border-t dark:border-gray-700">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
@endsection
