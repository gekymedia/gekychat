<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickReplyController extends Controller
{
    public function index()
    {
        $quickReplies = QuickReply::where('user_id', Auth::id())
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('settings.quick-replies', compact('quickReplies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
        ]);

        // Get the highest order value for this user
        $maxOrder = QuickReply::where('user_id', Auth::id())->max('order') ?? 0;

        $quickReply = QuickReply::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'message' => $request->message,
            'order' => $maxOrder + 1,
            'usage_count' => 0
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quick reply added successfully',
                'quick_reply' => $quickReply
            ]);
        }

        return redirect()->route('settings.quick-replies')
            ->with('status', 'Quick reply added successfully');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
        ]);

        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);

        $quickReply->update([
            'title' => $request->title,
            'message' => $request->message,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quick reply updated successfully',
                'quick_reply' => $quickReply
            ]);
        }

        return redirect()->route('settings.quick-replies')
            ->with('status', 'Quick reply updated successfully');
    }

    public function destroy($id)
    {
        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);
        $quickReply->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Quick reply deleted successfully'
            ]);
        }

        return redirect()->route('settings.quick-replies')
            ->with('status', 'Quick reply deleted successfully');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
        ]);

        foreach ($request->order as $index => $id) {
            QuickReply::where('user_id', Auth::id())
                ->where('id', $id)
                ->update(['order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    // For chat usage
    public function getQuickReplies(Request $request)
    {
        $quickReplies = QuickReply::where('user_id', Auth::id())
            ->orderBy('order')
            ->orderBy('usage_count', 'desc')
            ->get(['id', 'title', 'message', 'usage_count']);

        return response()->json([
            'success' => true,
            'quick_replies' => $quickReplies
        ]);
    }

    public function recordUsage(Request $request, $id)
    {
        $quickReply = QuickReply::where('user_id', Auth::id())->findOrFail($id);
        $quickReply->increment('usage_count');
        $quickReply->last_used_at = now();
        $quickReply->save();

        return response()->json(['success' => true]);
    }
}