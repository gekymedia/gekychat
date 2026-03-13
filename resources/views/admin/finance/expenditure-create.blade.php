@extends('layouts.admin')

@section('title', 'Add Expenditure')
@section('breadcrumb')
<li class="inline-flex items-center">
    <a href="{{ route('admin.finance.expenditure.index') }}" class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
        <i class="fas fa-chevron-right mx-2"></i>
        Account & Finance
    </a>
</li>
<li class="inline-flex items-center">
    <a href="{{ route('admin.finance.expenditure.index') }}" class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
        <i class="fas fa-chevron-right mx-2"></i>
        Expenditure
    </a>
</li>
<li class="inline-flex items-center">
    <span class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400">
        <i class="fas fa-chevron-right mx-2"></i>
        Add Expenditure
    </span>
</li>
@endsection

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.finance.expenditure.index') }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Expenditure
    </a>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">New expenditure record</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Record expense for 2-way reconciliation with Priority Bank</p>
    </div>
    <form action="{{ route('admin.finance.expenditure.store') }}" method="POST" class="p-6 space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category <span class="text-red-500">*</span></label>
                <input type="text" name="category" id="category" value="{{ old('category') }}" required
                    placeholder="e.g. hosting, sms, ads, salaries"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('category')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="vendor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vendor</label>
                <input type="text" name="vendor" id="vendor" value="{{ old('vendor') }}" placeholder="Optional"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('vendor')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (GHS) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" id="amount" value="{{ old('amount') }}" required min="0" step="0.01"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('amount')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="spent_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date incurred <span class="text-red-500">*</span></label>
                <input type="date" name="spent_at" id="spent_at" value="{{ old('spent_at', date('Y-m-d')) }}" required
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 max-w-xs">
                @error('spent_at')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea name="description" id="description" rows="3" placeholder="Optional notes"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference</label>
                <input type="text" name="reference" id="reference" value="{{ old('reference') }}" placeholder="Optional reference"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('reference')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="external_transaction_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">External transaction ID</label>
                <input type="text" name="external_transaction_id" id="external_transaction_id" value="{{ old('external_transaction_id') }}" maxlength="64"
                    placeholder="For Priority Bank 2-way sync (optional)"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('external_transaction_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.finance.expenditure.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                <i class="fas fa-check mr-2"></i>
                Add Expenditure
            </button>
        </div>
    </form>
</div>
@endsection
