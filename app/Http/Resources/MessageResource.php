<?php

namespace App\Http\Resources;


use App\Services\TextFormattingService;
use Illuminate\Http\Resources\Json\JsonResource;


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
            'is_document' => $a->is_document,
            'original_name' => $a->original_name,
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
            'reply_to_id' => $reply ? $reply->id : null, // Add reply_to_id for desktop app compatibility
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
            'location_data' => $m->location_data ?? null,
            'contact_data' => $m->contact_data ?? null,
            'link_previews' => $m->link_previews ?? [],
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
}
