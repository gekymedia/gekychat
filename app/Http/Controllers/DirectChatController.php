<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DirectChatController extends Controller
{
    public function handleDirectLink(Request $request, $identifier)
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $identifier);
        
        // If user is not authenticated, redirect to login with redirect back
        if (!Auth::check()) {
            session(['url.intended' => route('direct.chat', $identifier)]);
            return redirect()->route('login');
        }
        
        $currentUser = Auth::user();
        
        // Find user by phone
        $targetUser = User::where('phone', $phone)
            ->orWhere('phone', 'like', '%' . substr($phone, -9)) // Match last 9 digits
            ->first();
        
        if ($targetUser && $targetUser->id !== $currentUser->id) {
            // Create or get conversation
            $conversation = Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);
            
            return redirect()->route('chat.show', $conversation->slug);
        }
        
        // If user not found, show option to message the number
        return view('chat.unknown-number', [
            'phone' => $phone,
            'userExists' => false
        ]);
    }
}