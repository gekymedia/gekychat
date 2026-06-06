<?php

namespace App\Http\Support;

use App\Models\GroupMessage;
use App\Models\Message;

/**
 * Human-readable chat-list preview for the last message in a conversation.
 */
final class MessageListPreview
{
    public static function forDirectMessage(Message $m, int $viewerId): string
    {
        if ($m->is_encrypted && (int) $m->sender_id !== $viewerId) {
            return '[Encrypted]';
        }

        if (! empty($m->is_view_once)) {
            $text = trim((string) ($m->body ?? ''));

            return $text !== '' ? $text : '📷 Photo';
        }

        $typed = self::forType((string) ($m->type ?? ''));
        if ($typed !== null) {
            return $typed;
        }

        if (! empty($m->location_data)) {
            $loc = is_array($m->location_data) ? $m->location_data : [];

            return ! empty($loc['is_live']) ? '📍 Live location' : '📍 Location';
        }
        if (! empty($m->contact_data)) {
            return '👤 Contact';
        }
        if (! empty($m->call_data)) {
            return self::forCallData(is_array($m->call_data) ? $m->call_data : []);
        }
        if (($m->type ?? '') === 'poll') {
            return '📊 Poll';
        }

        $text = trim((string) ($m->body ?? ''));
        if ($text !== '') {
            return mb_strimwidth($text, 0, 140, '…');
        }

        if ($m->relationLoaded('attachments') ? $m->attachments->isNotEmpty() : $m->attachments()->exists()) {
            $att = $m->relationLoaded('attachments')
                ? $m->attachments->first()
                : $m->attachments()->first();
            if ($att) {
                return self::forAttachment($att, ! empty($m->is_view_once));
            }
        }

        return '';
    }

    public static function forGroupMessage(GroupMessage $m): string
    {
        if ($m->is_system) {
            $text = trim((string) ($m->body ?? ''));

            return $text !== '' ? mb_strimwidth($text, 0, 140, '…') : '';
        }

        if (! empty($m->is_view_once)) {
            $text = trim((string) ($m->body ?? ''));

            return $text !== '' ? $text : '📷 Photo';
        }

        $typed = self::forType((string) ($m->type ?? ''));
        if ($typed !== null) {
            return $typed;
        }

        if (! empty($m->location_data)) {
            $loc = is_array($m->location_data) ? $m->location_data : [];

            return ! empty($loc['is_live']) ? '📍 Live location' : '📍 Location';
        }
        if (! empty($m->contact_data)) {
            return '👤 Contact';
        }
        if (! empty($m->call_data)) {
            return self::forCallData(is_array($m->call_data) ? $m->call_data : []);
        }
        if (($m->type ?? '') === 'poll') {
            return '📊 Poll';
        }

        $text = trim((string) ($m->body ?? ''));
        if ($text !== '') {
            return mb_strimwidth($text, 0, 140, '…');
        }

        if ($m->relationLoaded('attachments') ? $m->attachments->isNotEmpty() : $m->attachments()->exists()) {
            $att = $m->relationLoaded('attachments')
                ? $m->attachments->first()
                : $m->attachments()->first();
            if ($att) {
                return self::forAttachment($att, ! empty($m->is_view_once));
            }
        }

        return '';
    }

    private static function forType(string $type): ?string
    {
        return match (strtolower($type)) {
            'poll' => '📊 Poll',
            'location' => '📍 Location',
            'live_location' => '📍 Live location',
            'contact' => '👤 Contact',
            'call', 'voice_call' => '📞 Voice call',
            'video_call' => '📹 Video call',
            'image', 'photo' => '📷 Photo',
            'video' => '🎬 Video',
            'audio', 'voice', 'voice_note' => '🎤 Voice message',
            'document' => '📎 Document',
            default => null,
        };
    }

    private static function forCallData(array $cd): string
    {
        if (! empty($cd['missed']) || ! empty($cd['is_missed'])) {
            return '📞 Missed call';
        }
        $callType = strtolower((string) ($cd['call_type'] ?? $cd['type'] ?? ''));

        return str_contains($callType, 'video') ? '📹 Video call' : '📞 Voice call';
    }

    /**
     * @param  object  $att  Attachment model (mime_type, is_voicenote, original_name, …)
     */
    private static function forAttachment(object $att, bool $isViewOnce): string
    {
        if ($isViewOnce) {
            return '📷 Photo';
        }

        $mime = strtolower((string) ($att->mime_type ?? ''));
        $isVoicenote = ! empty($att->is_voicenote);

        if ($isVoicenote) {
            return '🎤 Voice message';
        }
        if (str_starts_with($mime, 'image/')) {
            return '📷 Photo';
        }
        if (str_starts_with($mime, 'video/')) {
            return '🎬 Video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return '🎤 Voice message';
        }

        $name = trim((string) ($att->original_name ?? $att->name ?? ''));

        return $name !== '' ? '📎 '.$name : '📎 Document';
    }
}
