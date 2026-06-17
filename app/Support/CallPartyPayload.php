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
        if ($phone !== '') {
            return $phone;
        }

        $callerId = (int) ($callData['caller_id'] ?? 0);
        if ($callerId > 0) {
            return self::phoneForUserId($callerId);
        }

        return '';
    }
}
