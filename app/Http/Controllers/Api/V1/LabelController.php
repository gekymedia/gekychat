<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API controller for managing user labels. Labels allow users to create
 * personalized filters for their conversations.
 */
class LabelController extends Controller
{
    /**
     * List all labels for the authenticated user.
     */
    public function index(Request $request)
    {
        $labels = $request->user()->labels()->select(['id', 'name'])->get();
        return response()->json(['data' => $labels]);
    }

    /**
     * Create a new label.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
        ]);
        $label = $request->user()->labels()->create([
            'name' => $request->name,
        ]);
        return response()->json(['data' => $label], 201);
    }

    /**
     * Delete a label. Removing a label will also detach it from all
     * conversations.
     */
    public function destroy(Request $request, $labelId)
    {
        $label = $request->user()->labels()->findOrFail($labelId);
        $label->delete();
        return response()->json(['message' => 'Label deleted']);
    }

    /**
     * Assign a label to a conversation.
     */
    public function attachToConversation(Request $request, $labelId, $conversationId)
    {
        $conversation = Conversation::forUser($request->user()->id)->findOrFail($conversationId);
        $label = $request->user()->labels()->findOrFail($labelId);
        $conversation->labels()->syncWithoutDetaching([$label->id]);
        return response()->json(['message' => 'Label attached']);
    }

    /**
     * Remove a label from a conversation.
     */
    public function detachFromConversation(Request $request, $labelId, $conversationId)
    {
        $conversation = Conversation::forUser($request->user()->id)->findOrFail($conversationId);
        $label = $request->user()->labels()->findOrFail($labelId);
        $conversation->labels()->detach($label->id);
        return response()->json(['message' => 'Label detached']);
    }
}