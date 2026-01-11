@extends('layouts.admin')

@section('title', $title ?? 'Coming Soon')

@section('content')
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 text-center">
        <div class="max-w-md mx-auto">
            <div class="mb-6">
                <i class="fas fa-tools text-6xl text-gray-400 dark:text-gray-500"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $title ?? 'Feature Coming Soon' }}
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                This feature is currently under development and will be available soon.
            </p>
            <a href="{{ route('admin.dashboard') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
