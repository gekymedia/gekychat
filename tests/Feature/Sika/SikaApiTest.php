<?php

namespace Tests\Feature\Sika;

use App\Models\Sika\SikaCashoutRequest;
use App\Models\Sika\SikaCashoutTier;
use App\Models\Sika\SikaLedgerEntry;
use App\Models\Sika\SikaPack;
use App\Models\Sika\SikaWallet;
use App\Models\User;
use App\Services\Sika\PriorityBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SikaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private $pbgServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->pbgServiceMock = Mockery::mock(PriorityBankService::class);
        $this->app->instance(PriorityBankService::class, $this->pbgServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_lists_active_packs(): void
    {
        SikaPack::create([
            'name' => 'Starter',
            'price_ghs' => 5.00,
            'coins' => 500,
            'bonus_coins' => 50,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        SikaPack::create([
            'name' => 'Pro',
            'price_ghs' => 20.00,
            'coins' => 2500,
            'bonus_coins' => 500,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        SikaPack::create([
            'name' => 'Inactive',
            'price_ghs' => 100.00,
            'coins' => 10000,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sika/packs');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Starter')
            ->assertJsonPath('data.0.total_coins', 550)
            ->assertJsonPath('data.1.name', 'Pro');
    }

    /** @test */
    public function it_returns_wallet_info(): void
    {
        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sika/wallet');

        $response->assertOk()
            ->assertJsonPath('data.wallet.balance', 5000)
            ->assertJsonPath('data.wallet.status', 'active');
    }

    /** @test */
    public function it_creates_wallet_if_not_exists(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sika/wallet');

        $response->assertOk()
            ->assertJsonPath('data.wallet.balance', 0);

        $this->assertDatabaseHas('sika_wallets', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_purchases_coins(): void
    {
        $pack = SikaPack::create([
            'name' => 'Starter',
            'price_ghs' => 10.00,
            'coins' => 1000,
            'bonus_coins' => 100,
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

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/purchase/initiate', [
                'pack_id' => $pack->id,
                'idempotency_key' => 'purchase-' . uniqid(),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coins_credited', 1100);
    }

    /** @test */
    public function it_transfers_coins(): void
    {
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/transfer', [
                'to_user_id' => $receiver->id,
                'coins' => 1000,
                'note' => 'Thanks!',
                'idempotency_key' => 'transfer-' . uniqid(),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coins', 1000)
            ->assertJsonPath('data.new_balance', 4000);
    }

    /** @test */
    public function it_rejects_transfer_to_self(): void
    {
        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/transfer', [
                'to_user_id' => $this->user->id,
                'coins' => 1000,
                'idempotency_key' => 'transfer-' . uniqid(),
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function it_rejects_transfer_with_insufficient_balance(): void
    {
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 500,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/transfer', [
                'to_user_id' => $receiver->id,
                'coins' => 1000,
                'idempotency_key' => 'transfer-' . uniqid(),
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function it_gifts_coins(): void
    {
        $receiver = User::factory()->create();

        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/gift', [
                'to_user_id' => $receiver->id,
                'coins' => 500,
                'note' => 'Great content!',
                'idempotency_key' => 'gift-' . uniqid(),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coins', 500);
    }

    /** @test */
    public function it_returns_transaction_history(): void
    {
        $wallet = SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 5000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_PURCHASE_CREDIT,
            'direction' => SikaLedgerEntry::DIRECTION_CREDIT,
            'coins' => 5000,
            'status' => SikaLedgerEntry::STATUS_POSTED,
            'idempotency_key' => 'entry-1',
            'balance_after' => 5000,
        ]);

        SikaLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => SikaLedgerEntry::TYPE_GIFT_OUT,
            'direction' => SikaLedgerEntry::DIRECTION_DEBIT,
            'coins' => 500,
            'status' => SikaLedgerEntry::STATUS_POSTED,
            'idempotency_key' => 'entry-2',
            'balance_after' => 4500,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sika/transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', SikaLedgerEntry::TYPE_GIFT_OUT);
    }

    /** @test */
    public function it_rejects_cashout_when_disabled(): void
    {
        Config::set('sika.features.cashout_enabled', false);

        SikaWallet::create([
            'user_id' => $this->user->id,
            'balance_cached' => 1000000,
            'status' => SikaWallet::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/cashout/request', [
                'coins' => 1000000,
                'idempotency_key' => 'cashout-' . uniqid(),
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'CASHOUT_ERROR');
    }

    /** @test */
    public function it_validates_purchase_request(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/purchase/initiate', [
                'pack_id' => 99999,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_transfer_request(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sika/transfer', [
                'coins' => 0,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/sika/wallet');

        $response->assertStatus(401);
    }
}
