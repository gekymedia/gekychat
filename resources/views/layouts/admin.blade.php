<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GekyChat</title>
    
    <!-- Tailwind CSS -->
      <!-- Use reliable Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        .sidebar.collapsed {
            width: 64px;
        }
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        .main-content {
            transition: all 0.3s ease;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .dark-mode {
            background-color: #1a202c;
            color: #e2e8f0;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white dark:bg-gray-800 shadow-lg w-64">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-robot text-white text-lg"></i>
                    </div>
                    <div class="sidebar-text">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">GekyChat Admin</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Analytics Dashboard</p>
                    </div>
                </div>
            </div>

            <nav class="mt-6">
                <div class="px-4 space-y-2">
                    <a href="{{ route('admin.dashboard') }}" 
                       class="flex items-center space-x-3 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-900 text-blue-600 dark:text-blue-400">
                        <i class="fas fa-chart-line w-5"></i>
                        <span class="sidebar-text font-medium">Dashboard</span>
                    </a>
                    
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-users w-5"></i>
                        <span class="sidebar-text">User Management</span>
                    </a>
                    
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-flag w-5"></i>
                        <span class="sidebar-text">Reports</span>
                    </a>
                    
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-comments w-5"></i>
                        <span class="sidebar-text">Messages</span>
                    </a>
                    
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-layer-group w-5"></i>
                        <span class="sidebar-text">Groups</span>
                    </a>
                    
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span class="sidebar-text">Analytics</span>
                    </a>
                    
                    <a href="{{ route('chat.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-arrow-left w-5"></i>
                        <span class="sidebar-text">Back to Chat</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">@yield('title', 'Dashboard')</h2>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Theme Toggle -->
                        <button id="themeToggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <i class="fas fa-moon"></i>
                        </button>
                        
                        <!-- User Menu -->
                        <div class="relative">
                            <button class="flex items-center space-x-3 focus:outline-none">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ auth()->user()->name }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Theme Toggle
        document.getElementById('themeToggle').addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            const icon = this.querySelector('i');
            if (document.documentElement.classList.contains('dark')) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });

      // Auto-refresh data every 2 minutes
setInterval(() => {
    fetch('/admin/api/refresh-data')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                console.log('Data refreshed at:', data.data.timestamp);
                
                // Update the UI with new data
                updateDashboardData(data.data);
            } else {
                console.warn('Refresh failed:', data.message);
            }
        })
        .catch(error => {
            console.error('Refresh failed:', error);
            // Silently fail - don't show errors to user for auto-refresh
        });
}, 120000); // 2 minutes

// Function to update dashboard with new data
function updateDashboardData(data) {
    // Update online users count
    const onlineUsersElement = document.querySelector('[data-metric="online_users"]');
    if (onlineUsersElement) {
        onlineUsersElement.textContent = data.online_users;
    }
    
    // Update messages today
    const messagesElement = document.querySelector('[data-metric="messages_today"]');
    if (messagesElement) {
        messagesElement.textContent = data.messages_today.total;
    }
    
    // Update new users today
    const newUsersElement = document.querySelector('[data-metric="new_users_today"]');
    if (newUsersElement) {
        newUsersElement.textContent = data.new_users_today;
    }
    
    // Update pending reports
    const reportsElement = document.querySelector('[data-metric="pending_reports"]');
    if (reportsElement) {
        reportsElement.textContent = data.pending_reports;
    }
    
    // Update AI interactions
    const aiInteractionsElement = document.querySelector('[data-metric="ai_interactions"]');
    if (aiInteractionsElement) {
        aiInteractionsElement.textContent = data.ai_interactions_today;
    }
    
    // Update server time
    const timeElement = document.querySelector('[data-metric="server_time"]');
    if (timeElement) {
        timeElement.textContent = data.server_time;
    }
}
// Enhanced auto-refresh with retry logic
let refreshAttempts = 0;
const maxRefreshAttempts = 3;

function performAutoRefresh() {
    fetch('/admin/api/refresh-data')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                refreshAttempts = 0; // Reset counter on success
                updateDashboardData(data.data);
                updateRefreshStatus('success', `Last updated: ${new Date().toLocaleTimeString()}`);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            refreshAttempts++;
            console.warn(`Refresh failed (attempt ${refreshAttempts}):`, error);
            
            if (refreshAttempts >= maxRefreshAttempts) {
                updateRefreshStatus('error', 'Auto-refresh disabled due to errors');
                // Stop trying after max attempts
                clearInterval(refreshInterval);
            } else {
                updateRefreshStatus('warning', 'Refresh failed, retrying...');
            }
        });
}

function updateRefreshStatus(type, message) {
    const statusElement = document.getElementById('refreshStatus');
    if (statusElement) {
        statusElement.className = `fixed bottom-4 right-4 px-3 py-1 rounded-full text-sm ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'warning' ? 'bg-yellow-500 text-black' :
            'bg-red-500 text-white'
        }`;
        statusElement.innerHTML = `<i class="fas fa-circle mr-1"></i> ${message}`;
    }
}

// Start auto-refresh
const refreshInterval = setInterval(performAutoRefresh, 120000);

// Also perform initial refresh after page load
setTimeout(performAutoRefresh, 5000);
    </script>

    @yield('scripts')
</body>
</html>