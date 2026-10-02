<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DirectChatController extends Controller
{
    /**
     * wa.me-style short link: /me/{phone}?text=...
     * Forwards to the WhatsApp-style /send/ chooser so desktop/web can be picked
     * and the message is prefilled.
     */
    public function handleDirectLink(Request $request, $identifier)
    {
        $phone = preg_replace('/[^\d+]/', '', (string) $identifier);
        if ($phone === '') {
            abort(400, 'Phone number is required');
        }

        $params = [
            'phone' => $phone,
            'app_absent' => $request->query('app_absent', '0'),
        ];
        $text = $request->query('text');
        if ($text !== null && $text !== '') {
            $params['text'] = $text;
        }

        return redirect()->route('send.link', $params);
    }

    /**
     * Handle WhatsApp-style send link: /send/?phone=...&text=...&type=...&app_absent=...
     *
     * app_absent=0 (default): public chooser — Open app vs Continue on web
     * app_absent=1: open web chat (login if needed) with composer prefill
     */
    public function handleSendLink(Request $request)
    {
        $phone = $request->query('phone');
        $text = (string) $request->query('text', '');
        $type = $request->query('type', 'phone_number');
        $appAbsent = (string) $request->query('app_absent', '0');

        if (!$phone) {
            abort(400, 'Phone number is required');
        }

        $phone = preg_replace('/[^\d+]/', '', $phone);

        $targetUser = User::where('phone', $phone)
            ->orWhere('phone', 'like', '%' . substr($phone, -9))
            ->first();

        // Public chooser (like api.whatsapp.com/send) — no login required yet.
        if ($appAbsent === '0') {
            $deepLink = 'gekychat://send?phone=' . urlencode($phone)
                . '&text=' . urlencode($text);

            $webContinueUrl = route('send.link', array_filter([
                'phone' => $phone,
                'text' => $text !== '' ? $text : null,
                'type' => $type,
                'app_absent' => '1',
            ], static fn ($v) => $v !== null && $v !== ''));

            return view('send.link', [
                'phone' => $phone,
                'text' => $text,
                'type' => $type,
                'targetUser' => $targetUser,
                'deepLink' => $deepLink,
                'webContinueUrl' => $webContinueUrl,
                'currentUser' => Auth::user(),
            ]);
        }

        // Continue on web — require login, preserve full send URL.
        if (!Auth::check()) {
            session([
                'url.intended' => route('send.link', array_filter([
                    'phone' => $phone,
                    'text' => $text !== '' ? $text : null,
                    'type' => $type,
                    'app_absent' => '1',
                ], static fn ($v) => $v !== null && $v !== '')),
            ]);

            return redirect()->route('login')
                ->with('info', 'Please log in to send a message');
        }

        $currentUser = Auth::user();

        if ($targetUser && $targetUser->id !== $currentUser->id) {
            $conversation = Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);

            if ($text !== '') {
                return redirect()->route('chat.show', [
                    'conversation' => $conversation->slug,
                    'text' => $text,
                ]);
            }

            return redirect()->route('chat.show', $conversation->slug);
        }

        return view('chat.unknown-number', [
            'phone' => $phone,
            'text' => $text,
            'userExists' => false,
        ]);
    }
}
