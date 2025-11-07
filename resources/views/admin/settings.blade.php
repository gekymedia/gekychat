@extends('layouts.admin')

@section('title', 'Admin Settings')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Settings</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Admin Settings</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Configure system settings and preferences</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 lg:mt-0">
            <button class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>
                Save Changes
            </button>
            <button class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Reset to Defaults
            </button>
        </div>
    </div>

    <!-- Settings Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <!-- Tab Headers -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <button id="general-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600 dark:text-blue-400" onclick="switchTab('general')">
                    <i class="fas fa-cog mr-2"></i>
                    General
                </button>
                <button id="security-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt mr-2"></i>
                    Security
                </button>
                <button id="notifications-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" onclick="switchTab('notifications')">
                    <i class="fas fa-bell mr-2"></i>
                    Notifications
                </button>
                <button id="api-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" onclick="switchTab('api')">
                    <i class="fas fa-code mr-2"></i>
                    API Settings
                </button>
                <button id="maintenance-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" onclick="switchTab('maintenance')">
                    <i class="fas fa-tools mr-2"></i>
                    Maintenance
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- General Settings -->
            <div id="general-tab-content" class="tab-content space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Site Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Site Information</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Site Name
                            </label>
                            <input type="text" 
                                   value="GekyChat" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Site Description
                            </label>
                            <textarea class="w-full h-24 p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors" 
                                      placeholder="Enter site description...">Modern chat application for seamless communication</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Contact Email
                            </label>
                            <input type="email" 
                                   value="admin@gekychat.com" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>
                    </div>

                    <!-- User Registration -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">User Registration</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Allow New Registrations</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Allow new users to create accounts</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Email Verification</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Require email verification for new users</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Phone Verification</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Require phone verification for new users</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="security-tab-content" class="tab-content space-y-6 hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Authentication -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Authentication</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Two-Factor Authentication</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Require 2FA for admin accounts</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Session Timeout</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Auto-logout after inactivity</div>
                            </div>
                            <select class="p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                                <option>30 minutes</option>
                                <option selected>1 hour</option>
                                <option>2 hours</option>
                                <option>4 hours</option>
                                <option>Never</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Maximum Login Attempts
                            </label>
                            <input type="number" 
                                   value="5" 
                                   min="1" 
                                   max="10"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>
                    </div>

                    <!-- Content Moderation -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Content Moderation</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Auto-Moderation</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Automatically flag inappropriate content</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Profanity Filter</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Filter offensive language</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Report Threshold
                            </label>
                            <input type="number" 
                                   value="3" 
                                   min="1" 
                                   max="10"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Number of reports before auto-action</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Settings -->
            <div id="notifications-tab-content" class="tab-content space-y-6 hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Email Notifications -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Email Notifications</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">New User Registrations</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Notify admins of new registrations</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Report Submissions</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Notify when users submit reports</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">System Alerts</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Critical system notifications</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>

                    <!-- In-App Notifications -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">In-App Notifications</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Dashboard Updates</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Real-time dashboard notifications</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Pending Reports</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Notifications for pending reports</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notification Sound
                            </label>
                            <select class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                                <option>Default</option>
                                <option>Chime</option>
                                <option>Bell</option>
                                <option>None</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Settings -->
            <div id="api-tab-content" class="tab-content space-y-6 hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- API Configuration -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">API Configuration</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">API Access</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Enable API endpoints</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Rate Limit (requests per minute)
                            </label>
                            <input type="number" 
                                   value="60" 
                                   min="10" 
                                   max="1000"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                API Version
                            </label>
                            <input type="text" 
                                   value="v1" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>
                    </div>

                    <!-- Webhook Settings -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Webhook Settings</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Webhook Support</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Enable webhook notifications</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Webhook Secret
                            </label>
                            <div class="flex space-x-2">
                                <input type="text" 
                                       value="whsec_••••••••••••••••" 
                                       class="flex-1 p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                                <button class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Webhook URL
                            </label>
                            <input type="url" 
                                   placeholder="https://example.com/webhook" 
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Settings -->
            <div id="maintenance-tab-content" class="tab-content space-y-6 hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- System Maintenance -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Maintenance</h3>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Maintenance Mode</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Put site in maintenance mode</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Maintenance Message
                            </label>
                            <textarea class="w-full h-24 p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-600 dark:focus:border-blue-600 transition-colors" 
                                      placeholder="Enter maintenance message...">We're performing scheduled maintenance. We'll be back shortly.</textarea>
                        </div>

                        <div class="flex space-x-3">
                            <button class="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-play mr-2"></i>
                                Enable Maintenance
                            </button>
                            <button class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-stop mr-2"></i>
                                Disable Maintenance
                            </button>
                        </div>
                    </div>

                    <!-- Data Management -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Data Management</h3>
                        
                        <div class="space-y-3">
                            <button class="w-full flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Clear Cache</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Clear application cache</div>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </button>

                            <button class="w-full flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Optimize Database</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Optimize database tables</div>
                                </div>
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </button>

                            <button class="w-full flex items-center justify-between p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors">
                                <div>
                                    <div class="text-sm font-medium text-orange-800 dark:text-orange-300">Backup Database</div>
                                    <div class="text-sm text-orange-600 dark:text-orange-400">Create database backup</div>
                                </div>
                                <i class="fas fa-download text-orange-500"></i>
                            </button>

                            <button class="w-full flex items-center justify-between p-4 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                                <div>
                                    <div class="text-sm font-medium text-red-800 dark:text-red-300">Reset System</div>
                                    <div class="text-sm text-red-600 dark:text-red-400">Reset to factory settings</div>
                                </div>
                                <i class="fas fa-exclamation-triangle text-red-500"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl p-6">
        <h3 class="text-lg font-medium text-red-800 dark:text-red-300 mb-4">Danger Zone</h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-red-800 dark:text-red-300">Delete All Data</div>
                    <div class="text-sm text-red-600 dark:text-red-400">Permanently delete all user data and messages</div>
                </div>
                <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors"
                        onclick="return confirm('This will permanently delete ALL data. This action cannot be undone.')">
                    <i class="fas fa-trash mr-2"></i>
                    Delete All Data
                </button>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-red-800 dark:text-red-300">Reset System</div>
                    <div class="text-sm text-red-600 dark:text-red-400">Reset entire system to factory defaults</div>
                </div>
                <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors"
                        onclick="return confirm('This will reset the entire system. All settings and data will be lost.')">
                    <i class="fas fa-bomb mr-2"></i>
                    Factory Reset
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        button.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
    });

    // Show selected tab content
    document.getElementById(`${tabName}-tab-content`).classList.remove('hidden');

    // Activate selected tab
    document.getElementById(`${tabName}-tab`).classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
    document.getElementById(`${tabName}-tab`).classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
}

// Initialize first tab as active
document.addEventListener('DOMContentLoaded', function() {
    switchTab('general');
});

// Toggle switch functionality
document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const peer = this.nextElementSibling;
        if (this.checked) {
            peer.classList.add('peer-checked:bg-blue-600');
        } else {
            peer.classList.remove('peer-checked:bg-blue-600');
        }
    });
});

// Save settings functionality
document.querySelector('button:contains("Save Changes")').addEventListener('click', function() {
    // Implement save logic here
    alert('Settings saved successfully!');
});

// Reset to defaults functionality
document.querySelector('button:contains("Reset to Defaults")').addEventListener('click', function() {
    if (confirm('Are you sure you want to reset all settings to defaults?')) {
        // Implement reset logic here
        alert('Settings reset to defaults!');
        location.reload();
    }
});
</script>

<style>
.tab-button {
    transition: all 0.2s ease-in-out;
}

.tab-button:hover {
    color: #374151;
}

.dark .tab-button:hover {
    color: #d1d5db;
}

/* Custom styles for toggle switches */
.peer:checked ~ .peer-checked\:bg-blue-600 {
    background-color: #2563eb;
}

.peer:checked ~ .peer-checked\:after\:translate-x-full:after {
    transform: translateX(100%);
}
</style>
@endsection