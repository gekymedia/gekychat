<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'GekyChat') }} | @yield('title', 'Chat Instantly')</title>
    <meta name="description" content="GekyChat is a real-time messaging app built for fast and secure communication.">
    <meta name="robots" content="index, follow">
    <meta name="author" content="GEKYMEDIA">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta id="theme-color" name="theme-color" content="#0B141A">

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/css/chat-events.css', 'resources/js/app.js', 'resources/js/chat-events.js'])
    
    {{-- External Stylesheets --}}
    @include('layouts.styles')

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="GekyChat - Real-time Messaging">
    <meta property="og:description" content="Join GekyChat and chat instantly with others using real-time WebSockets.">
    <meta property="og:image" content="{{ asset('icons/icon-512x512.png') }}">
    <meta property="og:site_name" content="GekyChat">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="GekyChat">
    <meta name="twitter:description" content="Chat in real-time using GekyChat with file sharing and notifications.">
    <meta name="twitter:image" content="{{ asset('icons/icon-512x512.png') }}">

    {{-- Early theme (avoid flash) --}}
    <script>
        (function() {
            try {
                const saved = localStorage.getItem('theme');
                const system = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                document.documentElement.dataset.theme = saved || system || 'dark';
            } catch {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>

    @stack('head')
</head>

<body class="@yield('body_class')">
    <div id="app">
        <main class="content-wrap" id="main-content" tabindex="-1">
            <div class="container-fluid h-100 p-0">
                @if(Request::is('c*') || Request::is('g*') || Request::is('contacts*') || Request::is('settings*'))
                    {{-- Chat interface layout with sidebar --}}
                    <div class="row h-100 g-0">
                        {{-- Shared Sidebar --}}
                        @include('partials.chat_sidebar')

                        {{-- Main Content Area --}}
                        <div class="col-md-8 col-lg-9 d-flex flex-column" id="chat-area">
                            @yield('content')
                        </div>
                    </div>
                @else
                    {{-- Regular layout without sidebar (for auth, landing pages, etc.) --}}
                    @yield('content')
                @endif
            </div>
        </main>
    </div>

    {{-- Global JavaScript --}}
    @include('layouts.scripts')

    {{-- Page Specific Scripts and Styles --}}
    @stack('scripts')
    @stack('styles')
</body>

</html>