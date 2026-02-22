<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\WorldFeedPost;
use App\Models\Group;

class InviteController extends Controller
{
    /**
     * Show invite page (WhatsApp-style)
     * Handles /wf/{code} for world feed posts and group invites.
     * Public route (no auth) so shared links show Open Graph previews.
     */
    public function show($code)
    {
        // Try to find a world feed post
        $post = WorldFeedPost::where('share_code', $code)->with('creator:id,name,username')->first();
        
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
        $ogTitle = null;
        $ogDescription = null;
        $ogImage = null;
        $canonicalUrl = null;
        
        if ($type === 'post') {
            $deepLink = "gekychat://world-feed/post/{$code}";
            $webUrl = route('world-feed.index');
            $canonicalUrl = url("/wf/{$code}");
            $creatorName = $post->creator ? ($post->creator->name ?: $post->creator->username) : 'Someone';
            $ogTitle = config('app.name', 'GekyChat') . ' · ' . $creatorName;
            $ogDescription = $post->caption
                ? \Illuminate\Support\Str::limit(strip_tags($post->caption), 160)
                : "Watch this post by {$creatorName} on " . config('app.name', 'GekyChat');
            // Absolute URL for thumbnail so crawlers can fetch it (required for link previews)
            $thumbPath = $post->thumbnail_url ?: $post->media_url;
            if ($thumbPath) {
                if (\Illuminate\Support\Str::startsWith($thumbPath, ['http://', 'https://'])) {
                    $ogImage = $thumbPath;
                } else {
                    $path = ltrim($thumbPath, '/');
                    $ogImage = Storage::disk('public')->exists($path)
                        ? asset('storage/' . $path)
                        : null;
                }
            }
        } else {
            $canonicalUrl = url("/wf/{$code}");
            $ogTitle = config('app.name', 'GekyChat') . ' · Join group';
            $ogDescription = "You've been invited to join a group on " . config('app.name', 'GekyChat');
        }
        
        return view('invite.show', [
            'type' => $type,
            'item' => $item,
            'code' => $code,
            'deepLink' => $deepLink,
            'webUrl' => $webUrl,
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
