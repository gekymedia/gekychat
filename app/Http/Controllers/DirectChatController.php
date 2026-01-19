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

    /**
     * Handle WhatsApp-style send link: /send/?phone=...&text=...&type=...&app_absent=...
     */
    public function handleSendLink(Request $request)
    {
        $phone = $request->query('phone');
        $text = $request->query('text', '');
        $type = $request->query('type', 'phone_number');
        $appAbsent = $request->query('app_absent', '0');

        // Validate phone number
        if (!$phone) {
            abort(400, 'Phone number is required');
        }

        // Normalize phone number (remove non-digit characters except +)
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // If user is not authenticated, redirect to login with parameters preserved
        if (!Auth::check()) {
            session([
                'url.intended' => route('send.link', [
                    'phone' => $phone,
                    'text' => $text,
                    'type' => $type,
                    'app_absent' => $appAbsent
                ])
            ]);
            return redirect()->route('login')
                ->with('info', 'Please log in to send a message');
        }

        $currentUser = Auth::user();

        // Find user by phone
        $targetUser = User::where('phone', $phone)
            ->orWhere('phone', 'like', '%' . substr($phone, -9)) // Match last 9 digits
            ->first();

        // If app_absent=0, try to open desktop/mobile app first
        if ($appAbsent === '0') {
            // Create deep link for app
            $deepLink = "gekychat://send?phone=" . urlencode($phone) . "&text=" . urlencode($text);
            
            // Return view that tries to open app, then falls back to web
            return view('send.link', [
                'phone' => $phone,
                'text' => $text,
                'type' => $type,
                'targetUser' => $targetUser,
                'deepLink' => $deepLink,
                'currentUser' => $currentUser,
            ]);
        }

        // If app_absent=1 or app not available, show web interface
        if ($targetUser && $targetUser->id !== $currentUser->id) {
            // Create or get conversation
            $conversation = Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);
            
            // If text is provided, redirect to chat with pre-filled message via query parameter
            if (!empty($text)) {
                return redirect()
                    ->route('chat.show', ['conversation' => $conversation->slug, 'text' => $text]);
            }
            
            return redirect()->route('chat.show', $conversation->slug);
        }

        // If user not found, show option to message the number
        return view('chat.unknown-number', [
            'phone' => $phone,
            'text' => $text,
            'userExists' => false
        ]);
    }
}