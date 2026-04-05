<?php

namespace App\Http\Resources;


use App\Services\TextFormattingService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;


class MessageResource extends JsonResource
{
    public function toArray($request)
    {
        $m = $this->resource; // Message or GroupMessage


        // Normalize sender
        $sender = $m->relationLoaded('sender') ? $m->sender : null;
        $senderArr = $sender ? [
            'id' => $sender->id,
            'name' => $sender->name ?? $sender->phone,
            'avatar' => $sender->avatar_path ? asset('storage/' . $sender->avatar_path) : null,
        ] : null;
        $atts = $m->relationLoaded('attachments') ? $m->attachments : collect();
        $attachments = $atts->map(fn($a) => [
            'id' => $a->id,
            'url' => $a->url,
            'mime_type' => $a->mime_type,
            'size' => $a->size,
            'is_image' => $a->is_image,
            'is_video' => $a->is_video,
            'is_audio' => $a->is_audio,
            'is_document' => $a->is_document,
            'original_name' => $a->original_name,
            'shared_as_document' => (bool)($a->shared_as_document ?? false),
            'is_voicenote' => (bool)($a->is_voicenote ?? false),
            // MEDIA COMPRESSION: Include compression fields
            'compression_status' => $a->compression_status ?? null,
            'compressed_url' => $a->compressed_url ?? null,
            'thumbnail_url' => $a->thumbnail_url ?? null,
            'original_size' => $a->original_size ?? null,
            'compressed_size' => $a->compressed_size ?? null,
            'compression_level' => $a->compression_level ?? null,
        ]);


        // Reply/forward previews
        $reply = $m->relationLoaded('replyTo') ? $m->replyTo : null;
        $replyArr = $reply ? [
            'id' => $reply->id,
            'sender_id' => $reply->sender_id,
            'body_preview' => mb_strimwidth((string)$reply->body, 0, 140, '…'),
        ] : null;


        $fwdFrom = $m->relationLoaded('forwardedFrom') ? $m->forwardedFrom : null;
        $fwdFromArr = $fwdFrom ? [
            'id' => $fwdFrom->id,
            'sender_id' => $fwdFrom->sender_id,
            'body_preview' => mb_strimwidth((string)$fwdFrom->body, 0, 140, '…'),
        ] : null;


        $reactions = $m->relationLoaded('reactions') ? $m->reactions->map(fn($r) => [
            'emoji' => $r->emoji,
            'user_id' => $r->user_id, // Add user_id for desktop app compatibility
            'user' => $r->relationLoaded('user') && $r->user ? [
                'id' => $r->user->id,
                'name' => $r->user->name ?? $r->user->phone,
                'avatar' => $r->user->avatar_path ? asset('storage/' . $r->user->avatar_path) : null,
            ] : null,
        ]) : [];

        // Poll data for message list / refetch so clients get question + options without relying only on client-side merge.
        // Included when type === 'poll' so _loadMessages() and all list endpoints (index, around, sync, conversation, group) keep poll UI.
        $pollData = null;
        if (($m->type ?? '') === 'poll') {
            $isGroupMessage = $m->getTable() === 'group_messages';
            $pollRow = $isGroupMessage
                ? DB::table('message_polls')->where('group_message_id', $m->id)->first()
                : DB::table('message_polls')->where('message_id', $m->id)->first();
            if ($pollRow) {
                $options = DB::table('message_poll_options')
                    ->where('poll_id', $pollRow->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn($o) => ['id' => $o->id, 'text' => $o->text])
                    ->values()
                    ->all();
                $pollData = [
                    'question' => $pollRow->question,
                    'allow_multiple' => (bool) $pollRow->allow_multiple,
                    'is_anonymous' => (bool) $pollRow->is_anonymous,
                    'options' => $options,
                ];
            }
        }

        return [
            'id' => $m->id,
            'client_message_id' => $m->client_uuid ?? null, // For offline sync and deduplication
            'conversation_id' => $m->conversation_id ?? null,
            'group_id' => $m->group_id ?? null,
            'sender' => $senderArr,
            'sender_id' => $m->sender_id,
            'body' => $m->is_encrypted ? $this->decryptSafely($m->body) : (string)$m->body,
            'body_formatted' => $m->is_encrypted ? null : ($request->query('include_formatting') ? TextFormattingService::parseFormatting((string)$m->body) : null), // Optional parsed formatting
            'is_encrypted' => (bool)($m->is_encrypted ?? false),
            'is_system' => (bool)($m->is_system ?? false), // System messages (e.g., "User joined")
            'system_action' => $m->system_action ?? null, // Action type for system messages
            'attachments' => $attachments,
            'reply_to' => $replyArr,
            // Always include reply_to_id from column when set, so it is present even when replyTo relation is not loaded
            'reply_to_id' => $m->reply_to ?? ($reply ? $reply->id : null),
            'referenced_status_id' => $m->referenced_status_id ?? null,
            'referenced_status' => $this->formatReferencedStatus($m),
            'referenced_group_id' => $m->referenced_group_id ?? null,
            'referenced_group_message_id' => $m->referenced_group_message_id ?? null,
            'referenced_group' => $this->formatReferencedGroup($m),
            'forwarded_from' => $fwdFromArr,
            'forwarded_from_id' => $fwdFrom ? $fwdFrom->id : null, // Add forwarded_from_id for desktop app compatibility
            'forward_chain' => $m->forward_chain ?? null,
            'reactions' => $reactions,
            'read_at' => optional($m->read_at)->toIso8601String(),
            'delivered_at' => optional($m->delivered_at)->toIso8601String(),
            'edited_at' => optional($m->edited_at)->toIso8601String(),
            'expires_at' => optional($m->expires_at)->toIso8601String(),
            'created_at' => optional($m->created_at)->toIso8601String(),
            'updated_at' => optional($m->updated_at)->toIso8601String(),
            'version' => $m->version ?? 1,
            'type' => $m->type ?? null, // 'poll' | 'contact' | 'location' | 'call' | etc.
            'poll_data' => $pollData,
            'location_data' => $m->location_data ?? null,
            'contact_data' => $m->contact_data ?? null,
            'call_data' => $m->call_data ?? null,
            'view_once' => (bool)($m->is_view_once ?? false),
            'view_once_opened' => $m->viewed_at !== null,
            'link_previews' => $m->link_previews ?? [],
            'scheduled_at' => optional($m->scheduled_at)->toIso8601String(),
            'deleted_for_everyone_at' => isset($m->deleted_for_everyone_at) && $m->deleted_for_everyone_at
                ? optional($m->deleted_for_everyone_at)->toIso8601String()
                : null,
        ];
    }


    protected function decryptSafely(?string $cipher): string
    {
        if (!$cipher) return '';
        try {
            return decrypt($cipher);
        } catch (\Throwable $e) {
            return '[Encrypted message]';
        }
    }

    /**
     * Snapshot of the story/status this message replies to (WhatsApp-style).
     *
     * @param  \App\Models\Message|\App\Models\GroupMessage  $m
     */
    protected function formatReferencedStatus($m): ?array
    {
        if (! method_exists($m, 'referencedStatus')) {
            return null;
        }
        if (empty($m->referenced_status_id)) {
            return null;
        }
        if (($m->group_id ?? null) !== null) {
            return null;
        }
        $ref = $m->relationLoaded('referencedStatus')
            ? $m->referencedStatus
            : $m->referencedStatus()->first();
        if (! $ref) {
            return [
                'id' => (int) $m->referenced_status_id,
                'user_id' => null,
                'type' => null,
                'text' => null,
                'media_url' => null,
                'thumbnail_url' => null,
                'expires_at' => null,
                'expired' => true,
            ];
        }
        $expired = $ref->isExpired();

        return [
            'id' => $ref->id,
            'user_id' => $ref->user_id,
            'type' => $ref->type,
            'text' => $ref->text,
            'media_url' => $ref->media_url,
            'thumbnail_url' => $ref->thumbnail_url,
            'expires_at' => optional($ref->expires_at)->toIso8601String(),
            'expired' => $expired,
        ];
    }

    /**
     * Snapshot for private replies that reference a group message (WhatsApp-style).
     *
     * @param  \App\Models\Message|\App\Models\GroupMessage  $m
     */
    protected function formatReferencedGroup($m): ?array
    {
        if (empty($m->referenced_group_message_id) || empty($m->referenced_group_id)) {
            return null;
        }
        if (($m->group_id ?? null) !== null) {
            return null;
        }
        $gm = method_exists($m, 'referencedGroupMessage')
            ? ($m->relationLoaded('referencedGroupMessage') ? $m->referencedGroupMessage : $m->referencedGroupMessage()->with('group')->first())
            : null;
        if (! $gm) {
            return [
                'group_id' => (int) $m->referenced_group_id,
                'group_message_id' => (int) $m->referenced_group_message_id,
                'group_name' => null,
                'body_preview' => null,
            ];
        }
        $g = $gm->relationLoaded('group') ? $gm->group : $gm->group()->first();

        return [
            'group_id' => (int) $m->referenced_group_id,
            'group_message_id' => (int) $m->referenced_group_message_id,
            'group_name' => $g->name ?? null,
            'body_preview' => mb_strimwidth((string) $gm->body, 0, 160, '…'),
        ];
    }
}
