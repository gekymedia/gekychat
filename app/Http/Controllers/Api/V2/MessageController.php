<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\MessageResource;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, $conversationId)
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender', 'reactions', 'replyTo'])
            ->orderByDesc('created_at')
            ->cursorPaginate(50);
        
        return MessageResource::collection($messages);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required|string|max:10000',
            'reply_to' => 'nullable|exists:messages,id',
            'attachments' => 'nullable|array',
        ]);
        
        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id' => $request->user()->id,
            'body' => $validated['content'],
            'reply_to_id' => $validated['reply_to'] ?? null,
        ]);
        
        return new MessageResource($message->load(['sender', 'reactions']));
    }
    
    public function update(Request $request, $id)
    {
        $message = Message::findOrFail($id);
        
        $this->authorize('update', $message);
        
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);
        
        $message->update([
            'body' => $validated['content'],
            'edited_at' => now(),
        ]);
        
        return new MessageResource($message->load(['sender', 'reactions']));
    }
    
    public function destroy(Request $request, $id)
    {
        $message = Message::findOrFail($id);
        
        $this->authorize('delete', $message);
        
        $deleteForEveryone = $request->boolean('for_everyone', false);
        
        if ($deleteForEveryone) {
            $message->update(['deleted_for_everyone' => true]);
        } else {
            $message->delete();
        }
        
        return response()->json(['message' => 'Message deleted']);
    }
    
    public function react(Request $request, $id)
    {
        $validated = $request->validate([
            'emoji' => 'required|string|max:10',
        ]);
        
        $message = Message::findOrFail($id);
        
        // Toggle reaction
        $message->reactions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['emoji' => $validated['emoji']]
        );
        
        return new MessageResource($message->load(['sender', 'reactions']));
    }
}
