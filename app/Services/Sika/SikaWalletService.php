<?php

namespace App\Services\Sika;

use App\Exceptions\Sika\PbgApiException;
use App\Exceptions\Sika\PbgInsufficientFundsException;
use App\Exceptions\Sika\SikaWalletException;
use App\Models\Sika\SikaCashoutRequest;
use App\Models\Sika\SikaCashoutTier;
use App\Models\Sika\SikaLedgerEntry;
use App\Models\Sika\SikaMerchant;
use App\Models\Sika\SikaMerchantOrder;
use App\Models\Sika\SikaPack;
use App\Models\Sika\SikaWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SikaWalletService
{
    public function __construct(
        private PriorityBankService $pbgService,
        private ?SikaNotificationService $notificationService = null
    ) {
        // Lazy-load notification service to avoid circular dependency
        if ($this->notificationService === null) {
            $this->notificationService = app(SikaNotificationService::class);
        }
    }

    /**
     * Get or create wallet for user
     */
    public function getOrCreateWallet(int $userId): SikaWallet
    {
        return SikaWallet::getOrCreateForUser($userId);
    }

    /**
     * Get wallet with balance verification
     */
    public function getWalletWithVerifiedBalance(int $userId): array
    {
        $wallet = $this->getOrCreateWallet($userId);
        $ledgerBalance = $wallet->calculateLedgerBalance();

        if ($wallet->balance_cached !== $ledgerBalance) {
            Log::warning('Sika wallet balance mismatch detected', [
                'user_id' => $userId,
                'cached' => $wallet->balance_cached,
                'ledger' => $ledgerBalance,
            ]);
            $wallet->balance_cached = $ledgerBalance;
            $wallet->save();
        }

        return [
            'wallet_id' => $wallet->id,
            'balance' => $wallet->balance_cached,
            'status' => $wallet->status,
            'created_at' => $wallet->created_at,
        ];
    }

    /**
     * Purchase coins with a pack
     */
    public function purchasePack(
        int $userId,
        int $packId,
        string $idempotencyKey
    ): array {
        $existingEntry = SikaLedgerEntry::findByIdempotencyKey($idempotencyKey);
        if ($existingEntry) {
            return $this->buildPurchaseResponse($existingEntry);
        }

        $pack = SikaPack::find($packId);
        if (!$pack) {
            throw SikaWalletException::packNotFound();
        }
        if (!$pack->is_active) {
            throw SikaWalletException::packInactive();
        }

        $wallet = $this->getOrCreateWallet($userId);
        $this->validateWalletCanTransact($wallet);

        // Get user's phone number for Priority Bank account matching
        $user = \App\Models\User::find($userId);
        $userPhone = $user?->phone;

        return DB::transaction(function () use ($userId, $wallet, $pack, $idempotencyKey, $userPhone) {
            $pbgResult = $this->pbgService->debitWallet(
                $userId,
                (float) $pack->price_ghs,
                $idempotencyKey,
                [
                    'description' => "Purchase {$pack->name} - {$pack->total_coins} Sika Coins",
                    'pack_id' => $pack->id,
                    'pack_name' => $pack->name,
                ],
                $userPhone // Pass user's phone for Priority Bank account matching
            );

            $wallet->lockForUpdate();
            $wallet->refresh();

            $totalCoins = $pack->total_coins;
            $newBalance = $wallet->balance_cached + $totalCoins;

            $entry = SikaLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'type' => SikaLedgerEntry::TYPE_PURCHASE_CREDIT,
                'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
                'coins' => $totalCoins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'reference_type' => SikaLedgerEntry::REFERENCE_TYPE_PBG_DEBIT,
                'reference_id' => $pbgResult['transaction_id'],
                'idempotency_key' => $idempotencyKey,
                'balance_after' => $newBalance,
                'meta' => [
                    'pack_id' => $pack->id,
                    'pack_name' => $pack->name,
                    'price_ghs' => $pack->price_ghs,
                    'base_coins' => $pack->coins,
                    'bonus_coins' => $pack->bonus_coins,
                    'pbg_reference' => $pbgResult['reference'],
                ],
            ]);

            $wallet->balance_cached = $newBalance;
            $wallet->save();

            Log::info('Sika coins purchased', [
                'user_id' => $userId,
                'pack_id' => $pack->id,
                'coins' => $totalCoins,
                'price_ghs' => $pack->price_ghs,
                'new_balance' => $newBalance,
            ]);

            return $this->buildPurchaseResponse($entry);
        });
    }

    /**
     * Transfer coins to another user
     */
    public function transfer(
        int $fromUserId,
        int $toUserId,
        int $coins,
        string $idempotencyKey,
        ?string $note = null
    ): array {
        if ($fromUserId === $toUserId) {
            throw SikaWalletException::selfTransfer();
        }

        if ($coins <= 0) {
            throw SikaWalletException::invalidAmount();
        }

        $existingEntry = SikaLedgerEntry::findByIdempotencyKey($idempotencyKey . '_out');
        if ($existingEntry) {
            return $this->buildTransferResponse($existingEntry);
        }

        $fromWallet = $this->getOrCreateWallet($fromUserId);
        $toWallet = $this->getOrCreateWallet($toUserId);

        $this->validateWalletCanTransact($fromWallet);
        $this->validateWalletCanTransact($toWallet);

        return DB::transaction(function () use ($fromWallet, $toWallet, $coins, $idempotencyKey, $note, $fromUserId, $toUserId) {
            $fromWallet->lockForUpdate();
            $fromWallet->refresh();

            if ($fromWallet->balance_cached < $coins) {
                throw SikaWalletException::insufficientBalance($coins, $fromWallet->balance_cached);
            }

            $toWallet->lockForUpdate();
            $toWallet->refresh();

            $groupId = Str::uuid()->toString();
            $fromNewBalance = $fromWallet->balance_cached - $coins;
            $toNewBalance = $toWallet->balance_cached + $coins;

            $outEntry = SikaLedgerEntry::create([
                'wallet_id' => $fromWallet->id,
                'type' => SikaLedgerEntry::TYPE_TRANSFER_OUT,
                'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'idempotency_key' => $idempotencyKey . '_out',
                'balance_after' => $fromNewBalance,
                'meta' => [
                    'to_user_id' => $toUserId,
                    'note' => $note,
                ],
            ]);

            SikaLedgerEntry::create([
                'wallet_id' => $toWallet->id,
                'type' => SikaLedgerEntry::TYPE_TRANSFER_IN,
                'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'idempotency_key' => $idempotencyKey . '_in',
                'balance_after' => $toNewBalance,
                'meta' => [
                    'from_user_id' => $fromUserId,
                    'note' => $note,
                ],
            ]);

            $fromWallet->balance_cached = $fromNewBalance;
            $fromWallet->save();

            $toWallet->balance_cached = $toNewBalance;
            $toWallet->save();

            Log::info('Sika coins transferred', [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'coins' => $coins,
                'group_id' => $groupId,
            ]);

            // Send notification to recipient (outside transaction for performance)
            try {
                $this->notificationService?->notifyTransfer(
                    fromUserId: $fromUserId,
                    toUserId: $toUserId,
                    coins: $coins,
                    note: $note,
                    transactionId: $groupId
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send transfer notification', [
                    'error' => $e->getMessage(),
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                ]);
            }

            return $this->buildTransferResponse($outEntry);
        });
    }

    /**
     * Gift coins (similar to transfer but with gift context)
     */
    public function gift(
        int $fromUserId,
        int $toUserId,
        int $coins,
        string $idempotencyKey,
        ?int $postId = null,
        ?int $messageId = null,
        ?string $note = null
    ): array {
        if ($fromUserId === $toUserId) {
            throw SikaWalletException::selfTransfer();
        }

        if ($coins <= 0) {
            throw SikaWalletException::invalidAmount();
        }

        $existingEntry = SikaLedgerEntry::findByIdempotencyKey($idempotencyKey . '_out');
        if ($existingEntry) {
            return $this->buildGiftResponse($existingEntry);
        }

        $fromWallet = $this->getOrCreateWallet($fromUserId);
        $toWallet = $this->getOrCreateWallet($toUserId);

        $this->validateWalletCanTransact($fromWallet);
        $this->validateWalletCanTransact($toWallet);

        return DB::transaction(function () use ($fromWallet, $toWallet, $coins, $idempotencyKey, $postId, $messageId, $note, $fromUserId, $toUserId) {
            $fromWallet->lockForUpdate();
            $fromWallet->refresh();

            if ($fromWallet->balance_cached < $coins) {
                throw SikaWalletException::insufficientBalance($coins, $fromWallet->balance_cached);
            }

            $toWallet->lockForUpdate();
            $toWallet->refresh();

            $groupId = Str::uuid()->toString();
            $fromNewBalance = $fromWallet->balance_cached - $coins;
            $toNewBalance = $toWallet->balance_cached + $coins;

            $referenceType = $postId ? SikaLedgerEntry::REFERENCE_TYPE_WORLDFEED_POST : 
                            ($messageId ? SikaLedgerEntry::REFERENCE_TYPE_CHAT_MESSAGE : null);
            $referenceId = $postId ?? $messageId;

            $outEntry = SikaLedgerEntry::create([
                'wallet_id' => $fromWallet->id,
                'type' => SikaLedgerEntry::TYPE_GIFT_OUT,
                'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId ? (string) $referenceId : null,
                'idempotency_key' => $idempotencyKey . '_out',
                'balance_after' => $fromNewBalance,
                'meta' => [
                    'to_user_id' => $toUserId,
                    'post_id' => $postId,
                    'message_id' => $messageId,
                    'note' => $note,
                ],
            ]);

            SikaLedgerEntry::create([
                'wallet_id' => $toWallet->id,
                'type' => SikaLedgerEntry::TYPE_GIFT_IN,
                'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId ? (string) $referenceId : null,
                'idempotency_key' => $idempotencyKey . '_in',
                'balance_after' => $toNewBalance,
                'meta' => [
                    'from_user_id' => $fromUserId,
                    'post_id' => $postId,
                    'message_id' => $messageId,
                    'note' => $note,
                ],
            ]);

            $fromWallet->balance_cached = $fromNewBalance;
            $fromWallet->save();

            $toWallet->balance_cached = $toNewBalance;
            $toWallet->save();

            Log::info('Sika coins gifted', [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'coins' => $coins,
                'post_id' => $postId,
                'message_id' => $messageId,
                'group_id' => $groupId,
            ]);

            // Send notification to recipient (outside transaction for performance)
            try {
                $this->notificationService?->notifyGift(
                    fromUserId: $fromUserId,
                    toUserId: $toUserId,
                    coins: $coins,
                    note: $note,
                    postId: $postId,
                    messageId: $messageId,
                    transactionId: $groupId
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send gift notification', [
                    'error' => $e->getMessage(),
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                ]);
            }

            return $this->buildGiftResponse($outEntry);
        });
    }

    /**
     * Request cashout (feature-flagged)
     */
    public function requestCashout(
        int $userId,
        int $coins,
        string $idempotencyKey
    ): array {
        if (!config('sika.features.cashout_enabled', false)) {
            throw SikaWalletException::cashoutDisabled();
        }

        if ($coins <= 0) {
            throw SikaWalletException::invalidAmount();
        }

        $existingRequest = SikaCashoutRequest::findByIdempotencyKey($idempotencyKey);
        if ($existingRequest) {
            return $this->buildCashoutRequestResponse($existingRequest);
        }

        $tier = SikaCashoutTier::findTierForCoins($coins);
        if (!$tier) {
            throw new SikaWalletException('No cashout tier available for this amount');
        }

        $wallet = $this->getOrCreateWallet($userId);
        $this->validateWalletCanTransact($wallet);

        if ($wallet->balance_cached < $coins) {
            throw SikaWalletException::insufficientBalance($coins, $wallet->balance_cached);
        }

        $this->validateCashoutLimits($userId, $coins, $tier);

        $calculation = $tier->calculateNetGhs($coins);
        $holdDays = $tier->hold_days;
        $availableAt = $holdDays > 0 ? now()->addDays($holdDays) : null;

        return DB::transaction(function () use ($userId, $wallet, $tier, $coins, $calculation, $idempotencyKey, $availableAt) {
            $wallet->lockForUpdate();
            $wallet->refresh();

            if ($wallet->balance_cached < $coins) {
                throw SikaWalletException::insufficientBalance($coins, $wallet->balance_cached);
            }

            $newBalance = $wallet->balance_cached - $coins;

            $entry = SikaLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'type' => SikaLedgerEntry::TYPE_CASHOUT_DEBIT,
                'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey . '_debit',
                'balance_after' => $newBalance,
                'meta' => [
                    'tier_id' => $tier->id,
                    'ghs_amount' => $calculation['net_ghs'],
                ],
            ]);

            $wallet->balance_cached = $newBalance;
            $wallet->save();

            $request = SikaCashoutRequest::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'tier_id' => $tier->id,
                'coins_requested' => $coins,
                'ghs_to_credit' => $calculation['gross_ghs'],
                'fee_applied' => $calculation['fee'],
                'net_ghs' => $calculation['net_ghs'],
                'status' => SikaCashoutRequest::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
                'available_at' => $availableAt,
                'meta' => [
                    'ledger_entry_id' => $entry->id,
                    'tier_name' => $tier->name,
                    'rate_per_million' => $tier->ghs_per_million_coins,
                ],
            ]);

            Log::info('Sika cashout requested', [
                'user_id' => $userId,
                'coins' => $coins,
                'net_ghs' => $calculation['net_ghs'],
                'request_id' => $request->id,
            ]);

            return $this->buildCashoutRequestResponse($request);
        });
    }

    /**
     * Admin: Approve cashout request
     */
    public function approveCashout(int $requestId, int $adminUserId): array
    {
        $request = SikaCashoutRequest::findOrFail($requestId);

        if (!$request->canBeApproved()) {
            throw new SikaWalletException('Cashout request cannot be approved');
        }

        $request->status = SikaCashoutRequest::STATUS_APPROVED;
        $request->approved_by = $adminUserId;
        $request->approved_at = now();
        $request->save();

        SikaLedgerEntry::where('idempotency_key', $request->idempotency_key . '_debit')
            ->update(['status' => SikaLedgerEntry::STATUS_POSTED]);

        Log::info('Sika cashout approved', [
            'request_id' => $requestId,
            'admin_user_id' => $adminUserId,
        ]);

        return $this->buildCashoutRequestResponse($request->fresh());
    }

    /**
     * Admin: Reject cashout request (refunds coins)
     */
    public function rejectCashout(int $requestId, int $adminUserId, string $reason): array
    {
        $request = SikaCashoutRequest::findOrFail($requestId);

        if (!$request->canBeRejected()) {
            throw new SikaWalletException('Cashout request cannot be rejected');
        }

        return DB::transaction(function () use ($request, $adminUserId, $reason) {
            $wallet = $request->wallet;
            $wallet->lockForUpdate();
            $wallet->refresh();

            SikaLedgerEntry::where('idempotency_key', $request->idempotency_key . '_debit')
                ->update(['status' => SikaLedgerEntry::STATUS_REVERSED]);

            $newBalance = $wallet->balance_cached + $request->coins_requested;

            SikaLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'type' => SikaLedgerEntry::TYPE_REFUND,
                'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
                'coins' => $request->coins_requested,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'idempotency_key' => $request->idempotency_key . '_refund',
                'balance_after' => $newBalance,
                'meta' => [
                    'cashout_request_id' => $request->id,
                    'reason' => $reason,
                ],
            ]);

            $wallet->balance_cached = $newBalance;
            $wallet->save();

            $request->status = SikaCashoutRequest::STATUS_REJECTED;
            $request->approved_by = $adminUserId;
            $request->rejection_reason = $reason;
            $request->save();

            Log::info('Sika cashout rejected', [
                'request_id' => $request->id,
                'admin_user_id' => $adminUserId,
                'reason' => $reason,
            ]);

            return $this->buildCashoutRequestResponse($request->fresh());
        });
    }

    /**
     * Admin: Process approved cashout (credit PBG wallet)
     */
    public function processCashout(int $requestId, int $adminUserId): array
    {
        $request = SikaCashoutRequest::findOrFail($requestId);

        if (!$request->canBeProcessed()) {
            throw new SikaWalletException('Cashout request cannot be processed');
        }

        if ($request->available_at && $request->available_at->isFuture()) {
            throw new SikaWalletException('Cashout is still in hold period');
        }

        $request->status = SikaCashoutRequest::STATUS_PROCESSING;
        $request->processed_by = $adminUserId;
        $request->save();

        try {
            // Get user's phone for Priority Bank account matching
            $user = \App\Models\User::find($request->user_id);
            $userPhone = $user?->phone;

            $pbgResult = $this->pbgService->creditWallet(
                $request->user_id,
                (float) $request->net_ghs,
                $request->idempotency_key . '_pbg_credit',
                [
                    'description' => 'Sika Coins Cashout',
                    'cashout_request_id' => $request->id,
                ],
                $userPhone // Pass user's phone for Priority Bank account matching
            );

            $request->status = SikaCashoutRequest::STATUS_PAID;
            $request->pbg_credit_reference = $pbgResult['transaction_id'];
            $request->paid_at = now();
            $request->save();

            Log::info('Sika cashout processed', [
                'request_id' => $requestId,
                'pbg_transaction_id' => $pbgResult['transaction_id'],
            ]);

            return $this->buildCashoutRequestResponse($request->fresh());

        } catch (PbgApiException $e) {
            $request->status = SikaCashoutRequest::STATUS_FAILED;
            $request->meta = array_merge($request->meta ?? [], [
                'pbg_error' => $e->getMessage(),
            ]);
            $request->save();

            Log::error('Sika cashout PBG credit failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Pay merchant with coins (feature-flagged)
     */
    public function payMerchant(
        int $buyerUserId,
        int $merchantId,
        int $coins,
        string $idempotencyKey,
        ?string $description = null,
        ?array $items = null
    ): array {
        if (!config('sika.features.merchant_pay_enabled', false)) {
            throw SikaWalletException::merchantPayDisabled();
        }

        if ($coins <= 0) {
            throw SikaWalletException::invalidAmount();
        }

        $existingOrder = SikaMerchantOrder::findByIdempotencyKey($idempotencyKey);
        if ($existingOrder) {
            return $this->buildMerchantOrderResponse($existingOrder);
        }

        $merchant = SikaMerchant::findOrFail($merchantId);
        if (!$merchant->canReceivePayments()) {
            throw SikaWalletException::merchantNotActive();
        }

        $buyerWallet = $this->getOrCreateWallet($buyerUserId);
        $merchantWallet = $this->getOrCreateWallet($merchant->owner_user_id);

        $this->validateWalletCanTransact($buyerWallet);

        if ($buyerWallet->balance_cached < $coins) {
            throw SikaWalletException::insufficientBalance($coins, $buyerWallet->balance_cached);
        }

        $commissionCoins = $merchant->calculateCommission($coins);
        $netCoins = $coins - $commissionCoins;

        return DB::transaction(function () use (
            $buyerWallet, $merchantWallet, $merchant, $coins, $commissionCoins, $netCoins,
            $idempotencyKey, $description, $items, $buyerUserId
        ) {
            $buyerWallet->lockForUpdate();
            $buyerWallet->refresh();

            if ($buyerWallet->balance_cached < $coins) {
                throw SikaWalletException::insufficientBalance($coins, $buyerWallet->balance_cached);
            }

            $merchantWallet->lockForUpdate();
            $merchantWallet->refresh();

            $groupId = Str::uuid()->toString();
            $buyerNewBalance = $buyerWallet->balance_cached - $coins;
            $merchantNewBalance = $merchantWallet->balance_cached + $netCoins;

            $order = SikaMerchantOrder::create([
                'merchant_id' => $merchant->id,
                'buyer_user_id' => $buyerUserId,
                'coins_amount' => $coins,
                'commission_coins' => $commissionCoins,
                'net_coins' => $netCoins,
                'status' => SikaMerchantOrder::STATUS_PAID,
                'idempotency_key' => $idempotencyKey,
                'ledger_group_id' => $groupId,
                'description' => $description,
                'items' => $items,
                'paid_at' => now(),
            ]);

            SikaLedgerEntry::create([
                'wallet_id' => $buyerWallet->id,
                'type' => SikaLedgerEntry::TYPE_MERCHANT_PAY,
                'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'reference_type' => SikaLedgerEntry::REFERENCE_TYPE_MERCHANT_ORDER,
                'reference_id' => (string) $order->id,
                'idempotency_key' => $idempotencyKey . '_pay',
                'balance_after' => $buyerNewBalance,
                'meta' => [
                    'merchant_id' => $merchant->id,
                    'merchant_name' => $merchant->business_name,
                    'order_reference' => $order->order_reference,
                ],
            ]);

            SikaLedgerEntry::create([
                'wallet_id' => $merchantWallet->id,
                'type' => SikaLedgerEntry::TYPE_MERCHANT_RECEIVE,
                'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
                'coins' => $netCoins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'group_id' => $groupId,
                'reference_type' => SikaLedgerEntry::REFERENCE_TYPE_MERCHANT_ORDER,
                'reference_id' => (string) $order->id,
                'idempotency_key' => $idempotencyKey . '_receive',
                'balance_after' => $merchantNewBalance,
                'meta' => [
                    'buyer_user_id' => $buyerUserId,
                    'gross_coins' => $coins,
                    'commission_coins' => $commissionCoins,
                    'order_reference' => $order->order_reference,
                ],
            ]);

            $buyerWallet->balance_cached = $buyerNewBalance;
            $buyerWallet->save();

            $merchantWallet->balance_cached = $merchantNewBalance;
            $merchantWallet->save();

            Log::info('Sika merchant payment', [
                'buyer_user_id' => $buyerUserId,
                'merchant_id' => $merchant->id,
                'coins' => $coins,
                'commission' => $commissionCoins,
                'net' => $netCoins,
                'order_id' => $order->id,
            ]);

            return $this->buildMerchantOrderResponse($order);
        });
    }

    /**
     * Admin: Adjust wallet balance
     */
    public function adminAdjust(
        int $userId,
        int $coins,
        string $direction,
        string $idempotencyKey,
        int $adminUserId,
        string $reason
    ): array {
        $existingEntry = SikaLedgerEntry::findByIdempotencyKey($idempotencyKey);
        if ($existingEntry) {
            return [
                'entry_id' => $existingEntry->id,
                'coins' => $existingEntry->coins,
                'direction' => $existingEntry->direction,
                'status' => 'duplicate',
            ];
        }

        $wallet = $this->getOrCreateWallet($userId);

        return DB::transaction(function () use ($wallet, $coins, $direction, $idempotencyKey, $adminUserId, $reason) {
            $wallet->lockForUpdate();
            $wallet->refresh();

            $isCredit = strtoupper($direction) === SikaLedgerEntry::DIRECTION_CREDIT;

            if (!$isCredit && $wallet->balance_cached < $coins) {
                throw SikaWalletException::insufficientBalance($coins, $wallet->balance_cached);
            }

            $newBalance = $isCredit 
                ? $wallet->balance_cached + $coins 
                : $wallet->balance_cached - $coins;

            $entry = SikaLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'type' => SikaLedgerEntry::TYPE_ADMIN_ADJUST,
                'direction' => $isCredit ? SikaLedgerEntry::DIRECTION_CREDIT : SikaLedgerEntry::DIRECTION_DEBIT,
                'coins' => $coins,
                'status' => SikaLedgerEntry::STATUS_POSTED,
                'idempotency_key' => $idempotencyKey,
                'balance_after' => $newBalance,
                'meta' => [
                    'admin_user_id' => $adminUserId,
                    'reason' => $reason,
                ],
            ]);

            $wallet->balance_cached = $newBalance;
            $wallet->save();

            Log::info('Sika admin adjustment', [
                'user_id' => $wallet->user_id,
                'admin_user_id' => $adminUserId,
                'coins' => $coins,
                'direction' => $direction,
                'reason' => $reason,
            ]);

            return [
                'entry_id' => $entry->id,
                'coins' => $entry->coins,
                'direction' => $entry->direction,
                'new_balance' => $newBalance,
                'status' => 'success',
            ];
        });
    }

    /**
     * Get transaction history
     */
    public function getTransactions(
        int $userId,
        int $perPage = 20,
        ?string $type = null,
        ?string $direction = null
    ): array {
        $wallet = $this->getOrCreateWallet($userId);

        $query = $wallet->ledgerEntries()
            ->where('status', SikaLedgerEntry::STATUS_POSTED)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        if ($direction) {
            $query->where('direction', $direction);
        }

        $entries = $query->paginate($perPage);

        return [
            'data' => $entries->items(),
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ];
    }

    private function validateWalletCanTransact(SikaWallet $wallet): void
    {
        if ($wallet->isSuspended()) {
            throw SikaWalletException::walletSuspended();
        }

        if ($wallet->isFrozen()) {
            throw SikaWalletException::walletFrozen();
        }
    }

    private function validateCashoutLimits(int $userId, int $coins, SikaCashoutTier $tier): void
    {
        if ($tier->daily_limit !== null) {
            $dailyTotal = SikaCashoutRequest::forUser($userId)
                ->whereIn('status', [
                    SikaCashoutRequest::STATUS_PENDING,
                    SikaCashoutRequest::STATUS_APPROVED,
                    SikaCashoutRequest::STATUS_PROCESSING,
                    SikaCashoutRequest::STATUS_PAID,
                ])
                ->whereDate('created_at', today())
                ->sum('coins_requested');

            if (($dailyTotal + $coins) > $tier->daily_limit) {
                throw SikaWalletException::cashoutLimitExceeded('daily');
            }
        }

        if ($tier->weekly_limit !== null) {
            $weeklyTotal = SikaCashoutRequest::forUser($userId)
                ->whereIn('status', [
                    SikaCashoutRequest::STATUS_PENDING,
                    SikaCashoutRequest::STATUS_APPROVED,
                    SikaCashoutRequest::STATUS_PROCESSING,
                    SikaCashoutRequest::STATUS_PAID,
                ])
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('coins_requested');

            if (($weeklyTotal + $coins) > $tier->weekly_limit) {
                throw SikaWalletException::cashoutLimitExceeded('weekly');
            }
        }

        if ($tier->monthly_limit !== null) {
            $monthlyTotal = SikaCashoutRequest::forUser($userId)
                ->whereIn('status', [
                    SikaCashoutRequest::STATUS_PENDING,
                    SikaCashoutRequest::STATUS_APPROVED,
                    SikaCashoutRequest::STATUS_PROCESSING,
                    SikaCashoutRequest::STATUS_PAID,
                ])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('coins_requested');

            if (($monthlyTotal + $coins) > $tier->monthly_limit) {
                throw SikaWalletException::cashoutLimitExceeded('monthly');
            }
        }
    }

    private function buildPurchaseResponse(SikaLedgerEntry $entry): array
    {
        return [
            'success' => true,
            'entry_id' => $entry->id,
            'coins_credited' => $entry->coins,
            'new_balance' => $entry->balance_after,
            'pack_id' => $entry->getMetaValue('pack_id'),
            'pack_name' => $entry->getMetaValue('pack_name'),
            'price_ghs' => $entry->getMetaValue('price_ghs'),
            'pbg_reference' => $entry->reference_id,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    private function buildTransferResponse(SikaLedgerEntry $entry): array
    {
        return [
            'success' => true,
            'entry_id' => $entry->id,
            'coins' => $entry->coins,
            'new_balance' => $entry->balance_after,
            'to_user_id' => $entry->getMetaValue('to_user_id'),
            'group_id' => $entry->group_id,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    private function buildGiftResponse(SikaLedgerEntry $entry): array
    {
        return [
            'success' => true,
            'entry_id' => $entry->id,
            'coins' => $entry->coins,
            'new_balance' => $entry->balance_after,
            'to_user_id' => $entry->getMetaValue('to_user_id'),
            'post_id' => $entry->getMetaValue('post_id'),
            'message_id' => $entry->getMetaValue('message_id'),
            'group_id' => $entry->group_id,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    private function buildCashoutRequestResponse(SikaCashoutRequest $request): array
    {
        return [
            'request_id' => $request->id,
            'coins_requested' => $request->coins_requested,
            'ghs_to_credit' => $request->ghs_to_credit,
            'fee_applied' => $request->fee_applied,
            'net_ghs' => $request->net_ghs,
            'status' => $request->status,
            'available_at' => $request->available_at?->toIso8601String(),
            'created_at' => $request->created_at->toIso8601String(),
        ];
    }

    private function buildMerchantOrderResponse(SikaMerchantOrder $order): array
    {
        return [
            'order_id' => $order->id,
            'order_reference' => $order->order_reference,
            'merchant_id' => $order->merchant_id,
            'coins_amount' => $order->coins_amount,
            'commission_coins' => $order->commission_coins,
            'net_coins' => $order->net_coins,
            'status' => $order->status,
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
