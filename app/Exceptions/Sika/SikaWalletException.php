<?php

namespace App\Exceptions\Sika;

use Exception;

class SikaWalletException extends Exception
{
    public const CODE_WALLET_SUSPENDED = 1001;
    public const CODE_WALLET_FROZEN = 1002;
    public const CODE_INSUFFICIENT_BALANCE = 1003;
    public const CODE_INVALID_AMOUNT = 1004;
    public const CODE_SELF_TRANSFER = 1005;
    public const CODE_DUPLICATE_TRANSACTION = 1006;
    public const CODE_PACK_NOT_FOUND = 1007;
    public const CODE_PACK_INACTIVE = 1008;
    public const CODE_CASHOUT_DISABLED = 1009;
    public const CODE_CASHOUT_LIMIT_EXCEEDED = 1010;
    public const CODE_MERCHANT_NOT_ACTIVE = 1011;
    public const CODE_MERCHANT_PAY_DISABLED = 1012;

    public static function walletSuspended(): self
    {
        return new self('Wallet is suspended', self::CODE_WALLET_SUSPENDED);
    }

    public static function walletFrozen(): self
    {
        return new self('Wallet is frozen', self::CODE_WALLET_FROZEN);
    }

    public static function insufficientBalance(int $required, int $available): self
    {
        return new self(
            "Insufficient balance. Required: {$required} coins, Available: {$available} coins",
            self::CODE_INSUFFICIENT_BALANCE
        );
    }

    public static function invalidAmount(): self
    {
        return new self('Invalid coin amount', self::CODE_INVALID_AMOUNT);
    }

    public static function selfTransfer(): self
    {
        return new self('Cannot transfer coins to yourself', self::CODE_SELF_TRANSFER);
    }

    public static function duplicateTransaction(string $idempotencyKey): self
    {
        return new self(
            "Transaction with idempotency key '{$idempotencyKey}' already exists",
            self::CODE_DUPLICATE_TRANSACTION
        );
    }

    public static function packNotFound(): self
    {
        return new self('Coin pack not found', self::CODE_PACK_NOT_FOUND);
    }

    public static function packInactive(): self
    {
        return new self('Coin pack is not available', self::CODE_PACK_INACTIVE);
    }

    public static function cashoutDisabled(): self
    {
        return new self('Cashout feature is currently disabled', self::CODE_CASHOUT_DISABLED);
    }

    public static function cashoutLimitExceeded(string $limitType): self
    {
        return new self(
            "Cashout {$limitType} limit exceeded",
            self::CODE_CASHOUT_LIMIT_EXCEEDED
        );
    }

    public static function merchantNotActive(): self
    {
        return new self('Merchant is not active', self::CODE_MERCHANT_NOT_ACTIVE);
    }

    public static function merchantPayDisabled(): self
    {
        return new self('Merchant payments are currently disabled', self::CODE_MERCHANT_PAY_DISABLED);
    }
}
