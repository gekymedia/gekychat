<?php

namespace App\Http\Controllers\Api\V1\Sika;

use App\Exceptions\Sika\PbgApiException;
use App\Exceptions\Sika\PbgInsufficientFundsException;
use App\Exceptions\Sika\SikaWalletException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sika\CashoutRequest;
use App\Http\Requests\Sika\GiftCoinsRequest;
use App\Http\Requests\Sika\MerchantPayRequest;
use App\Http\Requests\Sika\PurchasePackRequest;
use App\Http\Requests\Sika\TransferCoinsRequest;
use App\Http\Resources\Sika\SikaLedgerEntryResource;
use App\Http\Resources\Sika\SikaWalletResource;
use App\Models\WorldFeedPost;
use App\Services\Sika\SikaWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SikaWalletController extends Controller
{
    public function __construct(
        private SikaWalletService $walletService
    ) {}

    /**
     * Get current user's wallet
     * 
     * GET /api/sika/wallet
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $walletData = $this->walletService->getWalletWithVerifiedBalance($userId);

        $wallet = $this->walletService->getOrCreateWallet($userId);
        $recentEntries = $wallet->ledgerEntries()
            ->posted()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => new SikaWalletResource($wallet),
                'recent_transactions' => SikaLedgerEntryResource::collection($recentEntries),
            ],
        ]);
    }

    /**
     * Get transaction history
     * 
     * GET /api/sika/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min($request->input('per_page', 20), 100);
        $type = $request->input('type');
        $direction = $request->input('direction');

        $result = $this->walletService->getTransactions($userId, $perPage, $type, $direction);

        return response()->json([
            'success' => true,
            'data' => SikaLedgerEntryResource::collection($result['data']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Purchase coins with a pack
     * 
     * POST /api/sika/purchase/initiate
     */
    public function purchase(PurchasePackRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->purchasePack(
                $request->user()->id,
                $request->validated('pack_id'),
                $request->validated('idempotency_key')
            );

            return response()->json([
                'success' => true,
                'message' => 'Coins purchased successfully',
                'data' => $result,
            ]);

        } catch (PbgInsufficientFundsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient funds in your Priority Bank wallet',
                'error_code' => 'INSUFFICIENT_PBG_BALANCE',
            ], 402);

        } catch (PbgApiException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again.',
                'error_code' => 'PBG_ERROR',
            ], 500);

        } catch (SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WALLET_ERROR',
            ], 400);
        }
    }

    /**
     * Transfer coins to another user
     * 
     * POST /api/sika/transfer
     */
    public function transfer(TransferCoinsRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->transfer(
                $request->user()->id,
                $request->validated('to_user_id'),
                $request->validated('coins'),
                $request->validated('idempotency_key'),
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Coins transferred successfully',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            $statusCode = match ($e->getCode()) {
                SikaWalletException::CODE_INSUFFICIENT_BALANCE => 402,
                SikaWalletException::CODE_SELF_TRANSFER => 400,
                SikaWalletException::CODE_WALLET_SUSPENDED,
                SikaWalletException::CODE_WALLET_FROZEN => 403,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WALLET_ERROR',
            ], $statusCode);
        }
    }

    /**
     * Gift coins to a user or post creator
     * 
     * POST /api/sika/gift
     */
    public function gift(GiftCoinsRequest $request): JsonResponse
    {
        try {
            $toUserId = $request->validated('to_user_id');
            $postId = $request->validated('post_id');

            if ($postId && !$toUserId) {
                $post = WorldFeedPost::findOrFail($postId);
                $toUserId = $post->user_id;
            }

            $result = $this->walletService->gift(
                $request->user()->id,
                $toUserId,
                $request->validated('coins'),
                $request->validated('idempotency_key'),
                $postId,
                $request->validated('message_id'),
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Gift sent successfully',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            $statusCode = match ($e->getCode()) {
                SikaWalletException::CODE_INSUFFICIENT_BALANCE => 402,
                SikaWalletException::CODE_SELF_TRANSFER => 400,
                SikaWalletException::CODE_WALLET_SUSPENDED,
                SikaWalletException::CODE_WALLET_FROZEN => 403,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WALLET_ERROR',
            ], $statusCode);
        }
    }

    /**
     * Request cashout (feature-flagged)
     * 
     * POST /api/sika/cashout/request
     */
    public function requestCashout(CashoutRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->requestCashout(
                $request->user()->id,
                $request->validated('coins'),
                $request->validated('idempotency_key')
            );

            return response()->json([
                'success' => true,
                'message' => 'Cashout request submitted',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            $statusCode = match ($e->getCode()) {
                SikaWalletException::CODE_CASHOUT_DISABLED => 403,
                SikaWalletException::CODE_INSUFFICIENT_BALANCE => 402,
                SikaWalletException::CODE_CASHOUT_LIMIT_EXCEEDED => 429,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'CASHOUT_ERROR',
            ], $statusCode);
        }
    }

    /**
     * Pay a merchant (feature-flagged)
     * 
     * POST /api/sika/merchant/pay
     */
    public function payMerchant(MerchantPayRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->payMerchant(
                $request->user()->id,
                $request->validated('merchant_id'),
                $request->validated('coins'),
                $request->validated('idempotency_key'),
                $request->validated('description'),
                $request->validated('items')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            $statusCode = match ($e->getCode()) {
                SikaWalletException::CODE_MERCHANT_PAY_DISABLED => 403,
                SikaWalletException::CODE_MERCHANT_NOT_ACTIVE => 400,
                SikaWalletException::CODE_INSUFFICIENT_BALANCE => 402,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'MERCHANT_PAY_ERROR',
            ], $statusCode);
        }
    }
}
