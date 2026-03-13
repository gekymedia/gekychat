@extends('layouts.admin')

@section('title', 'Income')
@section('breadcrumb')
<li class="inline-flex items-center">
    <span class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400">
        <i class="fas fa-chevron-right mx-2"></i>
        Account & Finance
    </span>
</li>
<li class="inline-flex items-center">
    <span class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400">
        <i class="fas fa-chevron-right mx-2"></i>
        Income
    </span>
</li>
@endsection

@section('content')
<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Income</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Admin income records linked with Priority Bank</p>
        </div>
        <a href="{{ route('admin.finance.income.create') }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-semibold hover:shadow-lg transition-all duration-200 shadow-sm">
            <i class="fas fa-plus mr-2"></i>
            Add Income
        </a>
    </div>
</div>

<div class="mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="text-2xl font-bold text-gray-800 dark:text-white">GHS {{ number_format($total ?? 0, 2) }}</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total (filtered)</div>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Income records</h2>
    </div>
    <div class="overflow-x-auto">
        @if($incomes->isEmpty())
            <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-arrow-down-to-line text-4xl mb-4 opacity-50"></i>
                <p>No income records yet.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">External ID</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-20">Sync</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($incomes as $income)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-white">{{ $income->date?->format('d M Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $income->category }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $income->description ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-800 dark:text-white">GHS {{ number_format($income->amount, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $income->external_transaction_id ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <form action="{{ route('admin.finance.income.sync', $income) }}" method="POST" class="inline" data-sync-form>
                                    @csrf
                                    <button type="submit" class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Sync with Priority Bank">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if($incomes->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $incomes->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('[data-sync-form]').forEach(function(form) {
    form.addEventListener('submit', function() {
        var btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.querySelector('i').classList.add('fa-spin');
        }
    });
});
</script>
@endsection
