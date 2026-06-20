<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Get shared media index for a conversation (photos, videos, docs, links).
     * GET /conversations/{id}/media
     */
    public function conversationMedia(Request $request, $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        abort_unless($conversation->isParticipant($request->user()->id), 403);

        return $this->mediaResponse(
            $conversation->messages()
                ->where(function (Builder $q) {
                    $q->whereHas('attachments')
                        ->orWhere('body', 'like', '%http://%')
                        ->orWhere('body', 'like', '%https://%');
                })
                ->with(['attachments', 'sender:id,name,avatar_path'])
                ->orderByDesc('created_at')
        );
    }

    /**
     * Get shared media index for a group.
     * GET /groups/{id}/media
     */
    public function groupMedia(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($request->user()), 403);

        return $this->mediaResponse(
            $group->messages()
                ->where(function (Builder $q) {
                    $q->whereHas('attachments')
                        ->orWhere('body', 'like', '%http://%')
                        ->orWhere('body', 'like', '%https://%');
                })
                ->with(['attachments', 'sender:id,name,avatar_path'])
                ->orderByDesc('created_at')
        );
    }

    private function mediaResponse(Builder $query)
    {
        $messages = $query->get();

        $data = $messages->map(function ($message) {
            $attachments = $message->attachments
                ->map(fn (Attachment $attachment) => $this->serializeAttachment($attachment, $message->id))
                ->values();

            return [
                'id' => $message->id,
                'body' => $message->display_body ?? $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'link_previews' => $message->link_previews ?? [],
                'attachments' => $attachments,
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar_url' => $message->sender->avatar_path
                        ? asset('storage/' . $message->sender->avatar_path)
                        : null,
                ] : null,
            ];
        })->filter(function (array $row) {
            return count($row['attachments']) > 0 || count($row['link_previews']) > 0;
        })->values();

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    private function serializeAttachment(Attachment $attachment, int $messageId): array
    {
        $type = 'file';
        if ($attachment->is_image) {
            $type = 'image';
        } elseif ($attachment->is_video) {
            $type = 'video';
        } elseif ($attachment->is_audio) {
            $type = 'audio';
        } elseif ($attachment->is_document) {
            $type = 'document';
        }

        return [
            'id' => $attachment->id,
            'message_id' => $messageId,
            'type' => $type,
            'url' => $attachment->url,
            'thumbnail_url' => $attachment->thumbnail_url,
            'mime_type' => $attachment->mime_type,
            'original_name' => $attachment->original_name,
            'size' => $attachment->size,
            'shared_as_document' => (bool) $attachment->shared_as_document,
            'is_voicenote' => (bool) $attachment->is_voicenote,
            'is_image' => $attachment->is_image,
            'is_video' => $attachment->is_video,
            'is_audio' => $attachment->is_audio,
            'is_document' => $attachment->is_document,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
