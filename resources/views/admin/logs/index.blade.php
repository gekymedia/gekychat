@extends('layouts.admin')

@section('title', 'System Logs')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 dark:text-gray-400">Dashboard</a>
        <i class="fas fa-chevron-right mx-2 text-gray-400"></i>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">System Logs</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Stats -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Logs</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Monitor application activity and debug issues</p>
        </div>
        <div class="flex gap-3 mt-4 md:mt-0">
            <!-- Log File Size -->
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <div class="text-gray-600 dark:text-gray-400 text-sm">Log Size</div>
                <div class="font-bold text-gray-900 dark:text-white">{{ $logStats['size'] ?? '0 MB' }}</div>
            </div>

            <!-- Last Modified -->
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <div class="text-gray-600 dark:text-gray-400 text-sm">Last Updated</div>
                <div class="font-bold text-gray-900 dark:text-white">{{ $logStats['last_modified'] ?? 'Never' }}</div>
            </div>

            <!-- Log File Count -->
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <div class="text-gray-600 dark:text-gray-400 text-sm">Log Files</div>
                <div class="font-bold text-gray-900 dark:text-white">{{ count($logFiles) }}</div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Search -->
                <div class="relative flex-grow">
                    <input type="text" id="logSearch" placeholder="Search logs..."
                        class="w-full px-4 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <i class="fas fa-search text-gray-400"></i>
                    </span>
                </div>

                <!-- Level Filter -->
                <select id="levelFilter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent w-auto">
                    <option value="all">All Levels</option>
                    <option value="emergency">Emergency</option>
                    <option value="alert">Alert</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="notice">Notice</option>
                    <option value="info">Info</option>
                    <option value="debug">Debug</option>
                </select>

                <!-- Log File Selector -->
                <select id="logFileSelect" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent w-auto">
                    <option value="latest">Latest Log File</option>
                    @foreach ($logFiles as $logFile)
                        @php
                            $filePath = storage_path('logs/' . $logFile);
                            $fileSize = File::exists($filePath)
                                ? $formatBytes(File::size($filePath))
                                : '0 MB';
                            $isSelected =
                                $selectedLogFile === $logFile ||
                                ($selectedLogFile === 'latest' && $loop->first);
                        @endphp
                        <option value="{{ $logFile }}" {{ $isSelected ? 'selected' : '' }}>
                            {{ $logFile }} ({{ $fileSize }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-2 flex-wrap">
                <!-- Refresh -->
                <button id="refreshLogs" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>

                <!-- Clear Current Log -->
                <button id="clearLogs" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-trash mr-1"></i> Clear Current
                </button>

                <!-- Clear All Logs -->
                <button id="clearAllLogs" class="px-4 py-2 bg-red-100 dark:bg-red-900/20 hover:bg-red-200 dark:hover:bg-red-900/40 text-red-700 dark:text-red-400 text-sm font-medium rounded-lg transition-colors border border-red-300 dark:border-red-700">
                    <i class="fas fa-broom mr-1"></i> Clear All
                </button>

                <!-- Download Current -->
                <button id="downloadLogs" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-download mr-1"></i> Download
                </button>

                <!-- Download All -->
                <button id="downloadAllLogs" class="px-4 py-2 bg-green-100 dark:bg-green-900/20 hover:bg-green-200 dark:hover:bg-green-900/40 text-green-700 dark:text-green-400 text-sm font-medium rounded-lg transition-colors border border-green-300 dark:border-green-700">
                    <i class="fas fa-file-archive mr-1"></i> Download All
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <div class="text-2xl font-bold mb-1 text-red-600 dark:text-red-400">{{ $logStats['counts']['error'] ?? 0 }}</div>
                <div class="text-gray-600 dark:text-gray-400 text-sm">Errors</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold mb-1 text-yellow-600 dark:text-yellow-400">{{ $logStats['counts']['warning'] ?? 0 }}</div>
                <div class="text-gray-600 dark:text-gray-400 text-sm">Warnings</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold mb-1 text-blue-600 dark:text-blue-400">{{ $logStats['counts']['info'] ?? 0 }}</div>
                <div class="text-gray-600 dark:text-gray-400 text-sm">Info</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold mb-1 text-gray-600 dark:text-gray-400">{{ $logStats['counts']['debug'] ?? 0 }}</div>
                <div class="text-gray-600 dark:text-gray-400 text-sm">Debug</div>
            </div>
        </div>
    </div>

    <!-- Logs Container -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg">
        <!-- Logs Header -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 rounded-t-xl">
            <div class="flex justify-between items-center">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Recent Log Entries
                    @if (isset($selectedLogFile) && $selectedLogFile !== 'latest')
                        <span class="text-gray-500 dark:text-gray-400 text-sm font-normal">- {{ $selectedLogFile }}</span>
                    @endif
                </h5>
                <div class="flex items-center gap-3">
                    <span class="text-gray-600 dark:text-gray-400 text-sm" id="logCount">
                        {{ count($logs) }} entries
                    </span>
                    <div class="flex gap-2">
                        <button id="autoRefreshToggle" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors">
                            <i class="fas fa-play mr-1"></i> <span>Auto-refresh</span>
                        </button>
                        <button id="toggleAll" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors">
                            <i class="fas fa-expand mr-1"></i> <span>Expand All</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Content -->
        <div class="p-0">
            <div class="max-h-[500px] overflow-auto" id="logsContainer">
                @if (empty($logs))
                    <div class="text-center p-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-inbox text-5xl mb-4 block"></i>
                        <p>No log entries found in selected file</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($logs as $index => $log)
                            <div class="log-entry p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" data-level="{{ $log['level'] }}" data-date="{{ $log['date'] }}">
                                <!-- Log Header -->
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Level Badge -->
                                        <span class="log-level-badge level-{{ $log['level'] }} px-2 py-1 text-xs font-semibold rounded text-white">
                                            {{ $log['level'] }}
                                        </span>

                                        <!-- Timestamp -->
                                        <span class="text-gray-600 dark:text-gray-400 text-sm font-mono">
                                            {{ $log['timestamp'] }}
                                        </span>

                                        <!-- Environment -->
                                        @if (isset($log['env']))
                                            <span class="px-2 py-1 bg-purple-600 text-white text-xs font-semibold rounded">
                                                {{ $log['env'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex gap-1">
                                        <!-- Copy Button -->
                                        <button class="copy-log-btn px-2 py-1 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded transition-colors" data-log="{{ json_encode($log) }}" title="Copy log entry">
                                            <i class="fas fa-copy"></i>
                                        </button>

                                        <!-- Expand Button -->
                                        <button class="expand-log-btn px-2 py-1 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded transition-colors" data-target="log-details-{{ $index }}" title="Expand details">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Main Message -->
                                <div class="mb-2">
                                    <p class="text-gray-900 dark:text-white log-message break-words">
                                        {{ $log['message'] }}
                                    </p>
                                </div>

                                <!-- Context Information (Collapsed by Default) -->
                                <div id="log-details-{{ $index }}" class="log-details hidden mt-3">
                                    @if (isset($log['context']) && !empty($log['context']))
                                        <div class="mb-3">
                                            <h6 class="text-gray-600 dark:text-gray-400 font-semibold mb-2">Context</h6>
                                            <pre class="bg-gray-100 dark:bg-gray-900 p-3 rounded text-sm overflow-auto"><code class="text-gray-900 dark:text-gray-100">{{ json_encode($log['context'], JSON_PRETTY_PRINT) }}</code></pre>
                                        </div>
                                    @endif

                                    @if (isset($log['stack_trace']) && !empty($log['stack_trace']))
                                        <div class="mb-3">
                                            <h6 class="text-gray-600 dark:text-gray-400 font-semibold mb-2">Stack Trace</h6>
                                            <div class="bg-gray-100 dark:bg-gray-900 p-3 rounded text-sm overflow-auto max-h-[200px]">
                                                <code class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $log['stack_trace'] }}</code>
                                            </div>
                                        </div>
                                    @endif

                                    @if (isset($log['extra']) && !empty($log['extra']))
                                        <div class="mb-3">
                                            <h6 class="text-gray-600 dark:text-gray-400 font-semibold mb-2">Additional Info</h6>
                                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded">
                                                <pre class="text-sm mb-0"><code class="text-gray-900 dark:text-gray-100">{{ json_encode($log['extra'], JSON_PRETTY_PRINT) }}</code></pre>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Quick Actions -->
                                <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex gap-3 text-sm text-gray-600 dark:text-gray-400">
                                        @if (isset($log['file']))
                                            <span class="font-mono">{{ $log['file'] }}:{{ $log['line'] ?? 'N/A' }}</span>
                                        @endif

                                        @if (isset($log['user_id']))
                                            <span>User ID: {{ $log['user_id'] }}</span>
                                        @endif

                                        @if (isset($log['ip']))
                                            <span>IP: {{ $log['ip'] }}</span>
                                        @endif
                                    </div>

                                    <div>
                                        <button class="mark-resolved-btn px-3 py-1 bg-green-100 dark:bg-green-900/20 hover:bg-green-200 dark:hover:bg-green-900/40 text-green-700 dark:text-green-400 text-sm font-medium rounded-lg transition-colors border border-green-300 dark:border-green-700">
                                            Mark Resolved
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Pagination -->
        @if (isset($pagination) && $pagination['total'] > 0)
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
                <div class="flex justify-between items-center">
                    <div class="text-gray-600 dark:text-gray-400 text-sm">
                        Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} entries
                    </div>
                    <nav class="flex gap-1">
                        @if ($pagination['current_page'] > 1)
                            <a href="?page={{ $pagination['current_page'] - 1 }}&log_file={{ $selectedLogFile }}" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-sm transition-colors">
                                Previous
                            </a>
                        @endif

                        @for ($i = 1; $i <= $pagination['last_page']; $i++)
                            @if ($i == $pagination['current_page'])
                                <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm">{{ $i }}</span>
                            @else
                                <a href="?page={{ $i }}&log_file={{ $selectedLogFile }}" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-sm transition-colors">{{ $i }}</a>
                            @endif
                        @endfor

                        @if ($pagination['current_page'] < $pagination['last_page'])
                            <a href="?page={{ $pagination['current_page'] + 1 }}&log_file={{ $selectedLogFile }}" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-sm transition-colors">
                                Next
                            </a>
                        @endif
                    </nav>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Confirmation Modal for Clear Current -->
<div id="clearLogsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">Clear Log File</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400" id="clearCurrentFileText">Clear current log file?</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">This will permanently delete all log entries from the selected log file.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirmClear" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">Clear Log File</button>
                <button type="button" id="cancelClear" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal for Clear All -->
<div id="clearAllLogsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">Clear All Log Files</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Clear all {{ count($logFiles) }} log files?</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">This will permanently delete all log entries from all log files ({{ count($logFiles) }} files).</p>
                            <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                                <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Warning:</strong> This will clear all historical log data.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirmClearAll" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">Clear All Logs</button>
                <button type="button" id="cancelClearAll" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Real-time Updates Indicator -->
<div id="realtimeIndicator" class="hidden fixed bottom-4 right-4 m-3 px-4 py-2 bg-green-500 text-white rounded-lg shadow-lg flex items-center">
    <i class="fas fa-circle mr-2 text-xs"></i>
    <span>Live updates enabled</span>
</div>

<style>
    /* Level-specific styling */
    .level-emergency { background-color: #dc2626 !important; }
    .level-alert { background-color: #ea580c !important; }
    .level-critical { background-color: #dc2626 !important; }
    .level-error { background-color: #ef4444 !important; }
    .level-warning { background-color: #f59e0b !important; }
    .level-notice { background-color: #3b82f6 !important; }
    .level-info { background-color: #10b981 !important; }
    .level-debug { background-color: #6b7280 !important; }

    .log-entry {
        transition: all 0.3s ease;
    }

    /* Animation for new log entries */
    @keyframes highlightNew {
        0% { background-color: rgba(34, 197, 94, 0.2); }
        100% { background-color: transparent; }
    }

    .log-entry.new {
        animation: highlightNew 2s ease-in-out;
    }

    /* Custom scrollbar */
    #logsContainer::-webkit-scrollbar {
        width: 6px;
    }

    #logsContainer::-webkit-scrollbar-track {
        background: transparent;
    }

    #logsContainer::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    #logsContainer::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .dark #logsContainer::-webkit-scrollbar-thumb {
        background: #4b5563;
    }

    .dark #logsContainer::-webkit-scrollbar-thumb:hover {
        background: #6b7280;
    }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let autoRefreshInterval = null;
    let isAutoRefreshEnabled = false;

    // Filter functionality
    const searchInput = document.getElementById('logSearch');
    const levelFilter = document.getElementById('levelFilter');
    const logFileSelect = document.getElementById('logFileSelect');

    function filterLogs() {
        const searchTerm = searchInput.value.toLowerCase();
        const level = levelFilter.value;

        document.querySelectorAll('.log-entry').forEach(entry => {
            const entryLevel = entry.getAttribute('data-level');
            const message = entry.querySelector('.log-message').textContent.toLowerCase();

            const matchesSearch = searchTerm === '' || message.includes(searchTerm);
            const matchesLevel = level === 'all' || entryLevel === level;

            if (matchesSearch && matchesLevel) {
                entry.style.display = 'block';
            } else {
                entry.style.display = 'none';
            }
        });

        updateLogCount();
    }

    function updateLogCount() {
        const visibleCount = document.querySelectorAll('.log-entry[style="display: block"], .log-entry:not([style*="display: none"])').length;
        const totalCount = document.querySelectorAll('.log-entry').length;
        document.getElementById('logCount').textContent = `${visibleCount} of ${totalCount} entries`;
    }

    // Event listeners for filters
    if (searchInput) searchInput.addEventListener('input', filterLogs);
    if (levelFilter) levelFilter.addEventListener('change', filterLogs);

    // Log file selector change
    if (logFileSelect) {
        logFileSelect.addEventListener('change', function() {
            const selectedFile = this.value;
            const url = new URL(window.location);
            url.searchParams.set('log_file', selectedFile);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    // Expand/collapse functionality
    document.querySelectorAll('.expand-log-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const target = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (target) {
                target.classList.toggle('hidden');
                if (target.classList.contains('hidden')) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        });
    });

    // Toggle all functionality
    const toggleAllBtn = document.getElementById('toggleAll');
    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const span = this.querySelector('span');
            const isExpanding = icon.classList.contains('fa-expand');

            document.querySelectorAll('.log-details').forEach(detail => {
                if (isExpanding) {
                    detail.classList.remove('hidden');
                } else {
                    detail.classList.add('hidden');
                }
            });

            document.querySelectorAll('.expand-log-btn i').forEach(icon => {
                if (isExpanding) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });

            icon.classList.toggle('fa-expand');
            icon.classList.toggle('fa-compress');
            if (span) span.textContent = isExpanding ? 'Collapse All' : 'Expand All';
        });
    }

    // Copy log functionality
    document.querySelectorAll('.copy-log-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const logData = JSON.parse(this.getAttribute('data-log'));
            const logText = formatLogForCopy(logData);

            navigator.clipboard.writeText(logText).then(() => {
                showNotification('Log entry copied to clipboard', 'success');
            }).catch(() => {
                showNotification('Failed to copy log entry', 'error');
            });
        });
    });

    function formatLogForCopy(log) {
        return `[${log.timestamp}] ${log.level.toUpperCase()}: ${log.message}\n\n` +
            (log.context ? `Context: ${JSON.stringify(log.context, null, 2)}\n\n` : '') +
            (log.stack_trace ? `Stack Trace:\n${log.stack_trace}\n\n` : '') +
            (log.extra ? `Extra: ${JSON.stringify(log.extra, null, 2)}` : '');
    }

    // Auto-refresh functionality
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    if (autoRefreshToggle) {
        autoRefreshToggle.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const span = this.querySelector('span');
            const indicator = document.getElementById('realtimeIndicator');

            if (isAutoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
                if (span) span.textContent = 'Auto-refresh';
                if (indicator) indicator.classList.add('hidden');
                isAutoRefreshEnabled = false;
            } else {
                autoRefreshInterval = setInterval(refreshLogs, 10000);
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
                if (span) span.textContent = 'Stop refresh';
                if (indicator) indicator.classList.remove('hidden');
                isAutoRefreshEnabled = true;
            }
        });
    }

    function refreshLogs() {
        fetch('{{ route('admin.logs.refresh') }}', {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Logs refreshed', 'info');
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(error => {
            console.error('Error refreshing logs:', error);
            showNotification('Failed to refresh logs', 'error');
        });
    }

    // Modal functions
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
    }

    function hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }

    // Clear current logs functionality
    const clearLogsBtn = document.getElementById('clearLogs');
    if (clearLogsBtn) {
        clearLogsBtn.addEventListener('click', function() {
            const currentFile = logFileSelect ? logFileSelect.value : 'latest';
            const displayFile = currentFile === 'latest' ? 'latest log file' : currentFile;
            const clearCurrentFileText = document.getElementById('clearCurrentFileText');
            if (clearCurrentFileText) clearCurrentFileText.textContent = `Clear ${displayFile}?`;
            showModal('clearLogsModal');
        });
    }

    const cancelClearBtn = document.getElementById('cancelClear');
    if (cancelClearBtn) {
        cancelClearBtn.addEventListener('click', function() {
            hideModal('clearLogsModal');
        });
    }

    const confirmClearBtn = document.getElementById('confirmClear');
    if (confirmClearBtn) {
        confirmClearBtn.addEventListener('click', function() {
            const currentFile = logFileSelect ? logFileSelect.value : 'latest';

            fetch('{{ route('admin.logs.clear') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    log_file: currentFile
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Log file cleared successfully', 'success');
                    hideModal('clearLogsModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Failed to clear log file', 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing logs:', error);
                showNotification('Failed to clear log file', 'error');
            });
        });
    }

    // Clear all logs functionality
    const clearAllLogsBtn = document.getElementById('clearAllLogs');
    if (clearAllLogsBtn) {
        clearAllLogsBtn.addEventListener('click', function() {
            showModal('clearAllLogsModal');
        });
    }

    const cancelClearAllBtn = document.getElementById('cancelClearAll');
    if (cancelClearAllBtn) {
        cancelClearAllBtn.addEventListener('click', function() {
            hideModal('clearAllLogsModal');
        });
    }

    const confirmClearAllBtn = document.getElementById('confirmClearAll');
    if (confirmClearAllBtn) {
        confirmClearAllBtn.addEventListener('click', function() {
            fetch('{{ route('admin.logs.clear-all') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    hideModal('clearAllLogsModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Failed to clear all logs', 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing all logs:', error);
                showNotification('Failed to clear all logs', 'error');
            });
        });
    }

    // Download current logs functionality
    const downloadLogsBtn = document.getElementById('downloadLogs');
    if (downloadLogsBtn) {
        downloadLogsBtn.addEventListener('click', function() {
            const currentFile = logFileSelect ? logFileSelect.value : 'latest';
            window.location.href = '{{ route('admin.logs.download') }}?log_file=' + currentFile;
        });
    }

    // Download all logs functionality
    const downloadAllLogsBtn = document.getElementById('downloadAllLogs');
    if (downloadAllLogsBtn) {
        downloadAllLogsBtn.addEventListener('click', function() {
            window.location.href = '{{ route('admin.logs.download-all') }}';
        });
    }

    // Manual refresh
    const refreshLogsBtn = document.getElementById('refreshLogs');
    if (refreshLogsBtn) {
        refreshLogsBtn.addEventListener('click', function() {
            location.reload();
        });
    }

    // Mark as resolved functionality
    document.querySelectorAll('.mark-resolved-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const logEntry = this.closest('.log-entry');
            if (logEntry) {
                logEntry.style.opacity = '0.6';
                showNotification('Log marked as resolved', 'success');
            }
        });
    });

    // Notification system
    function showNotification(message, type = 'info') {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500'
        };
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            info: 'fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center`;
        toast.innerHTML = `
            <i class="fas ${icons[type]} mr-2"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const clearModal = document.getElementById('clearLogsModal');
        const clearAllModal = document.getElementById('clearAllLogsModal');
        
        if (event.target === clearModal) hideModal('clearLogsModal');
        if (event.target === clearAllModal) hideModal('clearAllLogsModal');
    });

    // Initialize filters
    filterLogs();
});
</script>
@endpush
@endsection
