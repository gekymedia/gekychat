# Priority Sika Coins System

A virtual in-app currency system for GekyChat integrated with Priority Bank Ghana (PBG).

## Overview

Priority Sika Coins are **NOT real money**. They are virtual tokens used for:
- Gifting and tipping content creators
- Boosting posts on World Feed
- Future: Merchant payments within the app

Users fund coin purchases using their **Priority Bank wallet balance** (deposited via PBG payment APIs).

## Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   GekyChat      │     │  Sika Ledger    │     │  Priority Bank  │
│   Mobile/Web    │────▶│  (Source of     │────▶│  Ghana (PBG)    │
│                 │     │   Truth)        │     │  Real GHS       │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### Key Principles

1. **Ledger as Source of Truth**: All coin balances are derived from the ledger entries
2. **Idempotency**: Every transaction uses a unique idempotency key to prevent duplicates
3. **Atomic Transactions**: All operations use database transactions with row-level locking
4. **Double-Entry**: Transfers and gifts create paired entries with a shared `group_id`

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Priority Bank Ghana API
PBG_API_BASE_URL=https://api.prioritybank.gh/v1
PBG_API_KEY=your_api_key
PBG_API_SECRET=your_api_secret
PBG_API_TIMEOUT=30

# Feature Flags
SIKA_FEATURE_CASHOUT_ENABLED=false
SIKA_FEATURE_MERCHANT_PAY_ENABLED=false

# Internal Webhook Security
SIKA_INTERNAL_WEBHOOK_SECRET=your_webhook_secret
```

### Config File

The full configuration is in `config/sika.php`:

```php
return [
    'pbg' => [
        'base_url' => env('PBG_API_BASE_URL'),
        'api_key' => env('PBG_API_KEY'),
        'api_secret' => env('PBG_API_SECRET'),
        'timeout' => env('PBG_API_TIMEOUT', 30),
    ],
    'features' => [
        'cashout_enabled' => env('SIKA_FEATURE_CASHOUT_ENABLED', false),
        'merchant_pay_enabled' => env('SIKA_FEATURE_MERCHANT_PAY_ENABLED', false),
    ],
    // ... more settings
];
```

## Database Schema

### Tables

1. **sika_wallets** - User wallets with cached balance
2. **sika_ledger_entries** - Transaction ledger (source of truth)
3. **sika_packs** - Purchasable coin packages
4. **sika_cashout_tiers** - Cashout rate tiers and limits
5. **sika_cashout_requests** - Pending/processed cashout requests
6. **sika_merchants** - Registered merchants (future)
7. **sika_merchant_orders** - Merchant payment orders (future)

### Run Migrations

```bash
php artisan migrate
```

## Priority Bank (PBG) Integration

### Debit Wallet (Coin Purchase)

When a user purchases coins, GekyChat calls PBG to debit their wallet:

**Request:**
```http
POST /wallets/debit
Content-Type: application/json
X-API-Key: {api_key}
X-Timestamp: {iso8601_timestamp}
X-Signature: {hmac_sha256_signature}

{
    "user_id": 12345,
    "amount": 10.00,
    "currency": "GHS",
    "type": "SIKA_COIN_PURCHASE",
    "idempotency_key": "purchase_abc123_1709012345",
    "description": "Priority Sika Coins Purchase",
    "metadata": {
        "source": "gekychat",
        "transaction_type": "coin_purchase",
        "pack_id": 1,
        "pack_name": "Starter Pack"
    }
}
```

**Response (Success):**
```json
{
    "transaction_id": "pbg_txn_001",
    "reference": "PBG001",
    "amount": 10.00,
    "new_balance": 90.00,
    "timestamp": "2026-02-27T00:00:00Z"
}
```

**Response (Insufficient Funds):**
```json
{
    "error": "insufficient_funds",
    "message": "Insufficient balance in wallet",
    "available_balance": 5.00
}
```
HTTP Status: 402

### Credit Wallet (Cashout)

When processing a cashout, GekyChat credits the user's PBG wallet:

**Request:**
```http
POST /wallets/credit
Content-Type: application/json

{
    "user_id": 12345,
    "amount": 100.00,
    "currency": "GHS",
    "type": "SIKA_COIN_CASHOUT",
    "idempotency_key": "cashout_xyz789_pbg_credit",
    "description": "Sika Coins Cashout",
    "metadata": {
        "source": "gekychat",
        "transaction_type": "coin_cashout",
        "cashout_request_id": 456
    }
}
```

### Signature Generation

```php
$payload = strtoupper($method) . $endpoint . $timestamp . json_encode($data);
$signature = hash_hmac('sha256', $payload, $apiSecret);
```

## API Endpoints

### Public Endpoints (Authenticated)

#### List Coin Packs
```http
GET /api/v1/sika/packs
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Starter Pack",
            "description": "Perfect for beginners",
            "price_ghs": 5.00,
            "coins": 500,
            "bonus_coins": 50,
            "total_coins": 550,
            "coins_per_ghs": 110.00,
            "icon": "starter.png",
            "is_popular": true
        }
    ]
}
```

#### Get Wallet
```http
GET /api/v1/sika/wallet
```

**Response:**
```json
{
    "success": true,
    "data": {
        "wallet": {
            "id": 1,
            "user_id": 123,
            "balance": 5500,
            "formatted_balance": "5.5K",
            "status": "active",
            "can_transact": true
        },
        "recent_transactions": [...]
    }
}
```

#### Get Transactions
```http
GET /api/v1/sika/transactions?page=1&per_page=20&type=GIFT_IN&direction=CREDIT
```

#### Purchase Coins
```http
POST /api/v1/sika/purchase/initiate
Content-Type: application/json

{
    "pack_id": 1,
    "idempotency_key": "purchase_user123_1709012345"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Coins purchased successfully",
    "data": {
        "entry_id": 1,
        "coins_credited": 550,
        "new_balance": 550,
        "pack_id": 1,
        "pack_name": "Starter Pack",
        "price_ghs": "5.00",
        "pbg_reference": "pbg_txn_001"
    }
}
```

#### Transfer Coins
```http
POST /api/v1/sika/transfer
Content-Type: application/json

{
    "to_user_id": 456,
    "coins": 100,
    "note": "Thanks for your help!",
    "idempotency_key": "transfer_user123_to_456_1709012345"
}
```

#### Gift Coins
```http
POST /api/v1/sika/gift
Content-Type: application/json

{
    "to_user_id": 456,
    "coins": 50,
    "post_id": 789,
    "note": "Great content!",
    "idempotency_key": "gift_user123_post789_1709012345"
}
```

#### Request Cashout (Feature-Flagged)
```http
POST /api/v1/sika/cashout/request
Content-Type: application/json

{
    "coins": 1000000,
    "idempotency_key": "cashout_user123_1709012345"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Cashout request submitted",
    "data": {
        "request_id": 1,
        "coins_requested": 1000000,
        "ghs_to_credit": 100.00,
        "fee_applied": 0.00,
        "net_ghs": 100.00,
        "status": "PENDING",
        "available_at": null
    }
}
```

### Admin Endpoints

All admin endpoints require the `admin` role.

#### Manage Packs
```http
GET    /api/v1/sika/admin/packs
POST   /api/v1/sika/admin/packs
PUT    /api/v1/sika/admin/packs/{id}
```

#### Manage Cashout Tiers
```http
GET    /api/v1/sika/admin/cashout-tiers
POST   /api/v1/sika/admin/cashout-tiers
PUT    /api/v1/sika/admin/cashout-tiers/{id}
```

#### Manage Cashout Requests
```http
GET    /api/v1/sika/admin/cashout-requests?status=PENDING
POST   /api/v1/sika/admin/cashout-requests/{id}/approve
POST   /api/v1/sika/admin/cashout-requests/{id}/reject
POST   /api/v1/sika/admin/cashout-requests/{id}/process
```

#### Wallet Adjustment
```http
POST /api/v1/sika/admin/adjust
Content-Type: application/json

{
    "user_id": 123,
    "coins": 1000,
    "direction": "CREDIT",
    "reason": "Compensation for service issue",
    "idempotency_key": "admin_adjust_123_1709012345"
}
```

## Enabling Cashout

### Step 1: Create Cashout Tiers

```http
POST /api/v1/sika/admin/cashout-tiers
{
    "name": "Standard Tier",
    "min_coins": 1000000,
    "max_coins": null,
    "ghs_per_million_coins": 100.00,
    "fee_percent": 0,
    "fee_flat_ghs": 0,
    "daily_limit": 5000000,
    "weekly_limit": 20000000,
    "monthly_limit": 50000000,
    "hold_days": 7,
    "is_active": true
}
```

This creates a tier where:
- Minimum cashout: 1,000,000 coins
- Rate: 1,000,000 coins = GHS 100
- 7-day hold period before processing

### Step 2: Enable Feature Flag

```env
SIKA_FEATURE_CASHOUT_ENABLED=true
```

### Step 3: Admin Workflow

1. User requests cashout → Status: `PENDING`
2. Admin reviews and approves → Status: `APPROVED`
3. After hold period, admin processes → PBG credit → Status: `PAID`

## Anti-Fraud Measures

### Why Different Rates?

The **buy rate** (e.g., GHS 10 = 1,100 coins) is better than the **cashout rate** (e.g., 1,000,000 coins = GHS 100) to:

1. **Discourage arbitrage**: Prevents users from buying and immediately cashing out for profit
2. **Encourage in-app spending**: Coins are more valuable when used for gifting/tipping
3. **Reduce fraud risk**: Makes money laundering schemes unprofitable
4. **Cover operational costs**: Processing cashouts has real costs

### Built-in Protections

1. **Idempotency Keys**: Every transaction requires a unique key
2. **Row-Level Locking**: Prevents race conditions and negative balances
3. **Wallet Status**: Wallets can be suspended or frozen
4. **Cashout Limits**: Daily, weekly, and monthly limits per tier
5. **Hold Periods**: Configurable delay before cashout processing
6. **Admin Approval**: Manual review for cashout requests

## Testing

Run the test suite:

```bash
php artisan test --filter=Sika
```

Tests cover:
- Wallet auto-creation
- Pack purchases with PBG integration
- Idempotency (duplicate prevention)
- Transfer atomicity
- Insufficient balance rejection
- Concurrent transfer locking
- Feature flag enforcement

## Seeding Sample Data

Create a seeder for initial packs:

```php
// database/seeders/SikaPackSeeder.php
SikaPack::create([
    'name' => 'Starter',
    'description' => 'Perfect for beginners',
    'price_ghs' => 5.00,
    'coins' => 500,
    'bonus_coins' => 50,
    'is_active' => true,
    'sort_order' => 1,
]);

SikaPack::create([
    'name' => 'Popular',
    'description' => 'Best value!',
    'price_ghs' => 20.00,
    'coins' => 2500,
    'bonus_coins' => 500,
    'is_active' => true,
    'sort_order' => 2,
]);

SikaPack::create([
    'name' => 'Premium',
    'description' => 'For power users',
    'price_ghs' => 50.00,
    'coins' => 7000,
    'bonus_coins' => 1500,
    'is_active' => true,
    'sort_order' => 3,
]);
```

## Ledger Entry Types

| Type | Direction | Description |
|------|-----------|-------------|
| PURCHASE_CREDIT | CREDIT | Coins purchased with GHS |
| TRANSFER_OUT | DEBIT | Coins sent to another user |
| TRANSFER_IN | CREDIT | Coins received from another user |
| GIFT_OUT | DEBIT | Coins gifted to content creator |
| GIFT_IN | CREDIT | Coins received as gift |
| SPEND | DEBIT | Coins spent on features |
| MERCHANT_PAY | DEBIT | Payment to merchant |
| MERCHANT_RECEIVE | CREDIT | Merchant receiving payment |
| CASHOUT_DEBIT | DEBIT | Coins converted to GHS |
| REFUND | CREDIT | Refunded coins |
| ADMIN_ADJUST | CREDIT/DEBIT | Manual admin adjustment |

## Future Features

### Merchant Payments

When enabled (`SIKA_FEATURE_MERCHANT_PAY_ENABLED=true`):

1. Merchants register and get approved
2. Users pay merchants with coins
3. Platform takes commission
4. Merchants receive net coins

### Scheduled Gifts

Allow users to schedule recurring gifts to favorite creators.

### Coin Expiry

Optional feature to expire unused coins after a period.
