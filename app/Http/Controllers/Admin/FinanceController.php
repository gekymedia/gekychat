<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinanceController extends Controller
{
    /**
     * Admin income list (Account & Finance).
     */
    public function incomeIndex(Request $request)
    {
        $query = Income::query();

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        $incomes = $query->latest('date')->paginate(20);
        $total = (clone $query)->sum('amount');

        return view('admin.finance.income-index', compact('incomes', 'total'));
    }

    /**
     * Admin expenditure list (Account & Finance).
     */
    public function expenditureIndex(Request $request)
    {
        $query = Expense::query();

        if ($request->filled('from')) {
            $query->where('spent_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('spent_at', '<=', $request->input('to'));
        }

        $expenses = $query->latest('spent_at')->paginate(20);
        $total = (clone $query)->sum('amount');

        return view('admin.finance.expenditure-index', compact('expenses', 'total'));
    }

    /**
     * Show the form for creating a new income record.
     */
    public function incomeCreate()
    {
        return view('admin.finance.income-create');
    }

    /**
     * Store a new income record.
     */
    public function incomeStore(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'external_transaction_id' => ['nullable', 'string', 'max:64'],
        ]);

        Income::create($validated);

        return redirect()
            ->route('admin.finance.income.index')
            ->with('success', 'Income record added successfully.');
    }

    /**
     * Show the form for creating a new expenditure record.
     */
    public function expenditureCreate()
    {
        return view('admin.finance.expenditure-create');
    }

    /**
     * Store a new expenditure record.
     */
    public function expenditureStore(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'spent_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'external_transaction_id' => ['nullable', 'string', 'max:64'],
        ]);

        Expense::create($validated);

        return redirect()
            ->route('admin.finance.expenditure.index')
            ->with('success', 'Expenditure record added successfully.');
    }

    /**
     * Sync a single income record to Priority Bank (central-finance API).
     */
    public function incomeSync(Income $income)
    {
        $baseUrl = rtrim((string) (SystemSetting::get('priority_bank_api_url') ?: config('services.priority_bank.api_url', '')), '/');
        $token = SystemSetting::get('priority_bank_api_token') ?: config('services.priority_bank.api_token');
        $systemId = SystemSetting::get('priority_bank_system_id') ?: config('services.priority_bank.system_id', 'gekychat');

        if (! $baseUrl || ! $token) {
            return redirect()
                ->route('admin.finance.income.index')
                ->with('error', 'Priority Bank sync is not configured. Set API URL and API Token in Admin → System Settings → Priority Bank, or in .env.');
        }

        if (empty($income->external_transaction_id)) {
            $income->external_transaction_id = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $income->save();
        }

        $url = $baseUrl . '/api/central-finance/income';
        $idempotencyKey = hash('sha256', "{$systemId}:{$income->external_transaction_id}");

        try {
            $response = Http::withToken($token)
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->timeout(15)
                ->post($url, [
                    'system_id' => $systemId,
                    'external_transaction_id' => $income->external_transaction_id,
                    'amount' => (float) $income->amount,
                    'date' => optional($income->date)?->format('Y-m-d'),
                    'channel' => 'bank',
                    'notes' => $income->description ?? $income->category,
                    'income_category_name' => $income->category,
                ]);

            if ($response->successful()) {
                return redirect()
                    ->route('admin.finance.income.index')
                    ->with('success', 'Income synced to Priority Bank successfully.');
            }

            $status = $response->status();
            $body = $response->json();
            $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;
            if (empty($message)) {
                $message = match ($status) {
                    404 => 'HTTP 404 Not Found. Check that the Priority Bank API URL is correct and that the central-finance API is enabled.',
                    401 => 'HTTP 401 Unauthorized. Check that the API token is valid.',
                    403 => 'HTTP 403 Forbidden. The API token may not have access to this endpoint.',
                    422 => 'HTTP 422 Validation failed. ' . (is_array($body) && isset($body['errors']) ? json_encode($body['errors']) : ($response->body() ?? '')),
                    500 => 'HTTP 500 Server error on Priority Bank. Try again later.',
                    default => 'Bank returned HTTP ' . $status . '. ' . (strlen($response->body() ?? '') > 0 ? substr($response->body(), 0, 200) : 'No response body.'),
                };
            }
            Log::warning('Priority Bank income sync failed', [
                'income_id' => $income->id,
                'status' => $status,
                'url' => $url,
                'body' => $body ?? $response->body(),
            ]);

            return redirect()
                ->route('admin.finance.income.index')
                ->with('error', 'Bank sync failed: ' . $message);
        } catch (\Exception $e) {
            Log::error('Priority Bank income sync exception', [
                'income_id' => $income->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.finance.income.index')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync a single expenditure record to Priority Bank (central-finance API).
     */
    public function expenditureSync(Expense $expense)
    {
        $baseUrl = rtrim((string) (SystemSetting::get('priority_bank_api_url') ?: config('services.priority_bank.api_url', '')), '/');
        $token = SystemSetting::get('priority_bank_api_token') ?: config('services.priority_bank.api_token');
        $systemId = SystemSetting::get('priority_bank_system_id') ?: config('services.priority_bank.system_id', 'gekychat');

        if (! $baseUrl || ! $token) {
            return redirect()
                ->route('admin.finance.expenditure.index')
                ->with('error', 'Priority Bank sync is not configured. Set API URL and API Token in Admin → System Settings → Priority Bank, or in .env.');
        }

        if (empty($expense->external_transaction_id)) {
            $expense->external_transaction_id = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $expense->save();
        }

        $url = $baseUrl . '/api/central-finance/expense';
        $idempotencyKey = hash('sha256', "{$systemId}:exp:{$expense->external_transaction_id}");

        try {
            $response = Http::withToken($token)
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->timeout(15)
                ->post($url, [
                    'system_id' => $systemId,
                    'external_transaction_id' => $expense->external_transaction_id,
                    'amount' => (float) $expense->amount,
                    'date' => optional($expense->spent_at)?->format('Y-m-d'),
                    'channel' => 'bank',
                    'notes' => $expense->description ?? $expense->category,
                    'expense_category_name' => $expense->category,
                ]);

            if ($response->successful()) {
                return redirect()
                    ->route('admin.finance.expenditure.index')
                    ->with('success', 'Expenditure synced to Priority Bank successfully.');
            }

            $status = $response->status();
            $body = $response->json();
            $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;
            if (empty($message)) {
                $message = match ($status) {
                    404 => 'HTTP 404 Not Found. Check that the Priority Bank API URL is correct and that the central-finance API is enabled.',
                    401 => 'HTTP 401 Unauthorized. Check that the API token is valid.',
                    403 => 'HTTP 403 Forbidden. The API token may not have access to this endpoint.',
                    422 => 'HTTP 422 Validation failed. ' . (is_array($body) && isset($body['errors']) ? json_encode($body['errors']) : ($response->body() ?? '')),
                    500 => 'HTTP 500 Server error on Priority Bank. Try again later.',
                    default => 'Bank returned HTTP ' . $status . '. ' . (strlen($response->body() ?? '') > 0 ? substr($response->body(), 0, 200) : 'No response body.'),
                };
            }
            Log::warning('Priority Bank expenditure sync failed', [
                'expense_id' => $expense->id,
                'status' => $status,
                'url' => $url,
                'body' => $body ?? $response->body(),
            ]);

            return redirect()
                ->route('admin.finance.expenditure.index')
                ->with('error', 'Bank sync failed: ' . $message);
        } catch (\Exception $e) {
            Log::error('Priority Bank expenditure sync exception', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.finance.expenditure.index')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}
