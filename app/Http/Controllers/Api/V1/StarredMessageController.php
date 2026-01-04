<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\GroupMessage;
use App\Models\StarredMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StarredMessageController extends Controller
{
    /**
     * Get all starred messages for the authenticated user
     * GET /api/v1/starred-messages
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $starredMessages = StarredMessage::where('user_id', $user->id)
            ->with([
                'message.sender:id,name,avatar_path',
                'message.attachments',
                'message.conversation',
                'groupMessage.sender:id,name,avatar_path',
                'groupMessage.attachments',
                'groupMessage.group:id,name',
            ])
            ->orderBy('starred_at', 'desc')
            ->get();

        $data = $starredMessages->map(function ($starred) {
            $message = $starred->getActualMessage();
            if (!$message) {
                return null;
            }

            $messageData = [
                'id' => $message->id,
                'body' => $message->body,
                'type' => $message->type ?? 'text',
                'created_at' => $message->created_at->toIso8601String(),
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar_url' => $message->sender->avatar_url,
                ],
                'attachments' => $message->attachments->map(function ($att) {
                    return [
                        'id' => $att->id,
                        'type' => $att->type,
                        'url' => $att->url,
                        'thumbnail_url' => $att->thumbnail_url,
                        'name' => $att->name,
                        'size' => $att->size,
                    ];
                }),
            ];

            // Add conversation or group context
            if ($starred->message_id && $message instanceof Message) {
                $conversation = $message->conversation;
                $messageData['conversation'] = [
                    'id' => $conversation->id,
                    'type' => 'conversation',
                    'title' => $conversation->title,
                ];
            } elseif ($starred->group_message_id && $message instanceof GroupMessage) {
                $group = $message->group;
                $messageData['group'] = [
                    'id' => $group->id,
                    'type' => 'group',
                    'name' => $group->name,
                ];
            }

            $messageData['starred_at'] = $starred->starred_at->toIso8601String();

            return $messageData;
        })->filter();

        return response()->json([
            'data' => $data->values(),
        ]);
    }

    /**
     * Star a regular message
     * POST /api/v1/messages/{id}/star
     */
    public function star(Request $request, $messageId)
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);
        $conversation = $message->conversation;
        abort_unless($conversation->isParticipant($user->id), 403);

        $starred = StarredMessage::firstOrCreate([
            'user_id' => $user->id,
            'message_id' => $messageId,
        ], [
            'starred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message starred',
            'starred' => true,
        ]);
    }

    /**
     * Unstar a regular message
     * DELETE /api/v1/messages/{id}/star
     */
    public function unstar(Request $request, $messageId)
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);
        $conversation = $message->conversation;
        abort_unless($conversation->isParticipant($user->id), 403);

        StarredMessage::where('user_id', $user->id)
            ->where('message_id', $messageId)
            ->delete();

        return response()->json([
            'message' => 'Message unstarred',
            'starred' => false,
        ]);
    }

    /**
     * Star a group message
     * POST /api/v1/groups/{groupId}/messages/{id}/star
     */
    public function starGroupMessage(Request $request, $groupId, $messageId)
    {
        $user = $request->user();
        $message = GroupMessage::findOrFail($messageId);
        $group = $message->group;
        abort_unless($group->isMember($user), 403);

        $starred = StarredMessage::firstOrCreate([
            'user_id' => $user->id,
            'group_message_id' => $messageId,
        ], [
            'starred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message starred',
            'starred' => true,
        ]);
    }

    /**
     * Unstar a group message
     * DELETE /api/v1/groups/{groupId}/messages/{id}/star
     */
    public function unstarGroupMessage(Request $request, $groupId, $messageId)
    {
        $user = $request->user();
        $message = GroupMessage::findOrFail($messageId);
        $group = $message->group;
        abort_unless($group->isMember($user), 403);

        StarredMessage::where('user_id', $user->id)
            ->where('group_message_id', $messageId)
            ->delete();

        return response()->json([
            'message' => 'Message unstarred',
            'starred' => false,
        ]);
    }
}

