<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageMention;
use App\Services\MentionService;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    protected $mentionService;
    
    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }
    
    /**
     * Get unread mentions for current user
     * GET /api/v1/mentions
     */
    public function index(Request $request)
    {
        $limit = (int) $request->input('limit', 50);
        $mentions = $this->mentionService->getUnreadMentions($request->user()->id, $limit);
        
        return response()->json([
            'data' => $mentions,
            'count' => $mentions->count(),
        ]);
    }
    
    /**
     * Get mention statistics
     * GET /api/v1/mentions/stats
     */
    public function stats(Request $request)
    {
        $stats = $this->mentionService->getMentionStats($request->user()->id);
        return response()->json($stats);
    }
    
    /**
     * Mark mention as read
     * POST /api/v1/mentions/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $mention = MessageMention::findOrFail($id);
        
        // Verify user owns this mention
        if ($mention->mentioned_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $mention->markAsRead();
        
        return response()->json([
            'message' => 'Mention marked as read',
            'mention' => $mention,
        ]);
    }
    
    /**
     * Mark all mentions as read
     * POST /api/v1/mentions/read-all
     */
    public function markAllAsRead(Request $request)
    {
        $updated = MessageMention::forUser($request->user()->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        
        return response()->json([
            'message' => "Marked {$updated} mentions as read",
            'count' => $updated,
        ]);
    }
}
