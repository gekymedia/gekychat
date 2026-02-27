<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sika\SikaWallet;
use App\Models\Sika\SikaLedgerEntry;
use App\Models\Sika\SikaPack;
use App\Models\Sika\SikaCashoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SikaCoinsController extends Controller
{
    public function index()
    {
        // Summary Statistics
        $totalWallets = SikaWallet::count();
        $activeWallets = SikaWallet::where('status', 'active')->count();
        $totalCoinsInCirculation = SikaWallet::sum('balance_cached');
        
        // Transaction Statistics
        $totalTransactions = SikaLedgerEntry::count();
        $postedTransactions = SikaLedgerEntry::where('status', 'POSTED')->count();
        $pendingTransactions = SikaLedgerEntry::where('status', 'PENDING')->count();
        
        // Volume Statistics (Last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        
        $purchaseVolume = SikaLedgerEntry::where('type', 'PURCHASE_CREDIT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('coins');
            
        $purchaseCount = SikaLedgerEntry::where('type', 'PURCHASE_CREDIT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        
        $transferVolume = SikaLedgerEntry::whereIn('type', ['TRANSFER_OUT', 'TRANSFER_IN'])
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('direction', 'DEBIT')
            ->sum('coins');
            
        $transferCount = SikaLedgerEntry::where('type', 'TRANSFER_OUT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        
        $giftVolume = SikaLedgerEntry::whereIn('type', ['GIFT_OUT', 'GIFT_IN'])
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('direction', 'DEBIT')
            ->sum('coins');
            
        $giftCount = SikaLedgerEntry::where('type', 'GIFT_OUT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        
        // Today's Activity
        $today = now()->startOfDay();
        
        $todayPurchases = SikaLedgerEntry::where('type', 'PURCHASE_CREDIT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $today)
            ->sum('coins');
            
        $todayTransfers = SikaLedgerEntry::where('type', 'TRANSFER_OUT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $today)
            ->sum('coins');
            
        $todayGifts = SikaLedgerEntry::where('type', 'GIFT_OUT')
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $today)
            ->sum('coins');
        
        // Transaction Type Breakdown
        $transactionsByType = SikaLedgerEntry::select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(coins) as total_coins'))
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('type')
            ->orderBy('total_coins', 'desc')
            ->get();
        
        // Daily Volume Chart Data (Last 14 days)
        $dailyVolume = SikaLedgerEntry::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN direction = "CREDIT" THEN coins ELSE 0 END) as credits'),
                DB::raw('SUM(CASE WHEN direction = "DEBIT" THEN coins ELSE 0 END) as debits'),
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'POSTED')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
        
        // Recent Transactions
        $recentTransactions = SikaLedgerEntry::with(['wallet.user'])
            ->where('status', 'POSTED')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Top Wallets by Balance
        $topWallets = SikaWallet::with('user')
            ->where('balance_cached', '>', 0)
            ->orderBy('balance_cached', 'desc')
            ->limit(10)
            ->get();
        
        // Top Users by Transaction Volume (Last 30 days)
        $topUsersByVolume = SikaLedgerEntry::select('wallet_id', DB::raw('SUM(coins) as total_volume'), DB::raw('COUNT(*) as transaction_count'))
            ->where('status', 'POSTED')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('wallet_id')
            ->orderBy('total_volume', 'desc')
            ->limit(10)
            ->with('wallet.user')
            ->get();
        
        // Coin Packs Statistics
        $activePacks = SikaPack::where('is_active', true)->count();
        $totalPacks = SikaPack::count();
        
        // Pending Cashout Requests
        $pendingCashouts = SikaCashoutRequest::where('status', 'PENDING')->count();
        $pendingCashoutAmount = SikaCashoutRequest::where('status', 'PENDING')->sum('coins_requested');
        
        // Chart Data
        $chartLabels = $dailyVolume->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))->toArray();
        $chartCredits = $dailyVolume->pluck('credits')->map(fn($v) => (int) $v)->toArray();
        $chartDebits = $dailyVolume->pluck('debits')->map(fn($v) => (int) $v)->toArray();
        
        return view('admin.sika.dashboard', compact(
            'totalWallets',
            'activeWallets',
            'totalCoinsInCirculation',
            'totalTransactions',
            'postedTransactions',
            'pendingTransactions',
            'purchaseVolume',
            'purchaseCount',
            'transferVolume',
            'transferCount',
            'giftVolume',
            'giftCount',
            'todayPurchases',
            'todayTransfers',
            'todayGifts',
            'transactionsByType',
            'recentTransactions',
            'topWallets',
            'topUsersByVolume',
            'activePacks',
            'totalPacks',
            'pendingCashouts',
            'pendingCashoutAmount',
            'chartLabels',
            'chartCredits',
            'chartDebits'
        ));
    }
    
    public function transactions(Request $request)
    {
        $query = SikaLedgerEntry::with(['wallet.user']);
        
        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        
        if ($request->filled('user_id')) {
            $query->whereHas('wallet', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('idempotency_key', 'like', "%{$search}%")
                  ->orWhere('group_id', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%");
            });
        }
        
        $transactions = $query->orderBy('created_at', 'desc')->paginate(50);
        
        $transactionTypes = [
            'PURCHASE_CREDIT', 'TRANSFER_OUT', 'TRANSFER_IN', 
            'GIFT_OUT', 'GIFT_IN', 'SPEND', 'MERCHANT_PAY', 
            'MERCHANT_RECEIVE', 'CASHOUT_DEBIT', 'REFUND', 'ADMIN_ADJUST'
        ];
        
        return view('admin.sika.transactions', compact('transactions', 'transactionTypes'));
    }
    
    public function wallets(Request $request)
    {
        $query = SikaWallet::with('user');
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }
        
        $wallets = $query->orderBy('balance_cached', 'desc')->paginate(50);
        
        return view('admin.sika.wallets', compact('wallets'));
    }
    
    public function walletDetails(SikaWallet $wallet)
    {
        $wallet->load('user');
        
        $transactions = $wallet->ledgerEntries()
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        // Calculate actual balance from ledger
        $ledgerBalance = $wallet->calculateLedgerBalance();
        
        return view('admin.sika.wallet-details', compact('wallet', 'transactions', 'ledgerBalance'));
    }
    
    public function packs()
    {
        $packs = SikaPack::orderBy('sort_order')->get();
        
        return view('admin.sika.packs', compact('packs'));
    }
    
    public function cashouts(Request $request)
    {
        $query = SikaCashoutRequest::with(['user', 'wallet', 'tier', 'approvedBy', 'processedBy']);
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $cashouts = $query->orderBy('created_at', 'desc')->paginate(50);
        
        return view('admin.sika.cashouts', compact('cashouts'));
    }
    
    public function refreshData()
    {
        $thirtyDaysAgo = now()->subDays(30);
        $today = now()->startOfDay();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_wallets' => SikaWallet::count(),
                'active_wallets' => SikaWallet::where('status', 'active')->count(),
                'total_coins' => SikaWallet::sum('balance_cached'),
                'today_purchases' => SikaLedgerEntry::where('type', 'PURCHASE_CREDIT')
                    ->where('status', 'POSTED')
                    ->where('created_at', '>=', $today)
                    ->sum('coins'),
                'today_transfers' => SikaLedgerEntry::where('type', 'TRANSFER_OUT')
                    ->where('status', 'POSTED')
                    ->where('created_at', '>=', $today)
                    ->sum('coins'),
                'today_gifts' => SikaLedgerEntry::where('type', 'GIFT_OUT')
                    ->where('status', 'POSTED')
                    ->where('created_at', '>=', $today)
                    ->sum('coins'),
                'pending_cashouts' => SikaCashoutRequest::where('status', 'PENDING')->count(),
            ],
        ]);
    }
}
