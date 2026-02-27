<?php

namespace App\Http\Controllers;

use App\Models\Sika\SikaPack;
use App\Services\Sika\SikaWalletService;
use Illuminate\Http\Request;

class SikaWalletWebController extends Controller
{
    public function __construct(
        private SikaWalletService $walletService
    ) {}

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $wallet = $this->walletService->getOrCreateWallet($userId);
        $packs = SikaPack::where('is_active', true)
            ->orderBy('coins')
            ->get();
        
        return view('sika.index', compact('wallet', 'packs'));
    }
}
