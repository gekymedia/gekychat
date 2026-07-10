@extends('layouts.admin')

@section('title', 'App Versions')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">App Versions</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">App Versions</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Control latest and minimum client versions per platform. Mobile and desktop apps check
                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">GET /api/v1/app/version</code>
                on launch and in Settings → About.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3"></i>
                <p class="text-green-800 dark:text-green-300 font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <ul class="list-disc list-inside text-red-800 dark:text-red-300 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.app-versions.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Android Play In-App Updates --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-600 flex items-center justify-center text-white">
                        <i class="fab fa-google-play"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Android Play In-App Updates</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Use Google Play’s native update UI when the app is installed from Play Store.</p>
                    </div>
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="android_in_app_update_enabled" value="0">
                        <input type="checkbox"
                               name="android_in_app_update_enabled"
                               value="1"
                               class="sr-only peer"
                               {{ old('android_in_app_update_enabled', $androidInAppUpdate['enabled'] ? '1' : '0') == '1' ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 dark:peer-focus:ring-green-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white">Enable Play In-App Updates</span>
                    </label>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        When enabled, Android tries Play’s update flow before opening the store URL. Only works for Play Store builds (not sideloaded APKs).
                    </p>
                </div>
                <div>
                    <label for="android_in_app_update_priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Update style</label>
                    <select id="android_in_app_update_priority"
                            name="android_in_app_update_priority"
                            class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500">
                        <option value="flexible" {{ old('android_in_app_update_priority', $androidInAppUpdate['priority']) === 'flexible' ? 'selected' : '' }}>
                            Flexible — background download, user keeps using the app
                        </option>
                        <option value="immediate" {{ old('android_in_app_update_priority', $androidInAppUpdate['priority']) === 'immediate' ? 'selected' : '' }}>
                            Immediate — full-screen Play update (used for force updates too)
                        </option>
                    </select>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Force updates (below min version) always use immediate Play update when available.
                    </p>
                </div>
            </div>
        </div>

        {{-- Per-platform version cards --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach($platformLabels as $platform => $label)
                @php
                    $config = $platforms[$platform] ?? [];
                    $icon = match($platform) {
                        'android' => 'fab fa-android text-green-500',
                        'ios' => 'fab fa-apple text-gray-700 dark:text-gray-200',
                        'windows' => 'fab fa-windows text-blue-500',
                        'macos' => 'fab fa-apple text-gray-600',
                        'linux' => 'fab fa-linux text-yellow-600',
                        default => 'fas fa-mobile-alt',
                    };
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                        <i class="{{ $icon }} text-xl"></i>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $label }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">platform={{ $platform }}</p>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="{{ $platform }}_latest_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Latest version
                            </label>
                            <input type="text"
                                   id="{{ $platform }}_latest_version"
                                   name="{{ $platform }}_latest_version"
                                   value="{{ old("{$platform}_latest_version", $config['latest_version'] ?? '1.0.0') }}"
                                   placeholder="1.0.0+89"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 font-mono text-sm">
                            <p class="mt-1 text-xs text-gray-500">Format: <code>major.minor.patch+build</code> — triggers optional “Update available”.</p>
                        </div>
                        <div>
                            <label for="{{ $platform }}_min_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Minimum version
                            </label>
                            <input type="text"
                                   id="{{ $platform }}_min_version"
                                   name="{{ $platform }}_min_version"
                                   value="{{ old("{$platform}_min_version", $config['min_version'] ?? '1.0.0') }}"
                                   placeholder="1.0.0+1"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 font-mono text-sm">
                            <p class="mt-1 text-xs text-gray-500">Clients below this see a blocking force-update dialog.</p>
                        </div>
                        <div>
                            <label for="{{ $platform }}_download_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Download / store URL
                            </label>
                            <input type="url"
                                   id="{{ $platform }}_download_url"
                                   name="{{ $platform }}_download_url"
                                   value="{{ old("{$platform}_download_url", $config['download_url'] ?? '') }}"
                                   placeholder="{{ $platform === 'android' ? 'https://play.google.com/store/apps/details?id=com.gekychat.app' : 'https://...' }}"
                                   class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Opened when user taps Update (fallback if Play in-app update unavailable).</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors shadow">
                <i class="fas fa-save mr-2"></i>
                Save app versions
            </button>
        </div>
    </form>

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-2">
            <i class="fas fa-lightbulb mr-1"></i> Deployment checklist
        </h4>
        <ul class="text-sm text-blue-800 dark:text-blue-400 space-y-1 list-disc list-inside">
            <li>After publishing a new build, bump <strong>Latest version</strong> to match <code>pubspec.yaml</code> (e.g. <code>1.0.0+90</code>).</li>
            <li>Raise <strong>Minimum version</strong> only when you want to block older clients entirely.</li>
            <li>Play In-App Updates require the app on Play Store with a higher version code than installed.</li>
            <li>Settings here override <code>.env</code> values from <code>config/app_versions.php</code>.</li>
        </ul>
    </div>
</div>
@endsection
