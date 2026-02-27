@extends('layouts.admin')

@section('title', 'Sika Coins - Wallet Details')

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
        <a href="{{ route('admin.sika.wallets') }}" class="text-gray-500 dark:text-gray-400 hover:text-blue-600">Wallets</a>
    </li>
    <li class="inline-flex items-center">
        <svg class="w-3 h-3 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $wallet->user->name ?? 'Wallet' }}</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Wallet Details</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">View wallet information and transaction history</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 sm:mt-0">
            <a href="{{ route('admin.sika.wallets') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Wallets
            </a>
        </div>
    </div>

    <!-- Wallet Info Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-16 h-16 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                    {{ substr($wallet->user->name ?? 'U', 0, 1) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $wallet->user->name ?? 'Unknown User' }}</h2>
                    <p class="text-gray-500 dark:text-gray-400">{{ $wallet->user->phone_number ?? 'N/A' }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Wallet ID: {{ $wallet->id }}</p>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-6">
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cached Balance</p>
                    <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($wallet->balance_cached) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ledger Balance</p>
                    <p class="text-3xl font-bold {{ $ledgerBalance === $wallet->balance_cached ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($ledgerBalance) }}
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                    <span class="px-3 py-1 text-sm font-medium rounded-full
                        @if($wallet->status === 'active') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($wallet->status === 'suspended') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @endif
                    ">
                        {{ ucfirst($wallet->status) }}
                    </span>
                </div>
            </div>
        </div>

        @if($ledgerBalance !== $wallet->balance_cached)
        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 mr-3"></i>
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">Balance Mismatch Detected</p>
                    <p class="text-xs text-red-600 dark:text-red-400">Cached balance ({{ number_format($wallet->balance_cached) }}) does not match ledger balance ({{ number_format($ledgerBalance) }}). This may require investigation.</p>
                </div>
            </div>
        </div>
        @endif

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400">Created</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $wallet->created_at->format('M d, Y') }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400">Last Updated</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $wallet->updated_at->diffForHumans() }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $transactions->total() }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400">User ID</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $wallet->user_id }}</p>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Direction</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance After</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if(str_contains($transaction->type, 'PURCHASE')) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @elseif(str_contains($transaction->type, 'TRANSFER')) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                @elseif(str_contains($transaction->type, 'GIFT')) bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                @elseif(str_contains($transaction->type, 'CASHOUT')) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                @else bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                                @endif
                            ">
                                {{ str_replace('_', ' ', $transaction->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                @if($transaction->direction === 'CREDIT') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                @endif
                            ">
                                <i class="fas {{ $transaction->direction === 'CREDIT' ? 'fa-arrow-down' : 'fa-arrow-up' }} mr-1"></i>
                                {{ $transaction->direction }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold {{ $transaction->direction === 'CREDIT' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $transaction->direction === 'CREDIT' ? '+' : '-' }}{{ number_format($transaction->coins) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            {{ number_format($transaction->balance_after) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($transaction->status === 'POSTED') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @elseif($transaction->status === 'PENDING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                @else bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                                @endif
                            ">
                                {{ $transaction->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $transaction->created_at->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono" title="{{ $transaction->idempotency_key }}">
                                {{ Str::limit($transaction->idempotency_key, 12) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p>No transactions found for this wallet</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($transactions->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
