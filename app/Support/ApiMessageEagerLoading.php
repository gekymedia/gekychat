<?php

namespace App\Support;

/**
 * Consistent eager loads for {@see \App\Http\Resources\MessageResource} so list, search, sync, and “around”
 * endpoints return the same relation depth (forwards, mentions, refs, full attachments, statuses).
 */
final class ApiMessageEagerLoading
{
    /**
     * @return array<int|string, mixed>
     */
    public static function directMessageRelations(): array
    {
        return [
            'sender:id,name,phone,username,avatar_path',
            'attachments',
            'replyTo:id,body,sender_id',
            'replyTo.sender:id,name,phone,username,avatar_path',
            'forwardedFrom:id,body,sender_id',
            'referencedStatus:id,user_id,type,text,media_url,thumbnail_url,expires_at',
            'referencedGroupMessage:id,body,group_id',
            'referencedGroupMessage.group:id,name',
            'mentions.mentionedUser:id,name,username,avatar_path',
            'reactions' => static function ($q): void {
                $q->select('id', 'message_id', 'user_id', 'reaction')->limit(100);
            },
            'reactions.user:id,name,avatar_path',
            'statuses',
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function groupMessageRelations(): array
    {
        return [
            'sender:id,name,phone,username,avatar_path',
            'attachments',
            'replyTo:id,body,sender_id',
            'forwardedFrom:id,body,sender_id',
            'mentions.mentionedUser:id,name,username,avatar_path',
            'reactions' => static function ($q): void {
                $q->select('id', 'group_message_id', 'user_id', 'emoji')->limit(100);
            },
            'reactions.user:id,name,avatar_path',
            'statuses',
        ];
    }
}
