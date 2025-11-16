<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API controller for managing user quick replies.
 *
 * Endpoints:
 *   GET    /api/v1/quick-replies           -> index()
 *   POST   /api/v1/quick-replies           -> store()
 *   PUT    /api/v1/quick-replies/{id}      -> update()
 *   DELETE /api/v1/quick-replies/{id}      -> destroy()
 *   POST   /api/v1/quick-replies/{id}/usage -> recordUsage()
 */
class QuickReplyController extends Controller
{
    /**
     * Display a listing of the user's quick replies.
     */
    public function index(Request $request)
    {
        $quickReplies = QuickReply::where('user_id', Auth::id())
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'quick_replies' => $quickReplies,
        ]);
    }

    /**
     * Store a newly created quick reply in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
        ]);

        $maxOrder = QuickReply::where('user_id', Auth::id())->max('order') ?? 0;

        $quickReply = QuickReply::create([
            'user_id' => Auth::id(),
            'title'   => $request->title,
            'message' => $request->message,
            'order'   => $maxOrder + 1,
            'usage_count' => 0,
        ]);

        return response()->json([
            'success' => true,
            'quick_reply' => $quickReply,
        ], 201);
    }

    /**
     * Update the specified quick reply.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
        ]);

        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);
        $quickReply->update([
            'title'   => $request->title,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'quick_reply' => $quickReply,
        ]);
    }

    /**
     * Remove the specified quick reply from storage.
     */
    public function destroy($id)
    {
        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);
        $quickReply->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Increment the usage count of a quick reply when used in a chat.
     */
    public function recordUsage($id)
    {
        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);
        $quickReply->increment('usage_count');
        $quickReply->last_used_at = now();
        $quickReply->save();

        return response()->json([
            'success' => true,
        ]);
    }
}