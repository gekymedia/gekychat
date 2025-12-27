@extends('layouts.admin')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'Channels Management')

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
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Channels</span>
        </div>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Channels Management</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage and verify channels</p>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <form method="GET" action="{{ route('admin.channels.index') }}" class="flex flex-col md:flex-row gap-4">
            <!-- Search -->
            <div class="flex-1">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}"
                       placeholder="Search channels by name or description..." 
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Verified Filter -->
            <div class="w-full md:w-48">
                <select name="verified" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Channels</option>
                    <option value="1" {{ request('verified') === '1' ? 'selected' : '' }}>Verified Only</option>
                    <option value="0" {{ request('verified') === '0' ? 'selected' : '' }}>Unverified Only</option>
                </select>
            </div>

            <!-- Sort -->
            <div class="w-full md:w-48">
                <select name="sort" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Date Created</option>
                    <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
                    <option value="members_count" {{ request('sort') === 'members_count' ? 'selected' : '' }}>Members</option>
                </select>
            </div>

            <!-- Submit -->
            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-search mr-2"></i>Filter
            </button>

            @if(request()->has('search') || request()->has('verified'))
                <a href="{{ route('admin.channels.index') }}" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Channels Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($channels as $channel)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($channel->avatar_path)
                                        <img src="{{ $channel->avatar_url }}" 
                                             alt="{{ $channel->name }}" 
                                             class="h-10 w-10 rounded-full object-cover mr-3"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="h-10 w-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-semibold mr-3" style="display: none;">
                                            {{ strtoupper(substr($channel->name, 0, 1)) }}
                                        </div>
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-semibold mr-3">
                                            {{ strtoupper(substr($channel->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="flex items-center gap-2" data-channel-name-container>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $channel->name }}</div>
                                            @if($channel->is_verified)
                                                <span class="verified-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" title="Verified Channel" data-verified-badge>
                                                    <i class="fas fa-check-circle mr-1"></i>Verified
                                                </span>
                                            @endif
                                        </div>
                                        @if($channel->description)
                                            <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $channel->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $channel->owner->name ?? $channel->owner->phone ?? 'Unknown' }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $channel->owner->phone ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $channel->members_count }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($channel->is_public)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Public
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        Private
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $channel->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="toggleVerified({{ $channel->id }}, {{ $channel->is_verified ? 'true' : 'false' }})" 
                                        class="toggle-verified-btn inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $channel->is_verified ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800' : 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800' }}"
                                        data-channel-id="{{ $channel->id }}"
                                        data-verified="{{ $channel->is_verified ? '1' : '0' }}">
                                    <i class="fas {{ $channel->is_verified ? 'fa-times-circle' : 'fa-check-circle' }} mr-1"></i>
                                    {{ $channel->is_verified ? 'Unverify' : 'Verify' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-broadcast-tower text-4xl mb-4"></i>
                                    <p class="text-lg font-medium">No channels found</p>
                                    <p class="text-sm mt-1">Try adjusting your search or filters</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($channels->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $channels->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Make function available globally for inline onclick handlers
(function() {
    'use strict';
    
    window.toggleVerified = async function(channelId, currentlyVerified) {
    console.log('toggleVerified called with channelId:', channelId, 'currentlyVerified:', currentlyVerified);
    
    const btn = document.querySelector(`button[data-channel-id="${channelId}"]`);
    if (!btn) {
        console.error('Button not found for channelId:', channelId);
        alert('Button not found. Please refresh the page.');
        return;
    }

    // Get channel name for confirmation
    const row = btn.closest('tr');
    const channelName = row.querySelector('td:first-child .text-sm.font-medium')?.textContent?.trim() || 'this channel';
    
    // Show confirmation dialog
    const action = currentlyVerified ? 'unverify' : 'verify';
    const confirmMessage = currentlyVerified 
        ? `Are you sure you want to unverify "${channelName}"? This will remove the verified badge.`
        : `Are you sure you want to verify "${channelName}"? This will mark it as a verified channel.`;
    
    if (!confirm(confirmMessage)) {
        return; // User cancelled
    }

    const originalHTML = btn.innerHTML;
    const originalClass = btn.className;

    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating...';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.');
        }

        const url = `/admin/channels/${channelId}/toggle-verified`;
        console.log('Making request to:', url);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        console.log('Response status:', response.status, response.statusText);

        if (!response.ok) {
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                errorMessage = errorData.message || errorData.error || errorMessage;
            } catch (e) {
                const errorText = await response.text();
                errorMessage = errorText || errorMessage;
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();
        console.log('Response data:', data);

        if (data.success) {
            // Update button state
            const newVerified = data.is_verified;
            
            if (newVerified) {
                btn.className = 'toggle-verified-btn inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-colors bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800';
                btn.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Unverify';
                btn.setAttribute('data-verified', '1');
            } else {
                btn.className = 'toggle-verified-btn inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-colors bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800';
                btn.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Verify';
                btn.setAttribute('data-verified', '0');
            }

            // Update verified badge in table
            const row = btn.closest('tr');
            const nameDiv = row.querySelector('[data-channel-name-container]');
            
            if (nameDiv) {
                // Find existing badge using data attribute
                let badge = nameDiv.querySelector('[data-verified-badge]');
                
                if (newVerified && !badge) {
                    // Add verified badge after the channel name
                    badge = document.createElement('span');
                    badge.className = 'verified-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                    badge.setAttribute('data-verified-badge', '');
                    badge.title = 'Verified Channel';
                    badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Verified';
                    nameDiv.appendChild(badge);
                } else if (!newVerified && badge) {
                    // Remove verified badge
                    badge.remove();
                }
            }

            // Show success message
            window.showToast(data.message || 'Channel verification updated', 'success');
        } else {
            throw new Error(data.message || 'Failed to update verification');
        }
    } catch (error) {
        console.error('Error toggling verification:', error);
        window.showToast(error.message || 'Failed to update verification', 'error');
        
        // Restore original state
        btn.innerHTML = originalHTML;
        btn.className = originalClass;
    } finally {
        btn.disabled = false;
    }
    };

    window.showToast = function(message, type = 'success') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white flex items-center gap-3 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };
})(); // End IIFE - functions are now available globally
</script>
@endpush
@endsection
