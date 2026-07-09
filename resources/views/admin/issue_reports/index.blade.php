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
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr class="border-t dark:border-gray-700 align-top hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 whitespace-nowrap text-gray-500">{{ $report->created_at->format('M j, H:i') }}</td>
                        <td class="p-3">
                            <div class="font-medium text-gray-900 dark:text-white">#{{ $report->user_id }}</div>
                            <div class="text-gray-500">{{ $report->user?->name ?? $report->user?->phone }}</div>
                        </td>
                        <td class="p-3 capitalize">{{ $report->category }}</td>
                        <td class="p-3 capitalize">{{ $report->source }}</td>
                        <td class="p-3 max-w-xs">
                            <p class="text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($report->description, 120) }}</p>
                            @if($report->admin_reply)
                                <span class="text-xs text-green-600 mt-1 inline-block">Replied</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="capitalize px-2 py-0.5 rounded text-xs font-medium
                                {{ $report->status === 'resolved' ? 'bg-green-100 text-green-800' : ($report->status === 'reviewed' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                {{ $report->status }}
                            </span>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            <a href="{{ route('admin.issue-reports.show', $report) }}"
                               class="text-indigo-600 hover:underline font-medium">View</a>
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
