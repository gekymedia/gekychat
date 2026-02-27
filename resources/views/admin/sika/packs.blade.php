@extends('layouts.admin')

@section('title', 'Sika Coins - Coin Packs')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 dark:text-gray-400 hover:text-blue-600">Dashboard</a>
    </li>
    <li class="inline-flex items-center">
        <svg class="w-3 h-3 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
        <a href="{{ route('admin.sika.dashboard') }}" class="text-gray-500 dark:text-gray-400 hover:text-blue-600">Sika Coins</a>
    </li>
    <li class="inline-flex items-center">
        <svg class="w-3 h-3 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Coin Packs</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Coin Packs</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage available coin purchase packages</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 sm:mt-0">
            <a href="{{ route('admin.sika.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Packs Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($packs as $pack)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden {{ !$pack->is_active ? 'opacity-60' : '' }}">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $pack->name }}</h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        @if($pack->is_active) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @else bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                        @endif
                    ">
                        {{ $pack->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                @if($pack->description)
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $pack->description }}</p>
                @endif

                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Coins</span>
                        <span class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($pack->coins) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Price</span>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">GHS {{ number_format($pack->price_ghs, 2) }}</span>
                    </div>
                    @if($pack->bonus_coins > 0)
                    <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Bonus</span>
                        <span class="text-lg font-bold text-green-600 dark:text-green-400">+{{ number_format($pack->bonus_coins) }}</span>
                    </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Sort Order: {{ $pack->sort_order }}</span>
                        <span>ID: {{ $pack->id }}</span>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400">No coin packs configured yet</p>
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection
