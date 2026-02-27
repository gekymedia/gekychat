@extends('layouts.admin')

@section('title', 'Sika Coins Dashboard')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 dark:text-gray-400 hover:text-blue-600">Dashboard</a>
    </li>
    <li class="inline-flex items-center">
        <svg class="w-3 h-3 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Sika Coins</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                <span class="w-10 h-10 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-coins text-white"></i>
                </span>
                Sika Coins Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Monitor virtual currency transactions and wallet activity</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 sm:mt-0">
            <button id="refreshData" class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
            <a href="{{ route('admin.sika.transactions') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-list mr-2"></i>
                All Transactions
            </a>
        </div>
    </div>

    <!-- Quick Stats Row 1 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Coins in Circulation -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Coins in Circulation</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="total_coins">
                        {{ number_format($totalCoinsInCirculation) }}
                    </p>
                    <p class="text-xs text-yellow-600 dark:text-yellow-400">
                        <i class="fas fa-wallet mr-1"></i>{{ $activeWallets }} active wallets
                    </p>
                </div>
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                    <i class="fas fa-coins text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Wallets -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Wallets</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="total_wallets">
                        {{ number_format($totalWallets) }}
                    </p>
                    <p class="text-xs text-blue-600 dark:text-blue-400">
                        <i class="fas fa-check-circle mr-1"></i>{{ $activeWallets }} active
                    </p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-wallet text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <a href="{{ route('admin.sika.wallets') }}" class="mt-3 inline-flex items-center text-blue-600 dark:text-blue-400 text-sm font-medium hover:underline">
                View Wallets
                <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>

        <!-- Total Transactions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Transactions</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($totalTransactions) }}
                    </p>
                    <p class="text-xs text-green-600 dark:text-green-400">
                        <i class="fas fa-check mr-1"></i>{{ number_format($postedTransactions) }} posted
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-exchange-alt text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
            <a href="{{ route('admin.sika.transactions') }}" class="mt-3 inline-flex items-center text-green-600 dark:text-green-400 text-sm font-medium hover:underline">
                View Transactions
                <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>

        <!-- Pending Cashouts -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Cashouts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="pending_cashouts">
                        {{ $pendingCashouts }}
                    </p>
                    <p class="text-xs text-red-600 dark:text-red-400">
                        <i class="fas fa-coins mr-1"></i>{{ number_format($pendingCashoutAmount) }} coins
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-clock text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
            @if($pendingCashouts > 0)
            <a href="{{ route('admin.sika.cashouts') }}?status=PENDING" class="mt-3 inline-flex items-center text-red-600 dark:text-red-400 text-sm font-medium hover:underline">
                Review Cashouts
                <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
            @endif
        </div>
    </div>

    <!-- Today's Activity -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-green-100">Today's Purchases</p>
                    <p class="text-3xl font-bold" data-metric="today_purchases">{{ number_format($todayPurchases) }}</p>
                    <p class="text-xs text-green-200 mt-1">coins purchased</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <i class="fas fa-shopping-cart text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-blue-100">Today's Transfers</p>
                    <p class="text-3xl font-bold" data-metric="today_transfers">{{ number_format($todayTransfers) }}</p>
                    <p class="text-xs text-blue-200 mt-1">coins transferred</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <i class="fas fa-paper-plane text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl shadow-lg p-6 text-white hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-purple-100">Today's Gifts</p>
                    <p class="text-3xl font-bold" data-metric="today_gifts">{{ number_format($todayGifts) }}</p>
                    <p class="text-xs text-purple-200 mt-1">coins gifted</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <i class="fas fa-gift text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Daily Volume Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Transaction Volume</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Last 14 days</span>
            </div>
            <div class="h-64">
                <canvas id="dailyVolumeChart"></canvas>
            </div>
        </div>

        <!-- Transaction Type Breakdown -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction Types</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Last 30 days</span>
            </div>
            <div class="space-y-3">
                @forelse($transactionsByType as $type)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center
                            @if(str_contains($type->type, 'PURCHASE')) bg-green-100 dark:bg-green-900
                            @elseif(str_contains($type->type, 'TRANSFER')) bg-blue-100 dark:bg-blue-900
                            @elseif(str_contains($type->type, 'GIFT')) bg-purple-100 dark:bg-purple-900
                            @elseif(str_contains($type->type, 'CASHOUT')) bg-red-100 dark:bg-red-900
                            @else bg-gray-100 dark:bg-gray-600
                            @endif
                        ">
                            <i class="fas 
                                @if(str_contains($type->type, 'PURCHASE')) fa-shopping-cart text-green-600 dark:text-green-400
                                @elseif(str_contains($type->type, 'TRANSFER')) fa-paper-plane text-blue-600 dark:text-blue-400
                                @elseif(str_contains($type->type, 'GIFT')) fa-gift text-purple-600 dark:text-purple-400
                                @elseif(str_contains($type->type, 'CASHOUT')) fa-money-bill-wave text-red-600 dark:text-red-400
                                @else fa-exchange-alt text-gray-600 dark:text-gray-400
                                @endif
                            "></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ str_replace('_', ' ', $type->type) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($type->count) }} transactions</p>
                        </div>
                    </div>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($type->total_coins) }}</span>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>No transactions yet</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 30-Day Volume Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Purchases (30d)</h4>
                <i class="fas fa-shopping-cart text-green-500"></i>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Volume</span>
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($purchaseVolume) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Transactions</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($purchaseCount) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg per Transaction</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $purchaseCount > 0 ? number_format($purchaseVolume / $purchaseCount) : 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Transfers (30d)</h4>
                <i class="fas fa-paper-plane text-blue-500"></i>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Volume</span>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ number_format($transferVolume) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Transactions</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($transferCount) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg per Transaction</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $transferCount > 0 ? number_format($transferVolume / $transferCount) : 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Gifts (30d)</h4>
                <i class="fas fa-gift text-purple-500"></i>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Volume</span>
                    <span class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ number_format($giftVolume) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Transactions</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($giftCount) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg per Transaction</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $giftCount > 0 ? number_format($giftVolume / $giftCount) : 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Wallets & Recent Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Wallets by Balance -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Wallets by Balance</h3>
                <a href="{{ route('admin.sika.wallets') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    View All
                </a>
            </div>
            <div class="space-y-3">
                @forelse($topWallets as $index => $wallet)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $wallet->user->name ?? 'Unknown User' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $wallet->user->phone_number ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($wallet->balance_cached) }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">coins</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-wallet text-3xl mb-2"></i>
                    <p>No wallets with balance yet</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                <a href="{{ route('admin.sika.transactions') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    View All
                </a>
            </div>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @forelse($recentTransactions as $transaction)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center
                            @if($transaction->direction === 'CREDIT') bg-green-100 dark:bg-green-900
                            @else bg-red-100 dark:bg-red-900
                            @endif
                        ">
                            <i class="fas 
                                @if($transaction->direction === 'CREDIT') fa-arrow-down text-green-600 dark:text-green-400
                                @else fa-arrow-up text-red-600 dark:text-red-400
                                @endif
                            "></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $transaction->wallet->user->name ?? 'Unknown' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ str_replace('_', ' ', $transaction->type) }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold 
                            @if($transaction->direction === 'CREDIT') text-green-600 dark:text-green-400
                            @else text-red-600 dark:text-red-400
                            @endif
                        ">
                            {{ $transaction->direction === 'CREDIT' ? '+' : '-' }}{{ number_format($transaction->coins) }}
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $transaction->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>No transactions yet</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Coin Packs Info -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Coin Packs</h3>
            <a href="{{ route('admin.sika.packs') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                Manage Packs
            </a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalPacks }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Packs</p>
            </div>
            <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $activePacks }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Active Packs</p>
            </div>
            <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalPacks - $activePacks }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Inactive Packs</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">GHS</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Currency</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Daily Volume Chart
const dailyVolumeCtx = document.getElementById('dailyVolumeChart').getContext('2d');
const dailyVolumeChart = new Chart(dailyVolumeCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($chartLabels) !!},
        datasets: [
            {
                label: 'Credits',
                data: {!! json_encode($chartCredits) !!},
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Debits',
                data: {!! json_encode($chartDebits) !!},
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toLocaleString() + ' coins';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Refresh button handler
document.getElementById('refreshData')?.addEventListener('click', async function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
    btn.disabled = true;
    
    try {
        const response = await fetch('{{ route("admin.sika.refresh") }}');
        const result = await response.json();
        
        if (result.status === 'success') {
            // Update metrics
            const metrics = result.data;
            document.querySelectorAll('[data-metric="total_coins"]').forEach(el => {
                el.textContent = metrics.total_coins.toLocaleString();
            });
            document.querySelectorAll('[data-metric="total_wallets"]').forEach(el => {
                el.textContent = metrics.total_wallets.toLocaleString();
            });
            document.querySelectorAll('[data-metric="today_purchases"]').forEach(el => {
                el.textContent = metrics.today_purchases.toLocaleString();
            });
            document.querySelectorAll('[data-metric="today_transfers"]').forEach(el => {
                el.textContent = metrics.today_transfers.toLocaleString();
            });
            document.querySelectorAll('[data-metric="today_gifts"]').forEach(el => {
                el.textContent = metrics.today_gifts.toLocaleString();
            });
            document.querySelectorAll('[data-metric="pending_cashouts"]').forEach(el => {
                el.textContent = metrics.pending_cashouts;
            });
        }
    } catch (error) {
        console.error('Error refreshing data:', error);
    }
    
    setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }, 1000);
});

// Auto-refresh every 2 minutes
setInterval(async () => {
    try {
        const response = await fetch('{{ route("admin.sika.refresh") }}');
        const result = await response.json();
        
        if (result.status === 'success') {
            const metrics = result.data;
            document.querySelectorAll('[data-metric="total_coins"]').forEach(el => {
                el.textContent = metrics.total_coins.toLocaleString();
            });
            document.querySelectorAll('[data-metric="pending_cashouts"]').forEach(el => {
                el.textContent = metrics.pending_cashouts;
            });
        }
    } catch (error) {
        console.error('Auto-refresh error:', error);
    }
}, 120000);
</script>
@endsection
