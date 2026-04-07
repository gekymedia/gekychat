<?php

namespace App\Http\Support;

use App\Models\GroupMessage;
use App\Models\Message;

/**
 * Shapes `last_message` on conversation/group list APIs for chat list previews (ticks, etc.).
 */
final class ApiLastMessagePayload
{
    public static function forDirectMessage(?Message $last, int $viewerId): ?array
    {
        if ($last === null) {
            return null;
        }

        if (! $last->relationLoaded('statuses')) {
            $last->load(['statuses' => fn ($q) => $q->whereNull('deleted_at')]);
        }

        $senderType = (string) ($last->sender_type ?? 'user');
        $isFromMe = $senderType === 'user' && (int) $last->sender_id === $viewerId;

        $readAt = $last->read_at;
        $deliveredAt = $last->delivered_at;

        $outgoingStatus = null;
        if ($isFromMe) {
            $outgoingStatus = $readAt ? 'read' : ($deliveredAt ? 'delivered' : 'sent');
        }

        return [
            'id' => $last->id,
            'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string) ($last->body ?? ''), 0, 140, '…'),
            'created_at' => optional($last->created_at)->toIso8601String(),
            'sender_id' => $last->sender_id,
            'sender_type' => $senderType,
            'is_from_me' => $isFromMe,
            'outgoing_status' => $outgoingStatus,
            'read_at' => $readAt ? $readAt->toIso8601String() : null,
            'delivered_at' => $deliveredAt ? $deliveredAt->toIso8601String() : null,
        ];
    }

    public static function forGroupMessage(?GroupMessage $last, int $viewerId): ?array
    {
        if ($last === null) {
            return null;
        }

        if (! $last->relationLoaded('statuses')) {
            $last->load(['statuses' => fn ($q) => $q->whereNull('deleted_at')]);
        }

        $isFromMe = ! $last->is_system && (int) $last->sender_id === $viewerId;

        $readAt = $last->read_at;
        $deliveredAt = $last->delivered_at;

        $outgoingStatus = null;
        if ($isFromMe) {
            $outgoingStatus = $readAt ? 'read' : ($deliveredAt ? 'delivered' : 'sent');
        }

        return [
            'id' => $last->id,
            'body_preview' => mb_strimwidth((string) ($last->body ?? ''), 0, 140, '…'),
            'created_at' => optional($last->created_at)->toIso8601String(),
            'sender_id' => $last->sender_id,
            'is_from_me' => $isFromMe,
            'outgoing_status' => $outgoingStatus,
            'read_at' => $readAt ? $readAt->toIso8601String() : null,
            'delivered_at' => $deliveredAt ? $deliveredAt->toIso8601String() : null,
        ];
    }
}
