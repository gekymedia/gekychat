<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Canonical user payload for call invites, push notifications, and CallKit.
 * Phone is the primary identifier — every real user must have one on file.
 */
final class CallPartyPayload
{
    /**
     * @return array{id:int,name:string,phone:string,avatar:?string}
     */
    public static function forUser(User $user): array
    {
        $phone = self::phoneFromUser($user);
        $name = trim((string) ($user->name ?? ''));
        if ($name === '') {
            $name = $phone !== '' ? $phone : ('User '.$user->id);
        }

        return [
            'id' => (int) $user->id,
            'name' => $name,
            'phone' => $phone,
            'avatar' => $user->avatar_url ?? null,
        ];
    }

    public static function phoneFromUser(User $user): string
    {
        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            Log::warning('CallPartyPayload: user missing phone', ['user_id' => $user->id]);
        }

        return $phone;
    }

    /**
     * @return array{status:string,code:string,message:string}|null Null when the user has a phone on file.
     */
    public static function phoneRequiredErrorForUser(User $user, string $role = 'caller'): ?array
    {
        if (self::phoneFromUser($user) !== '') {
            return null;
        }

        $isCallee = $role === 'callee';

        return [
            'status' => 'error',
            'code' => $isCallee ? 'callee_phone_required' : 'caller_phone_required',
            'message' => $isCallee
                ? 'This contact cannot receive calls because they have no phone number on file.'
                : 'Add a phone number to your account before placing calls.',
        ];
    }

    public static function phoneForUserId(?int $userId): string
    {
        if (! $userId || $userId <= 0) {
            return '';
        }

        $user = User::query()->select(['id', 'phone'])->find($userId);
        if (! $user) {
            return '';
        }

        return self::phoneFromUser($user);
    }

    /** Resolve peer phone for conversation API payloads (never omit when user id is known). */
    public static function phoneForConversationPeer(?User $other, ?int $otherUserId = null): string
    {
        if ($other) {
            $phone = self::phoneFromUser($other);
            if ($phone !== '') {
                return $phone;
            }
        }

        return self::phoneForUserId($otherUserId);
    }

    /**
     * @param  array<string, mixed>  $callData
     */
    public static function callerPhoneFromPushData(array $callData): string
    {
        $phone = trim((string) ($callData['caller_phone'] ?? ''));
        if ($phone !== '' && ! self::isUuidLike($phone)) {
            return $phone;
        }

        $callerId = (int) ($callData['caller_id'] ?? 0);
        if ($callerId > 0) {
            $resolved = self::phoneForUserId($callerId);
            if ($resolved !== '' && ! self::isUuidLike($resolved)) {
                return $resolved;
            }
        }

        return '';
    }

    public static function isUuidLike(string $value): bool
    {
        $trimmed = trim($value);

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $trimmed
        );
    }
}
