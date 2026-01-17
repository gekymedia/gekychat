<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorldFeedPost;
use App\Models\Group;

class InviteController extends Controller
{
    /**
     * Show invite page (WhatsApp-style)
     * Handles /wf/{code} for world feed posts and group invites
     */
    public function show($code)
    {
        // Try to find a world feed post
        $post = WorldFeedPost::where('share_code', $code)->first();
        
        // Try to find a group invite
        $group = Group::where('invite_code', $code)->first();
        
        if (!$post && !$group) {
            abort(404, 'Invite link not found');
        }
        
        $type = $post ? 'post' : 'group';
        $item = $post ?? $group;
        
        // Determine deep link and web URL
        $deepLink = null;
        $webUrl = null;
        
        if ($type === 'post') {
            $deepLink = "gekychat://world-feed/post/{$code}";
            $webUrl = route('world-feed.index');
        } else {
            $deepLink = "gekychat://group/join/{$code}";
            $webUrl = route('groups.join-via-invite', $code);
        }
        
        return view('invite.show', [
            'type' => $type,
            'item' => $item,
            'code' => $code,
            'deepLink' => $deepLink,
            'webUrl' => $webUrl,
        ]);
    }
}
