<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GekyChat</title>
    
    <!-- Tailwind CSS -->
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
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .hover-lift {
            transition: all 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white dark:bg-gray-800 shadow-lg w-64 flex flex-col">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-robot text-white text-lg"></i>
                    </div>
                    <div class="sidebar-text">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">GekyChat Admin</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Analytics Dashboard</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('admin.dashboard') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-blue-600 dark:hover:text-blue-400 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}">
                            <i class="fas fa-tachometer-alt w-5"></i>
                            <span class="sidebar-text font-medium">Dashboard</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.users.index') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-green-50 dark:hover:bg-green-900/20 hover:text-green-600 dark:hover:text-green-400 transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' : '' }}">
                            <i class="fas fa-users w-5"></i>
                            <span class="sidebar-text font-medium">User Management</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.reports.index') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition-colors {{ request()->routeIs('admin.reports.*') ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : '' }}">
                            <i class="fas fa-flag w-5"></i>
                            <span class="sidebar-text font-medium">User Reports</span>
                            @if($pendingReportsCount > 0)
                                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $pendingReportsCount }}</span>
                            @endif
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.blocks.index') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/20 hover:text-orange-600 dark:hover:text-orange-400 transition-colors {{ request()->routeIs('admin.blocks.*') ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400' : '' }}">
                            <i class="fas fa-ban w-5"></i>
                            <span class="sidebar-text font-medium">Blocked Users</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.api-clients.index') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors {{ request()->routeIs('admin.api-clients.*') ? 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400' : '' }}">
                            <i class="fas fa-code w-5"></i>
                            <span class="sidebar-text font-medium">API Clients</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.settings') }}" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors {{ request()->routeIs('admin.settings') ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' : '' }}">
                            <i class="fas fa-cog w-5"></i>
                            <span class="sidebar-text font-medium">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="sidebar-text">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Administrator</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <button id="sidebarToggle" class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">@yield('title', 'Dashboard')</h2>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Refresh Status -->
                        <div id="refreshStatus" class="hidden fixed bottom-4 right-4 px-3 py-1 rounded-full text-sm bg-green-500 text-white">
                            <i class="fas fa-circle mr-1"></i> Last updated: <span id="lastUpdateTime">{{ now()->format('H:i:s') }}</span>
                        </div>
                        
                        <!-- Theme Toggle -->
                        <button id="themeToggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <i class="fas fa-moon"></i>
                        </button>
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-bell"></i>
                                @if($pendingReportsCount > 0)
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                                        {{ $pendingReportsCount }}
                                    </span>
                                @endif
                            </button>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                                <span class="text-gray-700 dark:text-gray-300 font-medium hidden md:block">{{ auth()->user()->name }}</span>
                                <i class="fas fa-chevron-down text-gray-400 text-sm hidden md:block"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 hidden z-50">
                                <a href="{{ route('profile.edit') }}" class="flex items-center space-x-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user w-5"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="{{ route('settings.index') }}" class="flex items-center space-x-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog w-5"></i>
                                    <span>Settings</span>
                                </a>
                                <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex items-center space-x-3 px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left">
                                        <i class="fas fa-sign-out-alt w-5"></i>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Breadcrumb -->
                <div class="mb-6">
                    <nav class="flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm font-medium text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                                    <i class="fas fa-home mr-2"></i>
                                    Admin
                                </a>
                            </li>
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                </div>

                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3"></i>
                            <span class="text-green-800 dark:text-green-300">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3"></i>
                            <span class="text-red-800 dark:text-red-300">{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('ml-0');
            mainContent.classList.toggle('md:ml-64');
        });

        // Theme Toggle
        document.getElementById('themeToggle').addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            const icon = this.querySelector('i');
            if (document.documentElement.classList.contains('dark')) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // User Dropdown
        document.getElementById('userMenuButton').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenuButton');
            const dropdown = document.getElementById('userDropdown');
            if (!userMenu.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Load saved theme
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            document.getElementById('themeToggle').querySelector('i').classList.remove('fa-moon');
            document.getElementById('themeToggle').querySelector('i').classList.add('fa-sun');
        }

        // Enhanced auto-refresh with retry logic
        let refreshAttempts = 0;
        const maxRefreshAttempts = 3;
        let refreshInterval;

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
                    // Retry after 30 seconds
                        setTimeout(performAutoRefresh, 30000);
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
                statusElement.classList.remove('hidden');
                
                // Hide success message after 5 seconds
                if (type === 'success') {
                    setTimeout(() => {
                        statusElement.classList.add('hidden');
                    }, 5000);
                }
            }
        }

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

        // Start auto-refresh
        refreshInterval = setInterval(performAutoRefresh, 120000);

        // Also perform initial refresh after page load
        setTimeout(performAutoRefresh, 5000);

        // Manual refresh button (if added to the header)
        document.addEventListener('DOMContentLoaded', function() {
            const manualRefreshBtn = document.getElementById('manualRefresh');
            if (manualRefreshBtn) {
                manualRefreshBtn.addEventListener('click', performAutoRefresh);
            }
        });
    </script>

    @yield('scripts')
</body>
</html>