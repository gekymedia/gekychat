<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DraftMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DraftController
 * 
 * Handles draft message auto-save functionality for conversations.
 * Supports WhatsApp-style draft persistence with media, replies, and mentions.
 */
class DraftController extends Controller
{
    /**
     * Get draft for a conversation
     * 
     * GET /api/v1/drafts/{conversationId}
     */
    public function show($conversationId)
    {
        try {
            $userId = Auth::id();
            
            // Verify conversation exists and user has access
            $conversation = Conversation::findOrFail($conversationId);
            
            // Check if user is part of this conversation
            if ($conversation->user_id !== $userId && $conversation->contact_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to conversation'
                ], 403);
            }
            
            // Get draft
            $draft = DraftMessage::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$draft) {
                return response()->json([
                    'status' => 'success',
                    'draft' => null
                ]);
            }
            
            return response()->json([
                'status' => 'success',
                'draft' => [
                    'content' => $draft->content,
                    'media_urls' => $draft->mediaUrls,
                    'reply_to_id' => $draft->reply_to_id,
                    'mentions' => $draft->mentions,
                    'saved_at' => $draft->saved_at->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load draft: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save or update draft for a conversation
     * 
     * POST /api/v1/drafts/{conversationId}
     */
    public function store(Request $request, $conversationId)
    {
        try {
            $userId = Auth::id();
            
            // Verify conversation exists and user has access
            $conversation = Conversation::findOrFail($conversationId);
            
            // Check if user is part of this conversation
            if ($conversation->user_id !== $userId && $conversation->contact_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to conversation'
                ], 403);
            }
            
            // Validate request
            $validated = $request->validate([
                'content' => 'nullable|string',
                'media_urls' => 'nullable|array',
                'media_urls.*' => 'string',
                'reply_to_id' => 'nullable|integer|exists:messages,id',
                'mentions' => 'nullable|array',
                'mentions.*' => 'integer',
            ]);
            
            // Update or create draft
            $draft = DraftMessage::updateOrCreate(
                [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                ],
                [
                    'content' => $validated['content'] ?? '',
                    'media_urls_json' => isset($validated['media_urls']) ? json_encode($validated['media_urls']) : null,
                    'reply_to_id' => $validated['reply_to_id'] ?? null,
                    'mentions_json' => isset($validated['mentions']) ? json_encode($validated['mentions']) : null,
                    'saved_at' => now(),
                ]
            );
            
            return response()->json([
                'status' => 'success',
                'message' => 'Draft saved successfully',
                'draft' => [
                    'content' => $draft->content,
                    'media_urls' => $draft->mediaUrls,
                    'reply_to_id' => $draft->reply_to_id,
                    'mentions' => $draft->mentions,
                    'saved_at' => $draft->saved_at->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save draft: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete draft for a conversation
     * 
     * DELETE /api/v1/drafts/{conversationId}
     */
    public function destroy($conversationId)
    {
        try {
            $userId = Auth::id();
            
            // Delete draft
            $deleted = DraftMessage::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => $deleted ? 'Draft deleted successfully' : 'No draft found',
                'deleted' => $deleted > 0
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete draft: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all conversations with drafts for current user
     * 
     * GET /api/v1/drafts
     */
    public function index()
    {
        try {
            $userId = Auth::id();
            
            $drafts = DraftMessage::where('user_id', $userId)
                ->with('conversation')
                ->orderBy('saved_at', 'desc')
                ->get()
                ->map(function ($draft) {
                    return [
                        'conversation_id' => $draft->conversation_id,
                        'content' => $draft->content,
                        'preview' => \Str::limit($draft->content, 50),
                        'saved_at' => $draft->saved_at->toIso8601String(),
                        'has_media' => !empty($draft->mediaUrls),
                        'has_reply' => !is_null($draft->reply_to_id),
                    ];
                });
            
            return response()->json([
                'status' => 'success',
                'drafts' => $drafts
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load drafts: ' . $e->getMessage()
            ], 500);
        }
    }
}
