<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Group;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * Get all media from a conversation.
     * GET /conversations/{id}/media
     */
    public function conversationMedia(Request $request, $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        abort_unless($conversation->isParticipant($request->user()->id), 403);

        $messages = $conversation->messages()
            ->whereHas('attachments', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('mime_type', 'LIKE', 'image/%')
                        ->orWhere('mime_type', 'LIKE', 'video/%');
                });
            })
            ->with(['attachments', 'sender:id,name,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->get();

        $media = collect();
        foreach ($messages as $message) {
            foreach ($message->attachments as $attachment) {
                if ($attachment->isImage() || $attachment->isVideo()) {
                    $media->push([
                        'id' => $attachment->id,
                        'message_id' => $message->id,
                        'type' => $attachment->isImage() ? 'image' : 'video',
                        'url' => $attachment->url,
                        'thumbnail_url' => $attachment->thumbnail_url,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'sender' => [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                            'avatar_url' => $message->sender->avatar_path
                                ? asset('storage/' . $message->sender->avatar_path)
                                : null,
                        ],
                        'created_at' => $attachment->created_at->toIso8601String(),
                    ]);
                }
            }
        }

        return response()->json([
            'data' => $media->values(),
            'total' => $media->count(),
        ]);
    }

    /**
     * Get all media from a group.
     * GET /groups/{id}/media
     */
    public function groupMedia(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($request->user()), 403);

        $messages = $group->messages()
            ->whereHas('attachments', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('mime_type', 'LIKE', 'image/%')
                        ->orWhere('mime_type', 'LIKE', 'video/%');
                });
            })
            ->with(['attachments', 'sender:id,name,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->get();

        $media = collect();
        foreach ($messages as $message) {
            foreach ($message->attachments as $attachment) {
                if ($attachment->isImage() || $attachment->isVideo()) {
                    $media->push([
                        'id' => $attachment->id,
                        'message_id' => $message->id,
                        'type' => $attachment->isImage() ? 'image' : 'video',
                        'url' => $attachment->url,
                        'thumbnail_url' => $attachment->thumbnail_url,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'sender' => [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                            'avatar_url' => $message->sender->avatar_path
                                ? asset('storage/' . $message->sender->avatar_path)
                                : null,
                        ],
                        'created_at' => $attachment->created_at->toIso8601String(),
                    ]);
                }
            }
        }

        return response()->json([
            'data' => $media->values(),
            'total' => $media->count(),
        ]);
    }
}

