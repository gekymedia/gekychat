<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Display all messages with pagination
     */
    public function messages()
    {
        $messages = Message::with(['sender', 'conversation', 'attachments'])
            ->latest()
            ->paginate(20);

        return view('admin.messages.index', compact('messages'));
    }

    /**
     * Delete a specific message
     */
    public function delete($id)
    {
        $message = Message::findOrFail($id);
        
        // Broadcast deletion event if needed
        event(new \App\Events\MessageDeleted($message->id));
        
        $message->delete();

        return back()->with('success', 'Message deleted successfully');
    }

    /**
     * Display all conversations
     */
    public function conversations()
    {
        $conversations = Conversation::with(['userOne', 'userTwo', 'messages'])
            ->latest()
            ->paginate(20);

        return view('admin.conversations.index', compact('conversations'));
    }

    /**
     * Delete a conversation and its messages
     */
    public function deleteConversation($id)
    {
        $conversation = Conversation::findOrFail($id);
        
        // Delete all messages first
        $conversation->messages()->delete();
        
        // Broadcast chat cleared event
        event(new \App\Events\ChatCleared($conversation->id));
        
        $conversation->delete();

        return back()->with('success', 'Conversation and all associated messages deleted');
    }

    /**
     * Display user management
     */
    public function users()
    {
        $users = User::withCount(['conversationsAsUserOne', 'conversationsAsUserTwo', 'messages'])
            ->latest()
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Toggle user ban status
     */
    public function toggleBan($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'is_banned' => !$user->is_banned
        ]);

        $status = $user->is_banned ? 'banned' : 'unbanned';
        return back()->with('success', "User {$status} successfully");
    }

    /**
     * View message details
     */
    public function showMessage($id)
    {
        $message = Message::with(['sender', 'conversation', 'attachments', 'replyTo'])
            ->findOrFail($id);

        return view('admin.messages.show', compact('message'));
    }
}