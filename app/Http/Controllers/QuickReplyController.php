<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use App\Models\QuickReplyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickReplyController extends Controller
{
    public function getQuickReplies(Request $request)
    {
        $userId = Auth::id();
        
        $categories = QuickReplyCategory::with(['quickReplies' => function($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('is_global', true)
                  ->orderBy('order', 'asc');
        }])
        ->whereHas('quickReplies', function($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('is_global', true);
        })
        ->orderBy('order', 'asc')
        ->get();

        $frequentReplies = QuickReply::where('user_id', $userId)
            ->orWhere('is_global', true)
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'frequent_replies' => $frequentReplies
        ]);
    }

    public function createQuickReply(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'category_id' => 'nullable|exists:quick_reply_categories,id',
            'is_global' => 'boolean'
        ]);

        $quickReply = QuickReply::create([
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'title' => $request->title,
            'message' => $request->message,
            'shortcut' => $request->shortcut,
            'is_global' => $request->is_global ?? false,
            'order' => $request->order ?? 0,
            'usage_count' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quick reply created successfully',
            'quick_reply' => $quickReply
        ]);
    }

    public function recordUsage(Request $request, $id)
    {
        $quickReply = QuickReply::where('id', $id)
            ->where(function($query) {
                $query->where('user_id', Auth::id())
                      ->orWhere('is_global', true);
            })
            ->firstOrFail();

        $quickReply->increment('usage_count');
        $quickReply->last_used_at = now();
        $quickReply->save();

        return response()->json(['success' => true]);
    }

    public function deleteQuickReply($id)
    {
        $quickReply = QuickReply::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $quickReply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quick reply deleted successfully'
        ]);
    }

    public function searchQuickReplies(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $userId = Auth::id();
        $query = $request->query;

        $results = QuickReply::where(function($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('is_global', true);
            })
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('message', 'like', "%{$query}%")
                  ->orWhere('shortcut', 'like', "%{$query}%");
            })
            ->with('category')
            ->orderBy('usage_count', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
}
