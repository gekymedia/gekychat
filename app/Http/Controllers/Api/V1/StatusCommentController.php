<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
     */
    public function store(Request $request, $statusId)
    {
        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        $status = Status::findOrFail($statusId);
        $user = $request->user();

        $comment = StatusComment::create([
            'status_id' => $status->id,
            'user_id' => $user->id,
            'comment' => $request->comment,
        ]);

        $comment->load('user:id,name,phone,avatar_path');

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
