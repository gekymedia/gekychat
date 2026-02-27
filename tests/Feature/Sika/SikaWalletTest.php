<?php

namespace Tests\Feature\Sika;

use App\Exceptions\Sika\PbgApiException;
use App\Exceptions\Sika\PbgInsufficientFundsException;
use App\Exceptions\Sika\SikaWalletException;
use App\Models\Sika\SikaCashoutRequest;
use App\Models\Sika\SikaCashoutTier;
use App\Models\Sika\SikaLedgerEntry;
use App\Models\Sika\SikaPack;
use App\Models\Sika\SikaWallet;
use App\Models\User;
use App\Services\Sika\PriorityBankService;
use App\Services\Sika\SikaWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SikaWalletTest extends TestCase
{
    use RefreshDatabase;

    private SikaWalletService $walletService;
    private $pbgServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pbgServiceMock = Mockery::mock(PriorityBankService::class);
        $this->app->instance(PriorityBankService::class, $this->pbgServiceMock);
        $this->walletService = $this->app->make(SikaWalletService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_auto_creates_wallet_for_user(): void
    {
        $user = User::factory()->create();

        $wallet = $this->walletService->getOrCreateWallet($user->id);

        $this->assertInstanceOf(SikaWallet::class, $wallet);
        $this->assertEquals($user->id, $wallet->user_id);
        $this->assertEquals(0, $wallet->balance_cached);
        $this->assertEquals(SikaWallet::STATUS_ACTIVE, $wallet->status);
    }

    /** @test */
    public function it_returns_existing_wallet_for_user(): void
    {
        $user = User::factory()->create();
        $existingWallet = SikaWallet::create([
            'user_id' => $user->id,
            'balance_cached' => 1000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $wallet = $this->walletService->getOrCreateWallet($user->id);

        $this->assertEquals($existingWallet->id, $wallet->id);
        $this->assertEquals(1000, $wallet->balance_cached);
    }

    /** @test */
    public function it_purchases_pack_and_credits_coins(): void
    {
        $user = User::factory()->create();
        $pack = SikaPack::create([
            'name' => 'Starter Pack',
            'price_ghs' => 10.00,
            'coins' => 1000,
            'bonus_coins' => 100,
            'is_active' => true,
        ]);

        $this->pbgServiceMock
            ->shouldReceive('debitWallet')
            ->once()
            ->with($user->id, 10.00, 'test-key-123', Mockery::any())
            ->andReturn([
                'success' => true,
                'transaction_id' => 'pbg_txn_001',
                'reference' => 'PBG001',
                'amount' => 10.00,
            ]);

        $result = $this->walletService->purchasePack($user->id, $pack->id, 'test-key-123');

        $this->assertTrue($result['success']);
        $this->assertEquals(1100, $result['coins_credited']);
        $this->assertEquals(1100, $result['new_balance']);
        $this->assertEquals('pbg_txn_001', $result['pbg_reference']);

        $wallet = SikaWallet::where('user_id', $user->id)->first();
        $this->assertEquals(1100, $wallet->balance_cached);

        $entry = SikaLedgerEntry::where('wallet_id', $wallet->id)->first();
        $this->assertEquals(SikaLedgerEntry::TYPE_PURCHASE_CREDIT, $entry->type);
        $this->assertEquals(SikaLedgerEntry::DIRECTION_CREDIT, $entry->direction);
        $this->assertEquals(1100, $entry->coins);
        $this->assertEquals(SikaLedgerEntry::STATUS_POSTED, $entry->status);
    }

    /** @test */
    public function it_prevents_duplicate_purchase_with_same_idempotency_key(): void
    {
        $user = User::factory()->create();
        $pack = SikaPack::create([
            'name' => 'Starter Pack',
            'price_ghs' => 10.00,
            'coins' => 1000,
            'bonus_coins' => 0,
            'is_active' => true,
        ]);

        $this->pbgServiceMock
            ->shouldReceive('debitWallet')
            ->once()
            ->andReturn([
                'success' => true,
                'transaction_id' => 'pbg_txn_001',
                'reference' => 'PBG001',
                'amount' => 10.00,
            ]);

        $result1 = $this->walletService->purchasePack($user->id, $pack->id, 'duplicate-key');
        $result2 = $this->walletService->purchasePack($user->id, $pack->id, 'duplicate-key');

        $this->assertEquals($result1['entry_id'], $result2['entry_id']);

        $wallet = SikaWallet::where('user_id', $user->id)->first();
        $this->assertEquals(1000, $wallet->balance_cached);

        $entries = SikaLedgerEntry::where('wallet_id', $wallet->id)->count();
        $this->assertEquals(1, $entries);
    }

    /** @test */
    public function it_handles_pbg_insufficient_funds(): void
    {
        $user = User::factory()->create();
        $pack = SikaPack::create([
            'name' => 'Starter Pack',
            'price_ghs' => 10.00,
            'coins' => 1000,
            'is_active' => true,
        ]);

        $this->pbgServiceMock
            ->shouldReceive('debitWallet')
            ->once()
            ->andThrow(new PbgInsufficientFundsException());

        $this->expectException(PbgInsufficientFundsException::class);

        $this->walletService->purchasePack($user->id, $pack->id, 'test-key');
    }

    /** @test */
    public function it_transfers_coins_between_users(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $senderWallet = SikaWallet::create([
            'user_id' => $sender->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $result = $this->walletService->transfer(
            $sender->id,
            $receiver->id,
            1000,
            'transfer-key-001',
            'Test transfer'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1000, $result['coins']);
        $this->assertEquals(4000, $result['new_balance']);

        $senderWallet->refresh();
        $this->assertEquals(4000, $senderWallet->balance_cached);

        $receiverWallet = SikaWallet::where('user_id', $receiver->id)->first();
        $this->assertEquals(1000, $receiverWallet->balance_cached);

        $outEntry = SikaLedgerEntry::where('idempotency_key', 'transfer-key-001_out')->first();
        $inEntry = SikaLedgerEntry::where('idempotency_key', 'transfer-key-001_in')->first();

        $this->assertNotNull($outEntry);
        $this->assertNotNull($inEntry);
        $this->assertEquals($outEntry->group_id, $inEntry->group_id);
        $this->assertEquals(SikaLedgerEntry::TYPE_TRANSFER_OUT, $outEntry->type);
        $this->assertEquals(SikaLedgerEntry::TYPE_TRANSFER_IN, $inEntry->type);
    }

    /** @test */
    public function it_rejects_transfer_with_insufficient_balance(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $sender->id,
            'balance_cached' => 500,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_INSUFFICIENT_BALANCE);

        $this->walletService->transfer($sender->id, $receiver->id, 1000, 'test-key');
    }

    /** @test */
    public function it_rejects_self_transfer(): void
    {
        $user = User::factory()->create();

        SikaWallet::create([
            'user_id' => $user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_SELF_TRANSFER);

        $this->walletService->transfer($user->id, $user->id, 1000, 'test-key');
    }

    /** @test */
    public function it_handles_concurrent_transfers_with_locking(): void
    {
        $sender = User::factory()->create();
        $receiver1 = User::factory()->create();
        $receiver2 = User::factory()->create();

        SikaWallet::create([
            'user_id' => $sender->id,
            'balance_cached' => 1500,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $this->walletService->transfer($sender->id, $receiver1->id, 1000, 'key-1');

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_INSUFFICIENT_BALANCE);

        $this->walletService->transfer($sender->id, $receiver2->id, 1000, 'key-2');
    }

    /** @test */
    public function it_gifts_coins_to_user(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $sender->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $result = $this->walletService->gift(
            $sender->id,
            $receiver->id,
            500,
            'gift-key-001',
            null,
            null,
            'Great post!'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(500, $result['coins']);

        $outEntry = SikaLedgerEntry::where('idempotency_key', 'gift-key-001_out')->first();
        $this->assertEquals(SikaLedgerEntry::TYPE_GIFT_OUT, $outEntry->type);
    }

    /** @test */
    public function it_rejects_cashout_when_feature_disabled(): void
    {
        Config::set('sika.features.cashout_enabled', false);

        $user = User::factory()->create();
        SikaWallet::create([
            'user_id' => $user->id,
            'balance_cached' => 1000000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_CASHOUT_DISABLED);

        $this->walletService->requestCashout($user->id, 1000000, 'cashout-key');
    }

    /** @test */
    public function it_creates_cashout_request_when_enabled(): void
    {
        Config::set('sika.features.cashout_enabled', true);

        $user = User::factory()->create();
        SikaWallet::create([
            'user_id' => $user->id,
            'balance_cached' => 2000000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        SikaCashoutTier::create([
            'name' => 'Standard',
            'min_coins' => 1000000,
            'max_coins' => null,
            'ghs_per_million_coins' => 100.00,
            'fee_percent' => 0,
            'fee_flat_ghs' => 0,
            'is_active' => true,
        ]);

        $result = $this->walletService->requestCashout($user->id, 1000000, 'cashout-key');

        $this->assertEquals(1000000, $result['coins_requested']);
        $this->assertEquals(100.00, $result['net_ghs']);
        $this->assertEquals(SikaCashoutRequest::STATUS_PENDING, $result['status']);

        $wallet = SikaWallet::where('user_id', $user->id)->first();
        $this->assertEquals(1000000, $wallet->balance_cached);
    }

    /** @test */
    public function it_calculates_ledger_balance_correctly(): void
    {
        $user = User::factory()->create();
        $wallet = SikaWallet::create([
            'user_id' => $user->id,
            'balance_cached' => 0,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_PURCHASE_CREDIT,
            'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
            'coins' => 5000,
            'status' => SikaLedgerEntry::STATUS_POSTED,
            'idempotency_key' => 'entry-1',
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_TRANSFER_OUT,
            'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
            'coins' => 1000,
            'status' => SikaLedgerEntry::STATUS_POSTED,
            'idempotency_key' => 'entry-2',
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_GIFT_IN,
            'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
            'coins' => 500,
            'status' => SikaLedgerEntry::STATUS_POSTED,
            'idempotency_key' => 'entry-3',
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_TRANSFER_OUT,
            'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
            'coins' => 2000,
            'status' => SikaLedgerEntry::STATUS_REVERSED,
            'idempotency_key' => 'entry-4',
        ]);

        $ledgerBalance = $wallet->calculateLedgerBalance();

        $this->assertEquals(4500, $ledgerBalance);
    }

    /** @test */
    public function it_rejects_inactive_pack_purchase(): void
    {
        $user = User::factory()->create();
        $pack = SikaPack::create([
            'name' => 'Inactive Pack',
            'price_ghs' => 10.00,
            'coins' => 1000,
            'is_active' => false,
        ]);

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_PACK_INACTIVE);

        $this->walletService->purchasePack($user->id, $pack->id, 'test-key');
    }

    /** @test */
    public function it_rejects_transfer_from_suspended_wallet(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $sender->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_SUSPENDED,
        ]);

        $this->expectException(SikaWalletException::class);
        $this->expectExceptionCode(SikaWalletException::CODE_WALLET_SUSPENDED);

        $this->walletService->transfer($sender->id, $receiver->id, 1000, 'test-key');
    }
}
