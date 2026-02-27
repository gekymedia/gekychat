<?php

namespace App\Http\Controllers\Api\V1\Sika;

use App\Exceptions\Sika\PbgApiException;
use App\Exceptions\Sika\SikaWalletException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sika\AdminAdjustRequest;
use App\Http\Resources\Sika\SikaCashoutRequestResource;
use App\Models\Sika\SikaCashoutRequest;
use App\Models\Sika\SikaCashoutTier;
use App\Models\Sika\SikaMerchant;
use App\Models\Sika\SikaPack;
use App\Services\Sika\SikaWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SikaAdminController extends Controller
{
    public function __construct(
        private SikaWalletService $walletService
    ) {
        $this->middleware('role:admin');
    }

    /**
     * List all coin packs (admin)
     */
    public function listPacks(): JsonResponse
    {
        $packs = SikaPack::orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $packs,
        ]);
    }

    /**
     * Create a new coin pack
     */
    public function createPack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price_ghs' => 'required|numeric|min:0.01',
            'coins' => 'required|integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $pack = SikaPack::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pack created successfully',
            'data' => $pack,
        ], 201);
    }

    /**
     * Update a coin pack
     */
    public function updatePack(Request $request, int $id): JsonResponse
    {
        $pack = SikaPack::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:500',
            'price_ghs' => 'numeric|min:0.01',
            'coins' => 'integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $pack->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pack updated successfully',
            'data' => $pack,
        ]);
    }

    /**
     * List cashout tiers
     */
    public function listCashoutTiers(): JsonResponse
    {
        $tiers = SikaCashoutTier::orderBy('min_coins')->get();

        return response()->json([
            'success' => true,
            'data' => $tiers,
        ]);
    }

    /**
     * Create a cashout tier
     */
    public function createCashoutTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'min_coins' => 'required|integer|min:1',
            'max_coins' => 'nullable|integer|min:1',
            'ghs_per_million_coins' => 'required|numeric|min:0.01',
            'fee_percent' => 'nullable|numeric|min:0|max:100',
            'fee_flat_ghs' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:1',
            'weekly_limit' => 'nullable|integer|min:1',
            'monthly_limit' => 'nullable|integer|min:1',
            'hold_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $tier = SikaCashoutTier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cashout tier created successfully',
            'data' => $tier,
        ], 201);
    }

    /**
     * Update a cashout tier
     */
    public function updateCashoutTier(Request $request, int $id): JsonResponse
    {
        $tier = SikaCashoutTier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'min_coins' => 'integer|min:1',
            'max_coins' => 'nullable|integer|min:1',
            'ghs_per_million_coins' => 'numeric|min:0.01',
            'fee_percent' => 'nullable|numeric|min:0|max:100',
            'fee_flat_ghs' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:1',
            'weekly_limit' => 'nullable|integer|min:1',
            'monthly_limit' => 'nullable|integer|min:1',
            'hold_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $tier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cashout tier updated successfully',
            'data' => $tier,
        ]);
    }

    /**
     * List pending cashout requests
     */
    public function listCashoutRequests(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $perPage = min($request->input('per_page', 20), 100);

        $query = SikaCashoutRequest::with(['user', 'tier'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => SikaCashoutRequestResource::collection($requests),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Approve a cashout request
     */
    public function approveCashout(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->walletService->approveCashout($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Cashout request approved',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a cashout request
     */
    public function rejectCashout(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->walletService->rejectCashout(
                $id,
                $request->user()->id,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Cashout request rejected',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process an approved cashout (credit PBG wallet)
     */
    public function processCashout(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->walletService->processCashout($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Cashout processed successfully',
                'data' => $result,
            ]);

        } catch (PbgApiException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to credit Priority Bank wallet: ' . $e->getMessage(),
            ], 500);

        } catch (SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Admin wallet adjustment
     */
    public function adjustWallet(AdminAdjustRequest $request): JsonResponse
    {
        try {
            $result = $this->walletService->adminAdjust(
                $request->validated('user_id'),
                $request->validated('coins'),
                $request->validated('direction'),
                $request->validated('idempotency_key'),
                $request->user()->id,
                $request->validated('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Wallet adjusted successfully',
                'data' => $result,
            ]);

        } catch (SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List merchants
     */
    public function listMerchants(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $perPage = min($request->input('per_page', 20), 100);

        $query = SikaMerchant::with('owner')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $merchants = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $merchants,
        ]);
    }

    /**
     * Approve a merchant
     */
    public function approveMerchant(int $id, Request $request): JsonResponse
    {
        $merchant = SikaMerchant::findOrFail($id);

        if (!$merchant->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant is not pending approval',
            ], 400);
        }

        $merchant->status = SikaMerchant::STATUS_ACTIVE;
        $merchant->approved_by = $request->user()->id;
        $merchant->approved_at = now();
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Merchant approved',
            'data' => $merchant,
        ]);
    }

    /**
     * Suspend a merchant
     */
    public function suspendMerchant(int $id, Request $request): JsonResponse
    {
        $merchant = SikaMerchant::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $merchant->status = SikaMerchant::STATUS_SUSPENDED;
        $merchant->meta = array_merge($merchant->meta ?? [], [
            'suspension_reason' => $validated['reason'],
            'suspended_at' => now()->toIso8601String(),
            'suspended_by' => $request->user()->id,
        ]);
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Merchant suspended',
            'data' => $merchant,
        ]);
    }
}
