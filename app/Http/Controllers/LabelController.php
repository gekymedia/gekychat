<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LabelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List all labels for the authenticated user.
     */
    public function index()
    {
        $labels = Auth::user()->labels()->select(['id', 'name'])->get();
        
        return response()->json([
            'success' => true,
            'data' => $labels
        ]);
    }

    /**
     * Store a newly created label.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $label = Auth::user()->labels()->create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Label created successfully',
            'data' => [
                'id' => $label->id,
                'name' => $label->name,
            ]
        ], 201);
    }

    /**
     * Remove the specified label.
     */
    public function destroy($id)
    {
        $label = Auth::user()->labels()->findOrFail($id);
        $label->delete();

        return response()->json([
            'success' => true,
            'message' => 'Label deleted successfully'
        ]);
    }

    /**
     * Attach a label to a conversation.
     */
    public function attachToConversation($labelId, $conversationId)
    {
        $label = Auth::user()->labels()->findOrFail($labelId);
        $conversation = \App\Models\Conversation::forUser(Auth::id())->findOrFail($conversationId);
        
        $conversation->labels()->syncWithoutDetaching([$label->id]);
        
        return response()->json([
            'success' => true,
            'message' => 'Label attached successfully'
        ]);
    }

    /**
     * Detach a label from a conversation.
     */
    public function detachFromConversation($labelId, $conversationId)
    {
        $label = Auth::user()->labels()->findOrFail($labelId);
        $conversation = \App\Models\Conversation::forUser(Auth::id())->findOrFail($conversationId);
        
        $conversation->labels()->detach($label->id);
        
        return response()->json([
            'success' => true,
            'message' => 'Label detached successfully'
        ]);
    }
}
