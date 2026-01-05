<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Status;
use App\Models\StatusComment;
use Illuminate\Http\Request;

class StatusCommentController extends Controller
{
    /**
     * Get comments for a status
     * GET /api/v1/statuses/{statusId}/comments
     */
    public function index(Request $request, $statusId)
    {
        $status = Status::findOrFail($statusId);
        
        // Check if user can view this status (basic check - user should be able to see status to comment)
        // For now, allow if status is from a contact or is own status
        $user = $request->user();
        
        $comments = $status->comments()
            ->with('user:id,name,phone,avatar_path')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'phone' => $comment->user->phone,
                        'avatar_url' => $comment->user->avatar_path 
                            ? asset('storage/' . $comment->user->avatar_path) 
                            : null,
                    ],
                    'created_at' => $comment->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $comments,
        ]);
    }

    /**
     * Add a comment to a status
     * POST /api/v1/statuses/{statusId}/comments
     * 
     * This will also create a message in the chat area between the commenter and status owner
     */
    public function store(Request $request, $statusId)
    {
        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        $status = Status::findOrFail($statusId);
        $status->load('user:id,name,phone,avatar_path');
        $user = $request->user();

        // Create the comment
        $comment = StatusComment::create([
            'status_id' => $status->id,
            'user_id' => $user->id,
            'comment' => $request->comment,
        ]);

        $comment->load('user:id,name,phone,avatar_path');

        // Create a message in the chat area between the commenter and status owner
        // Only create message if the commenter is not the status owner
        if ($user->id !== $status->user_id) {
            try {
                // Find or create conversation between commenter and status owner
                $conversation = Conversation::findOrCreateDirect($user->id, $status->user_id);

                // Prepare status metadata
                $statusMetadata = [
                    'type' => 'status_reply',
                    'status_id' => $status->id,
                    'status_type' => $status->type,
                    'status_text' => $status->text,
                    'status_media_url' => $status->media_url,
                    'status_owner_id' => $status->user_id,
                    'status_owner_name' => $status->user->name ?? $status->user->phone,
                    'commented_at' => now()->toISOString(),
                ];

                // Create message with comment text and status metadata
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'body' => $request->comment,
                    'metadata' => $statusMetadata,
                    'is_encrypted' => false,
                ]);

                // Mark message as delivered
                $message->markAsDeliveredFor($status->user_id);

                // Load relationships
                $message->load(['sender', 'attachments', 'reactions.user']);

                // Broadcast the message
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Exception $e) {
                // Log error but don't fail the comment creation
                \Log::error('Failed to create message for status comment: ' . $e->getMessage(), [
                    'status_id' => $statusId,
                    'user_id' => $user->id,
                    'comment_id' => $comment->id,
                ]);
            }
        }

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'phone' => $comment->user->phone,
                    'avatar_url' => $comment->user->avatar_path 
                        ? asset('storage/' . $comment->user->avatar_path) 
                        : null,
                ],
                'created_at' => $comment->created_at->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Delete a comment
     * DELETE /api/v1/statuses/{statusId}/comments/{commentId}
     */
    public function destroy(Request $request, $statusId, $commentId)
    {
        $comment = StatusComment::findOrFail($commentId);
        $user = $request->user();

        // Only allow deletion if user is the comment author or status owner
        if ($comment->user_id !== $user->id && $comment->status->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
